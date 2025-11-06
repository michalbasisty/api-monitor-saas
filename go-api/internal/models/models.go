package models

import (
	"encoding/json"
	"time"
)

type Endpoint struct {
	ID          int             `json:"id"`
	UserID      int             `json:"user_id"`
	URL         string          `json:"url"`
	CheckInterval int            `json:"check_interval"`
	Timeout     int             `json:"timeout"`
	Headers     json.RawMessage `json:"headers"`
	IsActive    bool            `json:"is_active"`
}

type MonitoringResult struct {
	EndpointID   int    `json:"endpoint_id"`
	ResponseTime int    `json:"response_time"`
	StatusCode   *int   `json:"status_code"`
	ErrorMessage *string `json:"error_message"`
	CheckedAt    time.Time `json:"checked_at"`
}

type Alert struct {
	ID              int             `json:"id"`
	UserID          int             `json:"user_id"`
	EndpointID      int             `json:"endpoint_id"`
	AlertType       string          `json:"alert_type"` // response_time, status_code, availability
	Threshold       json.RawMessage `json:"threshold"`
	IsActive        bool            `json:"is_active"`
}
