package monitoring

import (
"bytes"
"context"
"encoding/json"
"fmt"
"log"
"net/http"
"os"
"strconv"
	"sync"
"time"
)

// Service coordinates endpoint checks, result persistence, alert evaluation,
// WebSocket broadcasting, and publishing to the Redis stream.

"api-monitor-go/internal/database"
"api-monitor-go/internal/models"
	"api-monitor-go/internal/websocket"
	"github.com/redis/go-redis/v9"
)

type Service struct {
repo   *database.Repository
hub    *websocket.Hub
client *http.Client
rdb    *redis.Client
	ctx    context.Context
}

func NewService(repo *database.Repository, hub *websocket.Hub, rdb *redis.Client) *Service {
return &Service{
repo:   repo,
hub:    hub,
client: &http.Client{Timeout: 30 * time.Second},
rdb:    rdb,
ctx:    context.Background(),
	}
}

func (s *Service) MonitorEndpoints() error {
	// Run checks concurrently and publish results to both storage and consumers.
	endpoints, err := s.repo.GetActiveEndpoints()
	if err != nil {
		return fmt.Errorf("failed to get endpoints: %w", err)
	}

	var wg sync.WaitGroup
	results := make(chan models.MonitoringResult, len(endpoints))

	for _, endpoint := range endpoints {
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

	for result := range results {
	alerts, _ := s.repo.GetAlertsForEndpoint(result.EndpointID)
	s.evaluateAlerts(result, alerts)

	if err := s.repo.SaveResult(result); err != nil {
	log.Printf("Error saving result for endpoint %d: %v", result.EndpointID, err)
	} else {
	// Operator-facing summary; prefer debug level for verbose traces.
		log.Printf("Checked endpoint %d: %dms, status: %v", result.EndpointID, result.ResponseTime, result.StatusCode)

	// Broadcast to WebSocket clients
	s.hub.Broadcast(result)

	  // Publish to Redis stream for analytics
			s.publishToStream(result)
		}
	}

	return nil
}

func (s *Service) checkEndpoint(endpoint models.Endpoint) models.MonitoringResult {
	client := &http.Client{
		Timeout: time.Duration(endpoint.Timeout) * time.Millisecond,
	}

	req, err := http.NewRequest("GET", endpoint.URL, nil)
	if err != nil {
		errorMsg := err.Error()
		return models.MonitoringResult{
			EndpointID:   endpoint.ID,
			ResponseTime: 0,
			StatusCode:   nil,
			ErrorMessage: &errorMsg,
			CheckedAt:    time.Now(),
		}
	}

	// Add headers if any
	if len(endpoint.Headers) > 0 {
		var headers map[string]string
		json.Unmarshal(endpoint.Headers, &headers)
		for key, value := range headers {
			req.Header.Set(key, value)
		}
	}

	start := time.Now()
	resp, err := client.Do(req)
	responseTime := int(time.Since(start).Milliseconds())

	if err != nil {
		errorMsg := err.Error()
		return models.MonitoringResult{
			EndpointID:   endpoint.ID,
			ResponseTime: responseTime,
			StatusCode:   nil,
			ErrorMessage: &errorMsg,
			CheckedAt:    time.Now(),
		}
	}
	defer resp.Body.Close()

	return models.MonitoringResult{
	EndpointID:   endpoint.ID,
	ResponseTime: responseTime,
	StatusCode:   &resp.StatusCode,
	ErrorMessage: nil,
	CheckedAt:    time.Now(),
	}
}

func (s *Service) sendAlertNotification(alertID int, message string) {
	symfonyUrl := os.Getenv("SYMFONY_API_URL")
	if symfonyUrl == "" {
		symfonyUrl = "http://symfony:8000"
	}

	payload := map[string]interface{}{
		"alert_id": alertID,
		"message":  message,
	}

	jsonData, err := json.Marshal(payload)
	if err != nil {
		log.Printf("Error marshaling alert notification: %v", err)
		return
	}

	resp, err := s.client.Post(symfonyUrl+"/api/alerts/trigger", "application/json", bytes.NewBuffer(jsonData))
	if err != nil {
		log.Printf("Error sending alert notification: %v", err)
		return
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		log.Printf("Alert notification failed with status: %d", resp.StatusCode)
	}
}

func (s *Service) publishToStream(result models.MonitoringResult) {
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

	// Publish to Redis stream
	err := s.rdb.XAdd(s.ctx, &redis.XAddArgs{
		Stream: "api-metrics",
		ID:     "*",
		Values: streamData,
	}).Err()

	if err != nil {
		log.Printf("Error publishing to Redis stream: %v", err)
	}
}

func (s *Service) evaluateAlerts(result models.MonitoringResult, alerts []models.Alert) {
	for _, alert := range alerts {
		triggered := false
		message := ""

		switch alert.AlertType {
		case "response_time":
			var threshold int
			json.Unmarshal(alert.Threshold, &threshold)
			if result.ResponseTime > threshold {
				triggered = true
				message = fmt.Sprintf("Response time %dms exceeded threshold %dms", result.ResponseTime, threshold)
			}
		case "status_code":
			var config map[string]interface{}
			json.Unmarshal(alert.Threshold, &config)
			expectedCodes, _ := config["expected_codes"].([]interface{})
			if result.StatusCode == nil {
				if config["alert_on_failure"].(bool) {
					triggered = true
					message = "Endpoint failed to respond"
				}
			} else {
				found := false
				for _, code := range expectedCodes {
					if int(code.(float64)) == *result.StatusCode {
						found = true
						break
					}
				}
				if !found {
					triggered = true
					message = fmt.Sprintf("Status code %d not in expected codes", *result.StatusCode)
				}
			}
		case "availability":
			if result.ErrorMessage != nil {
				triggered = true
				message = "Endpoint is down"
			}
		}

		if triggered {
		log.Printf("Alert triggered for endpoint %d: %s", result.EndpointID, message)
		// Send notification via Symfony API
		 s.sendAlertNotification(alert.ID, message)
		}
	}
}
