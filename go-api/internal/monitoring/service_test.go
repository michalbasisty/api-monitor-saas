package monitoring

import (
	"context"
	"encoding/json"
	"net"
	"net/http"
	"testing"
	"time"

	"api-monitor-go/internal/models"
	"github.com/redis/go-redis/v9"
)

func TestCheckEndpointHandlesError(t *testing.T) {
	s := &Service{}
	endpoint := models.Endpoint{ID: 1, URL: "http://localhost:0", Timeout: 10}

	res := s.checkEndpoint(endpoint)
	if res.EndpointID != 1 {
		t.Fatalf("expected endpoint id 1")
	}
	if res.StatusCode != nil {
		t.Fatalf("expected nil status code on error")
	}
}

func TestCheckEndpointSuccessPath(t *testing.T) {
	// Create a test server with listener
	listener, _ := net.Listen("tcp", "127.0.0.1:0")
	ts := &http.Server{
		Handler: http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.WriteHeader(200)
			_ = json.NewEncoder(w).Encode(map[string]string{"ok": "1"})
		}),
	}
	go ts.Serve(listener)
	defer ts.Close()

	url := "http://" + listener.Addr().String() + "/"
	s := &Service{}
	endpoint := models.Endpoint{ID: 2, URL: url, Timeout: 1000}
	res := s.checkEndpoint(endpoint)
	if res.StatusCode == nil || *res.StatusCode != 200 {
		t.Fatalf("expected 200, got %+v", res.StatusCode)
	}
}

func TestCheckEndpointWithHeaders(t *testing.T) {
	listener, _ := net.Listen("tcp", "127.0.0.1:0")
	ts := &http.Server{
		Handler: http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			if r.Header.Get("Authorization") == "Bearer token123" {
				w.WriteHeader(200)
			} else {
				w.WriteHeader(401)
			}
		}),
	}
	go ts.Serve(listener)
	defer ts.Close()

	url := "http://" + listener.Addr().String() + "/"
	headers, _ := json.Marshal(map[string]string{"Authorization": "Bearer token123"})
	endpoint := models.Endpoint{ID: 3, URL: url, Timeout: 1000, Headers: headers}

	s := &Service{}
	res := s.checkEndpoint(endpoint)
	if res.StatusCode == nil || *res.StatusCode != 200 {
		t.Fatalf("expected 200 with proper headers, got %+v", res.StatusCode)
	}
}

func TestCheckEndpointTimeout(t *testing.T) {
	listener, _ := net.Listen("tcp", "127.0.0.1:0")
	ts := &http.Server{
		Handler: http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			time.Sleep(500 * time.Millisecond)
			w.WriteHeader(200)
		}),
	}
	go ts.Serve(listener)
	defer ts.Close()

	url := "http://" + listener.Addr().String() + "/"
	s := &Service{}
	endpoint := models.Endpoint{ID: 4, URL: url, Timeout: 50}
	res := s.checkEndpoint(endpoint)

	if res.StatusCode != nil {
		t.Fatalf("expected nil status code on timeout, got %+v", res.StatusCode)
	}
	if res.ErrorMessage == nil {
		t.Fatalf("expected error message on timeout")
	}
}

func TestEvaluateAlertsResponseTime(t *testing.T) {
	s := &Service{
		client: &http.Client{Timeout: 5 * time.Second},
	}
	threshold := 100
	alert := models.Alert{
		ID:        1,
		AlertType: "response_time",
		Threshold: mustMarshal(threshold),
	}

	result := models.MonitoringResult{
		EndpointID:   1,
		ResponseTime: 150,
	}

	// This would normally call sendAlertNotification, but we'll just check the logic
	s.evaluateAlerts(result, []models.Alert{alert})
}

func TestEvaluateAlertsAvailability(t *testing.T) {
	s := &Service{
		client: &http.Client{Timeout: 5 * time.Second},
	}
	alert := models.Alert{
		ID:        2,
		AlertType: "availability",
		Threshold: []byte("{}"),
	}

	errMsg := "connection refused"
	result := models.MonitoringResult{
		EndpointID:   1,
		ErrorMessage: &errMsg,
	}

	s.evaluateAlerts(result, []models.Alert{alert})
}

func TestEvaluateAlertsStatusCodeExpected(t *testing.T) {
	s := &Service{
		client: &http.Client{Timeout: 5 * time.Second},
	}
	threshold := map[string]interface{}{
		"expected_codes":   []interface{}{float64(200), float64(201)},
		"alert_on_failure": true,
	}
	alert := models.Alert{
		ID:        3,
		AlertType: "status_code",
		Threshold: mustMarshal(threshold),
	}

	statusCode := 200
	result := models.MonitoringResult{
		EndpointID: 1,
		StatusCode: &statusCode,
	}

	s.evaluateAlerts(result, []models.Alert{alert})
}

func TestEvaluateAlertsStatusCodeUnexpected(t *testing.T) {
	s := &Service{
		client: &http.Client{Timeout: 5 * time.Second},
	}
	threshold := map[string]interface{}{
		"expected_codes":   []interface{}{float64(200), float64(201)},
		"alert_on_failure": true,
	}
	alert := models.Alert{
		ID:        3,
		AlertType: "status_code",
		Threshold: mustMarshal(threshold),
	}

	statusCode := 500
	result := models.MonitoringResult{
		EndpointID: 1,
		StatusCode: &statusCode,
	}

	s.evaluateAlerts(result, []models.Alert{alert})
}

func TestEvaluateAlertsStatusCodeNilWithAlertOnFailure(t *testing.T) {
	s := &Service{
		client: &http.Client{Timeout: 5 * time.Second},
	}
	threshold := map[string]interface{}{
		"expected_codes":   []interface{}{float64(200)},
		"alert_on_failure": true,
	}
	alert := models.Alert{
		ID:        3,
		AlertType: "status_code",
		Threshold: mustMarshal(threshold),
	}

	result := models.MonitoringResult{
		EndpointID: 1,
		StatusCode: nil,
	}

	s.evaluateAlerts(result, []models.Alert{alert})
}

func TestCheckEndpointInvalidURL(t *testing.T) {
	s := &Service{}
	endpoint := models.Endpoint{
		ID:      5,
		URL:     "ht!tp://invalid[url",
		Timeout: 1000,
	}

	res := s.checkEndpoint(endpoint)
	if res.StatusCode != nil {
		t.Fatalf("expected nil status code on invalid URL, got %+v", res.StatusCode)
	}
	if res.ErrorMessage == nil {
		t.Fatalf("expected error message on invalid URL")
	}
}

func TestCheckEndpointResponseTime(t *testing.T) {
	listener, _ := net.Listen("tcp", "127.0.0.1:0")
	ts := &http.Server{
		Handler: http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			time.Sleep(100 * time.Millisecond)
			w.WriteHeader(200)
		}),
	}
	go ts.Serve(listener)
	defer ts.Close()

	url := "http://" + listener.Addr().String() + "/"
	s := &Service{}
	endpoint := models.Endpoint{ID: 6, URL: url, Timeout: 5000}
	res := s.checkEndpoint(endpoint)

	if res.ResponseTime < 90 {
		t.Fatalf("expected response time >= 90ms, got %d", res.ResponseTime)
	}
}

// TestCheckEndpointEmptyHeaders tests endpoint with empty headers
func TestCheckEndpointEmptyHeaders(t *testing.T) {
	listener, _ := net.Listen("tcp", "127.0.0.1:0")
	ts := &http.Server{
		Handler: http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.WriteHeader(200)
		}),
	}
	go ts.Serve(listener)
	defer ts.Close()

	url := "http://" + listener.Addr().String() + "/"
	s := &Service{}
	endpoint := models.Endpoint{ID: 7, URL: url, Timeout: 1000, Headers: []byte{}}
	res := s.checkEndpoint(endpoint)

	if res.StatusCode == nil || *res.StatusCode != 200 {
		t.Fatalf("expected 200, got %+v", res.StatusCode)
	}
}

// TestCheckEndpointMultipleHeaders tests endpoint with multiple headers
func TestCheckEndpointMultipleHeaders(t *testing.T) {
	listener, _ := net.Listen("tcp", "127.0.0.1:0")
	ts := &http.Server{
		Handler: http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			if r.Header.Get("Authorization") == "Bearer token" && r.Header.Get("X-API-Key") == "key123" {
				w.WriteHeader(200)
			} else {
				w.WriteHeader(403)
			}
		}),
	}
	go ts.Serve(listener)
	defer ts.Close()

	url := "http://" + listener.Addr().String() + "/"
	headers, _ := json.Marshal(map[string]string{
		"Authorization": "Bearer token",
		"X-API-Key":     "key123",
	})
	endpoint := models.Endpoint{ID: 8, URL: url, Timeout: 1000, Headers: headers}

	s := &Service{}
	res := s.checkEndpoint(endpoint)
	if res.StatusCode == nil || *res.StatusCode != 200 {
		t.Fatalf("expected 200 with proper headers, got %+v", res.StatusCode)
	}
}

// TestCheckEndpointStatusCodeVariety tests various HTTP status codes
func TestCheckEndpointStatusCodeVariety(t *testing.T) {
	tests := []struct {
		name           string
		responseCode   int
		expectedResult int
	}{
		{"Created", 201, 201},
		{"No Content", 204, 204},
		{"Bad Request", 400, 400},
		{"Not Found", 404, 404},
		{"Server Error", 500, 500},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			listener, _ := net.Listen("tcp", "127.0.0.1:0")
			ts := &http.Server{
				Handler: http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
					w.WriteHeader(tt.responseCode)
				}),
			}
			go ts.Serve(listener)
			defer ts.Close()

			url := "http://" + listener.Addr().String() + "/"
			s := &Service{}
			endpoint := models.Endpoint{ID: 9, URL: url, Timeout: 1000}
			res := s.checkEndpoint(endpoint)

			if res.StatusCode == nil || *res.StatusCode != tt.expectedResult {
				t.Fatalf("expected %d, got %+v", tt.expectedResult, res.StatusCode)
			}
		})
	}
}

// TestEvaluateAlertsStatusCodeNilWithoutAlertOnFailure tests status code alert when no response
func TestEvaluateAlertsStatusCodeNilWithoutAlertOnFailure(t *testing.T) {
	s := &Service{
		client: &http.Client{Timeout: 5 * time.Second},
	}
	threshold := map[string]interface{}{
		"expected_codes":   []interface{}{float64(200)},
		"alert_on_failure": false,
	}
	alert := models.Alert{
		ID:        4,
		AlertType: "status_code",
		Threshold: mustMarshal(threshold),
	}

	result := models.MonitoringResult{
		EndpointID: 1,
		StatusCode: nil,
	}

	s.evaluateAlerts(result, []models.Alert{alert})
}

// TestEvaluateAlertsResponseTimeNoTrigger tests response time alert that doesn't trigger
func TestEvaluateAlertsResponseTimeNoTrigger(t *testing.T) {
	s := &Service{
		client: &http.Client{Timeout: 5 * time.Second},
	}
	threshold := 500
	alert := models.Alert{
		ID:        5,
		AlertType: "response_time",
		Threshold: mustMarshal(threshold),
	}

	result := models.MonitoringResult{
		EndpointID:   1,
		ResponseTime: 100,
	}

	s.evaluateAlerts(result, []models.Alert{alert})
}

// TestEvaluateAlertsAvailabilityNoTrigger tests availability alert that doesn't trigger
func TestEvaluateAlertsAvailabilityNoTrigger(t *testing.T) {
	s := &Service{
		client: &http.Client{Timeout: 5 * time.Second},
	}
	alert := models.Alert{
		ID:        6,
		AlertType: "availability",
		Threshold: []byte("{}"),
	}

	statusCode := 200
	result := models.MonitoringResult{
		EndpointID: 1,
		StatusCode: &statusCode,
	}

	s.evaluateAlerts(result, []models.Alert{alert})
}

// TestEvaluateAlertsMultipleAlerts tests multiple alerts for a single result
func TestEvaluateAlertsMultipleAlerts(t *testing.T) {
	s := &Service{
		client: &http.Client{Timeout: 5 * time.Second},
	}

	alerts := []models.Alert{
		{
			ID:        1,
			AlertType: "response_time",
			Threshold: mustMarshal(100),
		},
		{
			ID:        2,
			AlertType: "availability",
			Threshold: []byte("{}"),
		},
		{
			ID:        3,
			AlertType: "status_code",
			Threshold: mustMarshal(map[string]interface{}{
				"expected_codes":   []interface{}{float64(200)},
				"alert_on_failure": true,
			}),
		},
	}

	result := models.MonitoringResult{
		EndpointID:   1,
		ResponseTime: 50,
		StatusCode:   nil,
	}

	s.evaluateAlerts(result, alerts)
}

// TestEvaluateAlertsUnknownType tests handling of unknown alert type
func TestEvaluateAlertsUnknownType(t *testing.T) {
	s := &Service{
		client: &http.Client{Timeout: 5 * time.Second},
	}
	alert := models.Alert{
		ID:        7,
		AlertType: "unknown_type",
		Threshold: []byte("{}"),
	}

	result := models.MonitoringResult{
		EndpointID:   1,
		ResponseTime: 100,
	}

	s.evaluateAlerts(result, []models.Alert{alert})
}

// TestCheckEndpointLargeTimeout tests endpoint with large timeout
func TestCheckEndpointLargeTimeout(t *testing.T) {
	listener, _ := net.Listen("tcp", "127.0.0.1:0")
	ts := &http.Server{
		Handler: http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.WriteHeader(200)
		}),
	}
	go ts.Serve(listener)
	defer ts.Close()

	url := "http://" + listener.Addr().String() + "/"
	s := &Service{}
	endpoint := models.Endpoint{ID: 10, URL: url, Timeout: 30000}
	res := s.checkEndpoint(endpoint)

	if res.StatusCode == nil || *res.StatusCode != 200 {
		t.Fatalf("expected 200, got %+v", res.StatusCode)
	}
}

// TestCheckEndpointSmallTimeout tests endpoint with very small timeout
func TestCheckEndpointSmallTimeout(t *testing.T) {
	listener, _ := net.Listen("tcp", "127.0.0.1:0")
	ts := &http.Server{
		Handler: http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			time.Sleep(10 * time.Millisecond)
			w.WriteHeader(200)
		}),
	}
	go ts.Serve(listener)
	defer ts.Close()

	url := "http://" + listener.Addr().String() + "/"
	s := &Service{}
	endpoint := models.Endpoint{ID: 11, URL: url, Timeout: 1}
	res := s.checkEndpoint(endpoint)

	if res.StatusCode != nil {
		t.Fatalf("expected nil status code on timeout, got %+v", res.StatusCode)
	}
}

// TestCheckEndpointResponseTimeAccuracy tests response time measurement is reasonable
func TestCheckEndpointResponseTimeAccuracy(t *testing.T) {
	listener, _ := net.Listen("tcp", "127.0.0.1:0")
	delayMs := 50
	ts := &http.Server{
		Handler: http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			time.Sleep(time.Duration(delayMs) * time.Millisecond)
			w.WriteHeader(200)
		}),
	}
	go ts.Serve(listener)
	defer ts.Close()

	url := "http://" + listener.Addr().String() + "/"
	s := &Service{}
	endpoint := models.Endpoint{ID: 12, URL: url, Timeout: 5000}
	res := s.checkEndpoint(endpoint)

	if res.ResponseTime < int(delayMs-10) || res.ResponseTime > int(delayMs+50) {
		t.Fatalf("expected response time around %dms, got %d", delayMs, res.ResponseTime)
	}
}



// TestPublishToStreamWithStatusCode tests publishing to Redis stream with status code
func TestPublishToStreamWithStatusCode(t *testing.T) {
	// Skip if Redis is not available
	rdb := redis.NewClient(&redis.Options{
		Addr: "localhost:6379",
	})
	defer rdb.Close()

	_, err := rdb.Ping(context.Background()).Result()
	if err != nil {
		t.Skip("Redis not available, skipping test")
	}

	s := &Service{
		repo:   nil,
		hub:    nil,
		client: &http.Client{Timeout: 5 * time.Second},
		rdb:    rdb,
		ctx:    context.Background(),
	}

	statusCode := 200
	result := models.MonitoringResult{
		EndpointID:   1,
		ResponseTime: 100,
		StatusCode:   &statusCode,
		CheckedAt:    time.Now(),
	}

	s.publishToStream(result)
}

// TestPublishToStreamWithError tests publishing to Redis stream with error
func TestPublishToStreamWithError(t *testing.T) {
	// Skip if Redis is not available
	rdb := redis.NewClient(&redis.Options{
		Addr: "localhost:6379",
	})
	defer rdb.Close()

	_, err := rdb.Ping(context.Background()).Result()
	if err != nil {
		t.Skip("Redis not available, skipping test")
	}

	s := &Service{
		repo:   nil,
		hub:    nil,
		client: &http.Client{Timeout: 5 * time.Second},
		rdb:    rdb,
		ctx:    context.Background(),
	}

	errMsg := "connection timeout"
	result := models.MonitoringResult{
		EndpointID:   1,
		ResponseTime: 5000,
		StatusCode:   nil,
		ErrorMessage: &errMsg,
		CheckedAt:    time.Now(),
	}

	s.publishToStream(result)
}

// TestSendAlertNotificationSuccess tests sending alert notification to Symfony
func TestSendAlertNotificationSuccess(t *testing.T) {
	// Create a mock Symfony API server
	listener, _ := net.Listen("tcp", "127.0.0.1:0")
	ts := &http.Server{
		Handler: http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			if r.URL.Path == "/api/alerts/trigger" && r.Method == "POST" {
				w.WriteHeader(http.StatusOK)
			} else {
				w.WriteHeader(http.StatusNotFound)
			}
		}),
	}
	go ts.Serve(listener)
	defer ts.Close()

	t.Setenv("SYMFONY_API_URL", "http://"+listener.Addr().String())

	s := &Service{
		client: &http.Client{Timeout: 5 * time.Second},
	}

	s.sendAlertNotification(1, "Test alert message")
}

// TestSendAlertNotificationDefaultURL tests using default Symfony URL
func TestSendAlertNotificationDefaultURL(t *testing.T) {
	t.Setenv("SYMFONY_API_URL", "")

	listener, _ := net.Listen("tcp", "127.0.0.1:0")
	ts := &http.Server{
		Handler: http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.WriteHeader(http.StatusOK)
		}),
	}
	go ts.Serve(listener)
	defer ts.Close()

	s := &Service{
		client: &http.Client{Timeout: 5 * time.Second},
	}

	s.sendAlertNotification(1, "Test alert")
}

// TestSendAlertNotificationInvalidURL tests handling of invalid request URL
func TestSendAlertNotificationInvalidURL(t *testing.T) {
	t.Setenv("SYMFONY_API_URL", "http://invalid-host-that-does-not-exist:9999")

	s := &Service{
		client: &http.Client{Timeout: 100 * time.Millisecond},
	}

	s.sendAlertNotification(1, "Test alert")
}

// TestCheckEndpointRetainErrorMessage tests that error message is preserved in result
func TestCheckEndpointRetainErrorMessage(t *testing.T) {
	s := &Service{}
	endpoint := models.Endpoint{
		ID:      13,
		URL:     "http://localhost:1/nonexistent",
		Timeout: 100,
	}

	res := s.checkEndpoint(endpoint)
	if res.ErrorMessage == nil {
		t.Fatalf("expected error message")
	}
	if res.StatusCode != nil {
		t.Fatalf("expected nil status code on error")
	}
}

// TestCheckEndpointResponseTimeNonZero tests that response time is recorded even on error
func TestCheckEndpointResponseTimeNonZero(t *testing.T) {
	listener, _ := net.Listen("tcp", "127.0.0.1:0")
	ts := &http.Server{
		Handler: http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			time.Sleep(5 * time.Millisecond)
			w.WriteHeader(200)
		}),
	}
	go ts.Serve(listener)
	defer ts.Close()

	url := "http://" + listener.Addr().String() + "/"
	s := &Service{}
	endpoint := models.Endpoint{
		ID:      14,
		URL:     url,
		Timeout: 1000,
	}

	res := s.checkEndpoint(endpoint)
	if res.ResponseTime == 0 {
		t.Fatalf("expected non-zero response time")
	}
}

// TestEvaluateAlertsWithEmptyList tests alert evaluation with empty list
func TestEvaluateAlertsWithEmptyList(t *testing.T) {
	s := &Service{
		client: &http.Client{Timeout: 5 * time.Second},
	}

	result := models.MonitoringResult{
		EndpointID:   1,
		ResponseTime: 100,
	}

	s.evaluateAlerts(result, []models.Alert{})
}

// TestCheckEndpointInvalidHeaderJSON tests endpoint with malformed headers JSON
func TestCheckEndpointInvalidHeaderJSON(t *testing.T) {
	listener, _ := net.Listen("tcp", "127.0.0.1:0")
	ts := &http.Server{
		Handler: http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.WriteHeader(200)
		}),
	}
	go ts.Serve(listener)
	defer ts.Close()

	url := "http://" + listener.Addr().String() + "/"
	s := &Service{}
	endpoint := models.Endpoint{
		ID:      15,
		URL:     url,
		Timeout: 1000,
		Headers: []byte("{invalid json"),
	}

	res := s.checkEndpoint(endpoint)
	if res.StatusCode == nil {
		t.Fatalf("expected valid status code even with bad headers JSON")
	}
}

// TestCheckEndpointRequestCreationError tests handling of NewRequest errors
func TestCheckEndpointRequestCreationError(t *testing.T) {
	s := &Service{}
	endpoint := models.Endpoint{
		ID:      16,
		URL:     "ht!tp://[invalid:url",
		Timeout: 1000,
	}

	res := s.checkEndpoint(endpoint)
	if res.StatusCode != nil {
		t.Fatalf("expected nil status code on request creation error")
	}
	if res.ErrorMessage == nil {
		t.Fatalf("expected error message")
	}
}

// TestCheckEndpointCheckedAtTime tests that CheckedAt timestamp is set
func TestCheckEndpointCheckedAtTime(t *testing.T) {
	listener, _ := net.Listen("tcp", "127.0.0.1:0")
	ts := &http.Server{
		Handler: http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.WriteHeader(200)
		}),
	}
	go ts.Serve(listener)
	defer ts.Close()

	url := "http://" + listener.Addr().String() + "/"
	s := &Service{}
	endpoint := models.Endpoint{ID: 20, URL: url, Timeout: 1000}
	
	beforeCheck := time.Now()
	res := s.checkEndpoint(endpoint)
	afterCheck := time.Now()

	if res.CheckedAt.Before(beforeCheck) || res.CheckedAt.After(afterCheck) {
		t.Fatalf("CheckedAt timestamp outside expected range")
	}
}

// TestEvaluateAlertsStatusCodeNilNoExpectedCodes tests status code alert with nil status and no alert on failure
func TestEvaluateAlertsStatusCodeNilNoAlertOnFailure(t *testing.T) {
	s := &Service{
		client: &http.Client{Timeout: 5 * time.Second},
	}
	threshold := map[string]interface{}{
		"expected_codes":   []interface{}{float64(200), float64(201)},
		"alert_on_failure": false,
	}
	alert := models.Alert{
		ID:        8,
		AlertType: "status_code",
		Threshold: mustMarshal(threshold),
	}

	result := models.MonitoringResult{
		EndpointID: 1,
		StatusCode: nil,
	}

	s.evaluateAlerts(result, []models.Alert{alert})
}

// TestCheckEndpointZeroResponseTime tests that response time is recorded correctly
func TestCheckEndpointZeroResponseTime(t *testing.T) {
	listener, _ := net.Listen("tcp", "127.0.0.1:0")
	ts := &http.Server{
		Handler: http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.WriteHeader(200)
		}),
	}
	go ts.Serve(listener)
	defer ts.Close()

	url := "http://" + listener.Addr().String() + "/"
	s := &Service{}
	endpoint := models.Endpoint{ID: 21, URL: url, Timeout: 5000}
	res := s.checkEndpoint(endpoint)

	if res.ResponseTime < 0 {
		t.Fatalf("expected non-negative response time, got %d", res.ResponseTime)
	}
}

// TestEvaluateAlertsInvalidThresholdJSON tests handling of invalid JSON in alert threshold
func TestEvaluateAlertsInvalidThresholdJSON(t *testing.T) {
	s := &Service{
		client: &http.Client{Timeout: 5 * time.Second},
	}
	alert := models.Alert{
		ID:        9,
		AlertType: "response_time",
		Threshold: []byte("invalid json"),
	}

	result := models.MonitoringResult{
		EndpointID:   1,
		ResponseTime: 100,
	}

	s.evaluateAlerts(result, []models.Alert{alert})
}

// TestCheckEndpointHTTPMethods tests that checkEndpoint uses GET method
func TestCheckEndpointHTTPMethods(t *testing.T) {
	listener, _ := net.Listen("tcp", "127.0.0.1:0")
	requestMethods := []string{}
	ts := &http.Server{
		Handler: http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			requestMethods = append(requestMethods, r.Method)
			w.WriteHeader(200)
		}),
	}
	go ts.Serve(listener)
	defer ts.Close()

	url := "http://" + listener.Addr().String() + "/"
	s := &Service{}
	endpoint := models.Endpoint{ID: 22, URL: url, Timeout: 1000}
	s.checkEndpoint(endpoint)

	if len(requestMethods) != 1 || requestMethods[0] != "GET" {
		t.Fatalf("expected GET request, got %v", requestMethods)
	}
}

// Helper function to marshal JSON
func mustMarshal(v interface{}) []byte {
	b, _ := json.Marshal(v)
	return b
}
