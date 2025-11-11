package models

import (
	"encoding/json"
	"testing"
	"time"
)

func TestEndpointJSON(t *testing.T) {
	headers := json.RawMessage(`{"Authorization": "Bearer token"}`)
	endpoint := Endpoint{
		ID:            1,
		UserID:        10,
		URL:           "https://api.example.com",
		CheckInterval: 300,
		Timeout:       5000,
		Headers:       headers,
		IsActive:      true,
	}

	data, err := json.Marshal(endpoint)
	if err != nil {
		t.Fatalf("failed to marshal endpoint: %v", err)
	}

	var unmarshaled Endpoint
	err = json.Unmarshal(data, &unmarshaled)
	if err != nil {
		t.Fatalf("failed to unmarshal endpoint: %v", err)
	}

	if unmarshaled.ID != endpoint.ID {
		t.Errorf("expected ID %d, got %d", endpoint.ID, unmarshaled.ID)
	}
	if unmarshaled.URL != endpoint.URL {
		t.Errorf("expected URL %s, got %s", endpoint.URL, unmarshaled.URL)
	}
	if unmarshaled.IsActive != endpoint.IsActive {
		t.Errorf("expected IsActive %v, got %v", endpoint.IsActive, unmarshaled.IsActive)
	}
}

func TestMonitoringResultJSON(t *testing.T) {
	statusCode := 200
	result := MonitoringResult{
		EndpointID:   1,
		ResponseTime: 145,
		StatusCode:   &statusCode,
		ErrorMessage: nil,
		CheckedAt:    time.Now(),
	}

	data, err := json.Marshal(result)
	if err != nil {
		t.Fatalf("failed to marshal result: %v", err)
	}

	var unmarshaled MonitoringResult
	err = json.Unmarshal(data, &unmarshaled)
	if err != nil {
		t.Fatalf("failed to unmarshal result: %v", err)
	}

	if unmarshaled.EndpointID != result.EndpointID {
		t.Errorf("expected EndpointID %d, got %d", result.EndpointID, unmarshaled.EndpointID)
	}
	if unmarshaled.ResponseTime != result.ResponseTime {
		t.Errorf("expected ResponseTime %d, got %d", result.ResponseTime, unmarshaled.ResponseTime)
	}
	if *unmarshaled.StatusCode != *result.StatusCode {
		t.Errorf("expected StatusCode %d, got %d", *result.StatusCode, *unmarshaled.StatusCode)
	}
	if unmarshaled.ErrorMessage != nil {
		t.Errorf("expected ErrorMessage nil, got %v", unmarshaled.ErrorMessage)
	}
}

func TestMonitoringResultWithError(t *testing.T) {
	errMsg := "connection timeout"
	result := MonitoringResult{
		EndpointID:   1,
		ResponseTime: 5000,
		StatusCode:   nil,
		ErrorMessage: &errMsg,
		CheckedAt:    time.Now(),
	}

	data, err := json.Marshal(result)
	if err != nil {
		t.Fatalf("failed to marshal result: %v", err)
	}

	var unmarshaled MonitoringResult
	err = json.Unmarshal(data, &unmarshaled)
	if err != nil {
		t.Fatalf("failed to unmarshal result: %v", err)
	}

	if unmarshaled.StatusCode != nil {
		t.Errorf("expected StatusCode nil, got %v", unmarshaled.StatusCode)
	}
	if *unmarshaled.ErrorMessage != errMsg {
		t.Errorf("expected ErrorMessage %s, got %s", errMsg, *unmarshaled.ErrorMessage)
	}
}

func TestAlertJSON(t *testing.T) {
	threshold := json.RawMessage(`{"threshold_ms": 5000}`)
	alert := Alert{
		ID:         1,
		UserID:     10,
		EndpointID: 1,
		AlertType:  "response_time",
		Threshold:  threshold,
		IsActive:   true,
	}

	data, err := json.Marshal(alert)
	if err != nil {
		t.Fatalf("failed to marshal alert: %v", err)
	}

	var unmarshaled Alert
	err = json.Unmarshal(data, &unmarshaled)
	if err != nil {
		t.Fatalf("failed to unmarshal alert: %v", err)
	}

	if unmarshaled.ID != alert.ID {
		t.Errorf("expected ID %d, got %d", alert.ID, unmarshaled.ID)
	}
	if unmarshaled.AlertType != alert.AlertType {
		t.Errorf("expected AlertType %s, got %s", alert.AlertType, unmarshaled.AlertType)
	}
	if unmarshaled.IsActive != alert.IsActive {
		t.Errorf("expected IsActive %v, got %v", alert.IsActive, unmarshaled.IsActive)
	}
}

func TestAlertTypes(t *testing.T) {
	alertTypes := []string{
		"response_time",
		"status_code",
		"availability",
	}

	for _, alertType := range alertTypes {
		alert := Alert{
			ID:        1,
			AlertType: alertType,
			IsActive:  true,
		}

		if alert.AlertType != alertType {
			t.Errorf("expected AlertType %s, got %s", alertType, alert.AlertType)
		}
	}
}

func TestEndpointDefaults(t *testing.T) {
	endpoint := Endpoint{
		ID:      1,
		UserID:  10,
		URL:     "http://example.com",
		Timeout: 0,
	}

	if endpoint.Timeout != 0 {
		t.Errorf("expected default Timeout 0, got %d", endpoint.Timeout)
	}

	if endpoint.CheckInterval != 0 {
		t.Errorf("expected default CheckInterval 0, got %d", endpoint.CheckInterval)
	}
}

func TestMonitoringResultTimestamp(t *testing.T) {
	now := time.Now()
	result := MonitoringResult{
		EndpointID:   1,
		ResponseTime: 100,
		CheckedAt:    now,
	}

	if !result.CheckedAt.Equal(now) {
		t.Errorf("expected CheckedAt %v, got %v", now, result.CheckedAt)
	}
}

func TestEndpointWithEmptyHeaders(t *testing.T) {
	endpoint := Endpoint{
		ID:      1,
		URL:     "http://example.com",
		Headers: json.RawMessage(`{}`),
	}

	var headerMap map[string]string
	err := json.Unmarshal(endpoint.Headers, &headerMap)
	if err != nil {
		t.Fatalf("failed to unmarshal headers: %v", err)
	}

	if len(headerMap) != 0 {
		t.Errorf("expected empty headers map, got %d items", len(headerMap))
	}
}

func TestAlertThresholdVariations(t *testing.T) {
	tests := []struct {
		name      string
		threshold interface{}
	}{
		{
			name:      "integer threshold",
			threshold: 5000,
		},
		{
			name:      "object threshold",
			threshold: map[string]interface{}{"value": 5000, "unit": "ms"},
		},
		{
			name:      "string threshold",
			threshold: "5000ms",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			data, err := json.Marshal(tt.threshold)
			if err != nil {
				t.Fatalf("failed to marshal threshold: %v", err)
			}

			alert := Alert{
				ID:        1,
				Threshold: data,
			}

			if alert.Threshold == nil {
				t.Errorf("expected non-nil Threshold")
			}
		})
	}
}
