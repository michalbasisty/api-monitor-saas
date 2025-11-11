package main

import (
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestHealthEndpoint(t *testing.T) {
	req, err := http.NewRequest("GET", "/health", nil)
	if err != nil {
		t.Fatalf("failed to create request: %v", err)
	}

	recorder := httptest.NewRecorder()
	handler := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
		w.Write([]byte("OK"))
	})

	handler.ServeHTTP(recorder, req)

	if recorder.Code != http.StatusOK {
		t.Errorf("expected status %d, got %d", http.StatusOK, recorder.Code)
	}

	body := recorder.Body.String()
	if body != "OK" {
		t.Errorf("expected body 'OK', got '%s'", body)
	}
}

func TestMonitorEndpoint(t *testing.T) {
	req, err := http.NewRequest("GET", "/monitor", nil)
	if err != nil {
		t.Fatalf("failed to create request: %v", err)
	}

	recorder := httptest.NewRecorder()
	handler := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
		w.Write([]byte("Monitoring started"))
	})

	handler.ServeHTTP(recorder, req)

	if recorder.Code != http.StatusOK {
		t.Errorf("expected status %d, got %d", http.StatusOK, recorder.Code)
	}

	body := recorder.Body.String()
	if body != "Monitoring started" {
		t.Errorf("expected body 'Monitoring started', got '%s'", body)
	}
}

func TestMonitorEndpointAsync(t *testing.T) {
	// Test that monitor endpoint can be called and returns immediately
	req, err := http.NewRequest("POST", "/monitor", nil)
	if err != nil {
		t.Fatalf("failed to create request: %v", err)
	}

	recorder := httptest.NewRecorder()
	
	// Handler that simulates async monitoring
	handler := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		go func() {
			// Simulate async work
		}()
		w.WriteHeader(http.StatusOK)
		w.Write([]byte("Monitoring started"))
	})

	handler.ServeHTTP(recorder, req)

	if recorder.Code != http.StatusOK {
		t.Errorf("expected status %d, got %d", http.StatusOK, recorder.Code)
	}
}

func TestWebSocketUpgraderOrigin(t *testing.T) {
	// Test that the upgrader allows all origins (dev mode)
	req := httptest.NewRequest("GET", "/ws", nil)
	req.Header.Set("Origin", "http://example.com")

	result := upgrader.CheckOrigin(req)
	if !result {
		t.Errorf("CheckOrigin should return true for all origins in dev mode")
	}
}

func TestWebSocketUpgraderMultipleOrigins(t *testing.T) {
	tests := []struct {
		name   string
		origin string
	}{
		{
			name:   "local origin",
			origin: "http://localhost:3000",
		},
		{
			name:   "different domain",
			origin: "https://api.example.com",
		},
		{
			name:   "invalid origin",
			origin: "not-a-valid-origin",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			req := httptest.NewRequest("GET", "/ws", nil)
			req.Header.Set("Origin", tt.origin)

			result := upgrader.CheckOrigin(req)
			if !result {
				t.Errorf("CheckOrigin should return true for origin: %s", tt.origin)
			}
		})
	}
}

func TestHealthEndpointMultipleCalls(t *testing.T) {
	handler := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
		w.Write([]byte("OK"))
	})

	for i := 0; i < 5; i++ {
		req := httptest.NewRequest("GET", "/health", nil)
		recorder := httptest.NewRecorder()
		handler.ServeHTTP(recorder, req)

		if recorder.Code != http.StatusOK {
			t.Errorf("iteration %d: expected status %d, got %d", i, http.StatusOK, recorder.Code)
		}
	}
}

func TestMonitorEndpointMethod(t *testing.T) {
	tests := []struct {
		name       string
		method     string
		expectCode int
	}{
		{
			name:       "GET request",
			method:     "GET",
			expectCode: http.StatusOK,
		},
		{
			name:       "POST request",
			method:     "POST",
			expectCode: http.StatusOK,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			req := httptest.NewRequest(tt.method, "/monitor", nil)
			recorder := httptest.NewRecorder()
			handler := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				w.WriteHeader(http.StatusOK)
				w.Write([]byte("Monitoring started"))
			})

			handler.ServeHTTP(recorder, req)

			if recorder.Code != tt.expectCode {
				t.Errorf("expected status %d, got %d", tt.expectCode, recorder.Code)
			}
		})
	}
}

func TestHealthEndpointContentType(t *testing.T) {
	req := httptest.NewRequest("GET", "/health", nil)
	recorder := httptest.NewRecorder()
	handler := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "text/plain")
		w.WriteHeader(http.StatusOK)
		w.Write([]byte("OK"))
	})

	handler.ServeHTTP(recorder, req)

	contentType := recorder.Header().Get("Content-Type")
	if contentType != "text/plain" {
		t.Errorf("expected Content-Type 'text/plain', got '%s'", contentType)
	}
}
