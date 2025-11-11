package monitoring

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"net/url"
	"strconv"
	"sync"
	"time"

	"api-monitor-go/internal/database"
	"api-monitor-go/internal/logger"
	"api-monitor-go/internal/models"
	"api-monitor-go/internal/resilience"
	"api-monitor-go/internal/websocket"
	"github.com/redis/go-redis/v9"
)

// Redis stream names
const (
	MetricsStream = "api-metrics"
	AlertsStream  = "alerts-fired"
)

// Service coordinates endpoint checks, result persistence, alert evaluation,
// WebSocket broadcasting, and publishing to the Redis stream.
type Service struct {
	repo           *database.Repository
	hub            *websocket.Hub
	client         *http.Client
	rdb            *redis.Client
	symphonyAPIURL string
	httpClientTimeout time.Duration
	circuitBreaker *resilience.CircuitBreaker
	retrier        *resilience.Retrier
	log            *logger.Logger
}

func NewService(repo *database.Repository, hub *websocket.Hub, rdb *redis.Client, symphonyAPIURL string, httpClientTimeout time.Duration) *Service {
	log := logger.New()
	log.SetLevel(logger.LevelInfo)

	circuitBreakerConfig := resilience.CircuitBreakerConfig{
		Name:          "endpoint-checker",
		MaxFailures:   5,
		Timeout:       30 * time.Second,
		ResetInterval: 60 * time.Second,
	}

	retryConfig := resilience.RetryConfig{
		MaxAttempts:  3,
		InitialDelay: 100 * time.Millisecond,
		MaxDelay:     2 * time.Second,
		Multiplier:   2.0,
		Jitter:       true,
	}

	return &Service{
		repo:           repo,
		hub:            hub,
		client:         &http.Client{Timeout: httpClientTimeout},
		rdb:            rdb,
		symphonyAPIURL: symphonyAPIURL,
		httpClientTimeout: httpClientTimeout,
		circuitBreaker: resilience.NewCircuitBreaker(circuitBreakerConfig),
		retrier:        resilience.NewRetrier(retryConfig),
		log:            log,
	}
}

// MonitorEndpoints performs concurrent monitoring of all active endpoints
// This method should be called with a timeout context to prevent indefinite runs
func (s *Service) MonitorEndpoints() error {
	// Create a context with default timeout for the entire monitoring cycle
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Minute)
	defer cancel()

	return s.MonitorEndpointsWithContext(ctx)
}

// MonitorEndpointsWithContext performs concurrent monitoring with context-aware cancellation
func (s *Service) MonitorEndpointsWithContext(ctx context.Context) error {
	// Check context before starting
	select {
	case <-ctx.Done():
		return ctx.Err()
	default:
	}

	// Run checks concurrently and publish results to both storage and consumers.
	endpoints, err := s.repo.GetActiveEndpoints()
	if err != nil {
		s.log.Errorf("failed to get endpoints: %v", err)
		return fmt.Errorf("failed to get endpoints: %w", err)
	}

	var wg sync.WaitGroup
	results := make(chan models.MonitoringResult, len(endpoints))

	for _, endpoint := range endpoints {
		// Check context before spawning goroutine
		select {
		case <-ctx.Done():
			close(results)
			return ctx.Err()
		default:
		}

		// Track one goroutine per endpoint to check.
		wg.Add(1)
		go func(e models.Endpoint) {
			defer wg.Done()
			result := s.checkEndpoint(e)
			results <- result
		}(endpoint)
	}

	go func() {
		wg.Wait()
		close(results)
	}()

	// Collect errors during result processing
	var processingErrors []error

	for result := range results {
		// Check context during result processing
		select {
		case <-ctx.Done():
			processingErrors = append(processingErrors, ctx.Err())
			break
		default:
		}

		if err := s.repo.SaveResult(result); err != nil {
			s.log.WithField("endpoint_id", result.EndpointID).Errorf("failed to save result: %v", err)
			processingErrors = append(processingErrors, err)
		} else {
			// Log for monitoring
			s.log.WithFields(map[string]interface{}{
				"endpoint_id":   result.EndpointID,
				"response_time": result.ResponseTime,
				"status_code":   result.StatusCode,
			}).Info("endpoint checked successfully")

			// Broadcast to WebSocket clients
			s.hub.Broadcast(result)

			// Publish to Redis stream for analytics (async)
			go s.publishToStream(result)

			// Notify Symfony for alert evaluation asynchronously (non-blocking)
			go s.notifySymfonyForAlertEvaluation(result)
		}
	}

	// If there were processing errors, log them but don't fail the entire cycle
	if len(processingErrors) > 0 {
		s.log.Warnf("monitoring cycle completed with %d errors", len(processingErrors))
	}

	return nil
}

func (s *Service) checkEndpoint(endpoint models.Endpoint) models.MonitoringResult {
	// Validate endpoint URL
	if !isValidEndpointURL(endpoint.URL) {
		errorMsg := "invalid endpoint URL format"
		s.log.WithField("endpoint_id", endpoint.ID).Warnf("invalid URL: %s", endpoint.URL)
		return models.MonitoringResult{
			EndpointID:   endpoint.ID,
			ResponseTime: 0,
			StatusCode:   nil,
			ErrorMessage: &errorMsg,
			CheckedAt:    time.Now(),
		}
	}

	client := &http.Client{
		Timeout: time.Duration(endpoint.Timeout) * time.Millisecond,
	}

	// Use circuit breaker and retry logic for endpoint check
	var result models.MonitoringResult
	err := s.circuitBreaker.Execute(func() error {
		return s.retrier.Do(func() error {
			return s.executeEndpointCheck(client, endpoint, &result)
		})
	})

	if err != nil {
		// Log circuit breaker or retry errors
		s.log.WithField("endpoint_id", endpoint.ID).Warnf("endpoint check failed after retries: %v", err)
		errorMsg := fmt.Sprintf("request failed: %v", err)
		result.ErrorMessage = &errorMsg
		result.EndpointID = endpoint.ID
		result.CheckedAt = time.Now()
	}

	return result
}

// executeEndpointCheck performs the actual HTTP request to the endpoint
func (s *Service) executeEndpointCheck(client *http.Client, endpoint models.Endpoint, result *models.MonitoringResult) error {
	req, err := http.NewRequest("GET", endpoint.URL, nil)
	if err != nil {
		return fmt.Errorf("failed to create request: %w", err)
	}

	// Add headers if any
	if len(endpoint.Headers) > 0 {
		var headers map[string]string
		if err := json.Unmarshal(endpoint.Headers, &headers); err != nil {
			s.log.WithField("endpoint_id", endpoint.ID).Warnf("failed to parse headers: %v", err)
		} else {
			for key, value := range headers {
				req.Header.Set(key, value)
			}
		}
	}

	start := time.Now()
	resp, err := client.Do(req)
	responseTime := int(time.Since(start).Milliseconds())

	result.EndpointID = endpoint.ID
	result.ResponseTime = responseTime
	result.CheckedAt = time.Now()

	if err != nil {
		return err
	}

	defer resp.Body.Close()

	result.StatusCode = &resp.StatusCode
	result.ErrorMessage = nil

	return nil
}

// isValidEndpointURL validates that a URL is properly formatted
func isValidEndpointURL(urlStr string) bool {
	if urlStr == "" {
		return false
	}
	u, err := url.Parse(urlStr)
	if err != nil {
		return false
	}
	return u.Scheme != "" && u.Host != ""
}

func (s *Service) notifySymfonyForAlertEvaluation(result models.MonitoringResult) {
	// Create context with timeout for this notification
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	payload := map[string]interface{}{
		"endpoint_id":   result.EndpointID,
		"response_time": result.ResponseTime,
		"status_code":   result.StatusCode,
		"error_message": result.ErrorMessage,
		"checked_at":    result.CheckedAt,
	}

	jsonData, err := json.Marshal(payload)
	if err != nil {
		s.log.Errorf("failed to marshal monitoring result for alert evaluation: %v", err)
		return
	}

	// Construct alert evaluation URL
	alertURL := s.symphonyAPIURL + "/api/monitoring/evaluate-alerts"
	if _, err := url.Parse(alertURL); err != nil {
		s.log.Errorf("invalid Symfony API URL: %v", err)
		return
	}

	// POST to Symfony for alert evaluation with retry logic
	err = s.retrier.DoWithContext(ctx, func(retryCtx context.Context) error {
		req, err := http.NewRequestWithContext(retryCtx, "POST", alertURL, bytes.NewBuffer(jsonData))
		if err != nil {
			return fmt.Errorf("failed to create alert notification request: %w", err)
		}
		req.Header.Set("Content-Type", "application/json")

		resp, err := s.client.Do(req)
		if err != nil {
			return fmt.Errorf("failed to notify Symfony for alert evaluation: %w", err)
		}
		defer resp.Body.Close()

		if resp.StatusCode != http.StatusOK && resp.StatusCode != http.StatusNoContent {
			s.log.WithField("status_code", resp.StatusCode).Warnf("Symfony alert evaluation returned unexpected status")
		}

		return nil
	})

	if err != nil {
		s.log.WithField("endpoint_id", result.EndpointID).Warnf("failed to notify Symfony after retries: %v", err)
	}
}

func (s *Service) publishToStream(result models.MonitoringResult) {
	// Create context with timeout for Redis operation
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	streamData := map[string]interface{}{
		"endpoint_id":   strconv.Itoa(result.EndpointID),
		"response_time": strconv.Itoa(result.ResponseTime),
		"timestamp":     result.CheckedAt.Format(time.RFC3339),
	}

	if result.StatusCode != nil {
		streamData["status_code"] = strconv.Itoa(*result.StatusCode)
	}

	if result.ErrorMessage != nil {
		streamData["error_message"] = *result.ErrorMessage
	}

	// Publish to Redis stream with retry logic
	err := s.retrier.DoWithContext(ctx, func(retryCtx context.Context) error {
		return s.rdb.XAdd(retryCtx, &redis.XAddArgs{
			Stream: MetricsStream,
			ID:     "*",
			Values: streamData,
		}).Err()
	})

	if err != nil {
		s.log.WithField("endpoint_id", result.EndpointID).Warnf("failed to publish metrics to Redis stream: %v", err)
	}
}

// Removed: evaluateAlerts function
// Alert evaluation is now handled exclusively in Symfony (AlertEvaluationService)
// This ensures single source of truth and prevents duplicate logic
// Go service only: persists results, broadcasts via WebSocket, publishes to Redis
