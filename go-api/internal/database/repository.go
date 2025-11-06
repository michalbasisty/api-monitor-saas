package database

import (
	"database/sql"
	"fmt"
	"time"

	"api-monitor-go/internal/models"
)

type Repository struct {
	db *sql.DB
}

func NewRepository(db *sql.DB) *Repository {
	return &Repository{db: db}
}

func (r *Repository) GetActiveEndpoints() ([]models.Endpoint, error) {
	query := `SELECT id, user_id, url, check_interval, timeout, headers, is_active
	          FROM api_endpoints WHERE is_active = true`

	rows, err := r.db.Query(query)
	if err != nil {
		return nil, fmt.Errorf("failed to query endpoints: %w", err)
	}
	defer rows.Close()

	var endpoints []models.Endpoint
	for rows.Next() {
		var e models.Endpoint
		err := rows.Scan(&e.ID, &e.UserID, &e.URL, &e.CheckInterval, &e.Timeout, &e.Headers, &e.IsActive)
		if err != nil {
			return nil, fmt.Errorf("failed to scan endpoint: %w", err)
		}
		endpoints = append(endpoints, e)
	}

	return endpoints, nil
}

func (r *Repository) GetAlertsForEndpoint(endpointID int) ([]models.Alert, error) {
	query := `SELECT id, user_id, endpoint_id, alert_type, threshold, is_active
	          FROM alerts WHERE endpoint_id = $1 AND is_active = true`

	rows, err := r.db.Query(query, endpointID)
	if err != nil {
		return nil, fmt.Errorf("failed to query alerts: %w", err)
	}
	defer rows.Close()

	var alerts []models.Alert
	for rows.Next() {
		var a models.Alert
		err := rows.Scan(&a.ID, &a.UserID, &a.EndpointID, &a.AlertType, &a.Threshold, &a.IsActive)
		if err != nil {
			return nil, fmt.Errorf("failed to scan alert: %w", err)
		}
		alerts = append(alerts, a)
	}

	return alerts, nil
}

func (r *Repository) SaveResult(result models.MonitoringResult) error {
	query := `INSERT INTO monitoring_results (endpoint_id, response_time, status_code, error_message, checked_at, created_at)
	          VALUES ($1, $2, $3, $4, $5, $6)`

	_, err := r.db.Exec(query,
		result.EndpointID,
		result.ResponseTime,
		result.StatusCode,
		result.ErrorMessage,
		result.CheckedAt,
		time.Now(),
	)

	if err != nil {
		return fmt.Errorf("failed to save result: %w", err)
	}

	return nil
}
