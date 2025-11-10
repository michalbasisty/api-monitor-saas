package metrics

import (
	"context"
	"database/sql"
	"encoding/json"
	"fmt"
	"time"
)

// PostgresMetricsStore stores metrics in PostgreSQL
type PostgresMetricsStore struct {
	db *sql.DB
}

// NewPostgresMetricsStore creates a new PostgreSQL metrics store
func NewPostgresMetricsStore(db *sql.DB) *PostgresMetricsStore {
	return &PostgresMetricsStore{
		db: db,
	}
}

// Store saves metrics to persistent storage
func (s *PostgresMetricsStore) Store(ctx context.Context, metrics []MetricValue) error {
	if len(metrics) == 0 {
		return nil
	}

	query := `
		INSERT INTO system_metrics (name, type, value, timestamp, tags, description)
		VALUES ($1, $2, $3, $4, $5, $6)
		ON CONFLICT DO NOTHING
	`

	for _, metric := range metrics {
		tagsJSON, err := json.Marshal(metric.Tags)
		if err != nil {
			return fmt.Errorf("failed to marshal tags: %w", err)
		}

		_, err = s.db.ExecContext(ctx, query,
			metric.Name,
			metric.Type,
			metric.Value,
			metric.Timestamp,
			tagsJSON,
			metric.Description,
		)
		if err != nil {
			return fmt.Errorf("failed to store metric '%s': %w", metric.Name, err)
		}
	}

	return nil
}

// Retrieve fetches metrics for a time range
func (s *PostgresMetricsStore) Retrieve(ctx context.Context, startTime, endTime time.Time) ([]MetricValue, error) {
	query := `
		SELECT name, type, value, timestamp, tags, description
		FROM system_metrics
		WHERE timestamp BETWEEN $1 AND $2
		ORDER BY timestamp DESC
	`

	rows, err := s.db.QueryContext(ctx, query, startTime, endTime)
	if err != nil {
		return nil, fmt.Errorf("failed to query metrics: %w", err)
	}
	defer rows.Close()

	var metrics []MetricValue
	for rows.Next() {
		var metric MetricValue
		var tagsJSON []byte

		err := rows.Scan(
			&metric.Name,
			&metric.Type,
			&metric.Value,
			&metric.Timestamp,
			&tagsJSON,
			&metric.Description,
		)
		if err != nil {
			return nil, fmt.Errorf("failed to scan metric: %w", err)
		}

		metric.Tags = make(map[string]string)
		if len(tagsJSON) > 0 {
			err = json.Unmarshal(tagsJSON, &metric.Tags)
			if err != nil {
				return nil, fmt.Errorf("failed to unmarshal tags: %w", err)
			}
		}

		metrics = append(metrics, metric)
	}

	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("error iterating metric rows: %w", err)
	}

	return metrics, nil
}

// RetrieveByName fetches metrics by name
func (s *PostgresMetricsStore) RetrieveByName(ctx context.Context, name string, startTime, endTime time.Time) ([]MetricValue, error) {
	query := `
		SELECT name, type, value, timestamp, tags, description
		FROM system_metrics
		WHERE name = $1 AND timestamp BETWEEN $2 AND $3
		ORDER BY timestamp DESC
	`

	rows, err := s.db.QueryContext(ctx, query, name, startTime, endTime)
	if err != nil {
		return nil, fmt.Errorf("failed to query metrics by name: %w", err)
	}
	defer rows.Close()

	var metrics []MetricValue
	for rows.Next() {
		var metric MetricValue
		var tagsJSON []byte

		err := rows.Scan(
			&metric.Name,
			&metric.Type,
			&metric.Value,
			&metric.Timestamp,
			&tagsJSON,
			&metric.Description,
		)
		if err != nil {
			return nil, fmt.Errorf("failed to scan metric: %w", err)
		}

		metric.Tags = make(map[string]string)
		if len(tagsJSON) > 0 {
			err = json.Unmarshal(tagsJSON, &metric.Tags)
			if err != nil {
				return nil, fmt.Errorf("failed to unmarshal tags: %w", err)
			}
		}

		metrics = append(metrics, metric)
	}

	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("error iterating metric rows: %w", err)
	}

	return metrics, nil
}

// Aggregate calculates aggregate statistics for a metric
func (s *PostgresMetricsStore) Aggregate(ctx context.Context, name string, startTime, endTime time.Time) (map[string]interface{}, error) {
	query := `
		SELECT 
			COUNT(*) as count,
			AVG(value) as average,
			MIN(value) as minimum,
			MAX(value) as maximum,
			STDDEV(value) as stddev
		FROM system_metrics
		WHERE name = $1 AND timestamp BETWEEN $2 AND $3
	`

	var count int
	var avg, min, max, stddev sql.NullFloat64

	err := s.db.QueryRowContext(ctx, query, name, startTime, endTime).
		Scan(&count, &avg, &min, &max, &stddev)
	if err != nil {
		return nil, fmt.Errorf("failed to aggregate metrics: %w", err)
	}

	result := map[string]interface{}{
		"name":  name,
		"count": count,
	}

	if avg.Valid {
		result["average"] = avg.Float64
	}
	if min.Valid {
		result["minimum"] = min.Float64
	}
	if max.Valid {
		result["maximum"] = max.Float64
	}
	if stddev.Valid {
		result["stddev"] = stddev.Float64
	}

	return result, nil
}
