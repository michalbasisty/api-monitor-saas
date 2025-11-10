package metrics

import (
	"context"
	"database/sql"
	"fmt"
	"time"

	"api-monitor-saas/internal/database"
)

// MonitoringMetricsCollector collects metrics from the monitoring system
type MonitoringMetricsCollector struct {
	name               string
	enabled            bool
	lastUpdateTime     time.Time
	collectionInterval time.Duration
	db                 *sql.DB
}

// NewMonitoringMetricsCollector creates a new monitoring metrics collector
func NewMonitoringMetricsCollector(db *sql.DB) *MonitoringMetricsCollector {
	return &MonitoringMetricsCollector{
		name:               "monitoring",
		enabled:            true,
		collectionInterval: 5 * time.Minute,
		db:                 db,
	}
}

// Name returns the collector name
func (m *MonitoringMetricsCollector) Name() string {
	return m.name
}

// IsEnabled returns if the collector is enabled
func (m *MonitoringMetricsCollector) IsEnabled() bool {
	return m.enabled
}

// SetEnabled sets the enabled state
func (m *MonitoringMetricsCollector) SetEnabled(enabled bool) {
	m.enabled = enabled
}

// GetLastUpdateTime returns when metrics were last collected
func (m *MonitoringMetricsCollector) GetLastUpdateTime() time.Time {
	return m.lastUpdateTime
}

// GetCollectionInterval returns the collection interval
func (m *MonitoringMetricsCollector) GetCollectionInterval() time.Duration {
	return m.collectionInterval
}

// Collect gathers metrics from the monitoring system
func (m *MonitoringMetricsCollector) Collect(ctx context.Context) ([]MetricValue, error) {
	if !m.enabled {
		return []MetricValue{}, nil
	}

	var metrics []MetricValue

	// Collect endpoint metrics
	endpointMetrics, err := m.collectEndpointMetrics(ctx)
	if err != nil {
		return nil, fmt.Errorf("failed to collect endpoint metrics: %w", err)
	}
	metrics = append(metrics, endpointMetrics...)

	// Collect monitoring result metrics
	resultMetrics, err := m.collectMonitoringResultMetrics(ctx)
	if err != nil {
		return nil, fmt.Errorf("failed to collect result metrics: %w", err)
	}
	metrics = append(metrics, resultMetrics...)

	// Collect alert metrics
	alertMetrics, err := m.collectAlertMetrics(ctx)
	if err != nil {
		return nil, fmt.Errorf("failed to collect alert metrics: %w", err)
	}
	metrics = append(metrics, alertMetrics...)

	// Collect performance metrics
	perfMetrics, err := m.collectPerformanceMetrics(ctx)
	if err != nil {
		return nil, fmt.Errorf("failed to collect performance metrics: %w", err)
	}
	metrics = append(metrics, perfMetrics...)

	m.lastUpdateTime = time.Now()
	return metrics, nil
}

// collectEndpointMetrics collects metrics related to monitored endpoints
func (m *MonitoringMetricsCollector) collectEndpointMetrics(ctx context.Context) ([]MetricValue, error) {
	var metrics []MetricValue
	timestamp := time.Now()

	// Get total endpoints count
	var totalEndpoints int
	err := m.db.QueryRowContext(ctx, "SELECT COUNT(*) FROM endpoints WHERE deleted_at IS NULL").
		Scan(&totalEndpoints)
	if err != nil && err != sql.ErrNoRows {
		return nil, fmt.Errorf("failed to count endpoints: %w", err)
	}

	// Get active endpoints count
	var activeEndpoints int
	err = m.db.QueryRowContext(ctx, "SELECT COUNT(*) FROM endpoints WHERE deleted_at IS NULL AND is_active = true").
		Scan(&activeEndpoints)
	if err != nil && err != sql.ErrNoRows {
		return nil, fmt.Errorf("failed to count active endpoints: %w", err)
	}

	// Get endpoints by type
	typeQuery := `
		SELECT 
			protocol,
			COUNT(*) as count
		FROM endpoints
		WHERE deleted_at IS NULL
		GROUP BY protocol
	`

	rows, err := m.db.QueryContext(ctx, typeQuery)
	if err != nil {
		return nil, fmt.Errorf("failed to query endpoints by type: %w", err)
	}
	defer rows.Close()

	for rows.Next() {
		var protocol string
		var count int

		if err := rows.Scan(&protocol, &count); err != nil {
			return nil, fmt.Errorf("failed to scan endpoint type: %w", err)
		}

		metrics = append(metrics, MetricValue{
			Name:      "monitoring_endpoints_by_type",
			Type:      MetricTypeGauge,
			Value:     float64(count),
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": m.name,
				"protocol":  protocol,
			},
			Description: fmt.Sprintf("Number of %s endpoints", protocol),
		})
	}

	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("error iterating endpoint rows: %w", err)
	}

	metrics = append(metrics,
		MetricValue{
			Name:      "monitoring_endpoints_total",
			Type:      MetricTypeGauge,
			Value:     float64(totalEndpoints),
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": m.name,
			},
			Description: "Total number of monitored endpoints",
		},
		MetricValue{
			Name:      "monitoring_endpoints_active",
			Type:      MetricTypeGauge,
			Value:     float64(activeEndpoints),
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": m.name,
			},
			Description: "Number of active monitored endpoints",
		},
	)

	return metrics, nil
}

// collectMonitoringResultMetrics collects metrics about monitoring results
func (m *MonitoringMetricsCollector) collectMonitoringResultMetrics(ctx context.Context) ([]MetricValue, error) {
	var metrics []MetricValue
	timestamp := time.Now()
	oneDayAgo := timestamp.Add(-24 * time.Hour)

	// Get successful checks in last 24 hours
	var successfulChecks int
	err := m.db.QueryRowContext(ctx,
		`SELECT COUNT(*) FROM monitoring_results 
		 WHERE created_at > $1 AND status_code >= 200 AND status_code < 300`,
		oneDayAgo).
		Scan(&successfulChecks)
	if err != nil && err != sql.ErrNoRows {
		return nil, fmt.Errorf("failed to count successful checks: %w", err)
	}

	// Get failed checks in last 24 hours
	var failedChecks int
	err = m.db.QueryRowContext(ctx,
		`SELECT COUNT(*) FROM monitoring_results 
		 WHERE created_at > $1 AND (status_code >= 400 OR status_code = 0)`,
		oneDayAgo).
		Scan(&failedChecks)
	if err != nil && err != sql.ErrNoRows {
		return nil, fmt.Errorf("failed to count failed checks: %w", err)
	}

	// Get average response time in last 24 hours
	var avgResponseTime sql.NullFloat64
	err = m.db.QueryRowContext(ctx,
		`SELECT AVG(response_time_ms) FROM monitoring_results 
		 WHERE created_at > $1 AND response_time_ms > 0`,
		oneDayAgo).
		Scan(&avgResponseTime)
	if err != nil && err != sql.ErrNoRows {
		return nil, fmt.Errorf("failed to get average response time: %w", err)
	}

	// Get max response time
	var maxResponseTime sql.NullFloat64
	err = m.db.QueryRowContext(ctx,
		`SELECT MAX(response_time_ms) FROM monitoring_results 
		 WHERE created_at > $1 AND response_time_ms > 0`,
		oneDayAgo).
		Scan(&maxResponseTime)
	if err != nil && err != sql.ErrNoRows {
		return nil, fmt.Errorf("failed to get max response time: %w", err)
	}

	metrics = append(metrics,
		MetricValue{
			Name:      "monitoring_checks_successful_24h",
			Type:      MetricTypeCounter,
			Value:     float64(successfulChecks),
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": m.name,
				"status":    "successful",
			},
			Description: "Number of successful health checks in last 24 hours",
		},
		MetricValue{
			Name:      "monitoring_checks_failed_24h",
			Type:      MetricTypeCounter,
			Value:     float64(failedChecks),
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": m.name,
				"status":    "failed",
			},
			Description: "Number of failed health checks in last 24 hours",
		},
	)

	if avgResponseTime.Valid {
		metrics = append(metrics, MetricValue{
			Name:      "monitoring_response_time_avg_ms_24h",
			Type:      MetricTypeTimer,
			Value:     avgResponseTime.Float64,
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": m.name,
				"unit":      "ms",
			},
			Description: "Average response time in last 24 hours",
		})
	}

	if maxResponseTime.Valid {
		metrics = append(metrics, MetricValue{
			Name:      "monitoring_response_time_max_ms_24h",
			Type:      MetricTypeTimer,
			Value:     maxResponseTime.Float64,
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": m.name,
				"unit":      "ms",
			},
			Description: "Maximum response time in last 24 hours",
		})
	}

	return metrics, nil
}

// collectAlertMetrics collects metrics about alert system
func (m *MonitoringMetricsCollector) collectAlertMetrics(ctx context.Context) ([]MetricValue, error) {
	var metrics []MetricValue
	timestamp := time.Now()
	oneDayAgo := timestamp.Add(-24 * time.Hour)

	// Get total alerts configured
	var totalAlerts int
	err := m.db.QueryRowContext(ctx, "SELECT COUNT(*) FROM alerts WHERE deleted_at IS NULL").
		Scan(&totalAlerts)
	if err != nil && err != sql.ErrNoRows {
		return nil, fmt.Errorf("failed to count alerts: %w", err)
	}

	// Get triggered alerts in last 24 hours
	var triggeredAlerts int
	err = m.db.QueryRowContext(ctx,
		`SELECT COUNT(*) FROM alerts 
		 WHERE triggered_at IS NOT NULL AND triggered_at > $1`,
		oneDayAgo).
		Scan(&triggeredAlerts)
	if err != nil && err != sql.ErrNoRows {
		return nil, fmt.Errorf("failed to count triggered alerts: %w", err)
	}

	metrics = append(metrics,
		MetricValue{
			Name:      "monitoring_alerts_configured",
			Type:      MetricTypeGauge,
			Value:     float64(totalAlerts),
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": m.name,
			},
			Description: "Total number of configured alerts",
		},
		MetricValue{
			Name:      "monitoring_alerts_triggered_24h",
			Type:      MetricTypeCounter,
			Value:     float64(triggeredAlerts),
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": m.name,
				"period":    "24h",
			},
			Description: "Number of triggered alerts in last 24 hours",
		},
	)

	return metrics, nil
}

// collectPerformanceMetrics collects overall system performance metrics
func (m *MonitoringMetricsCollector) collectPerformanceMetrics(ctx context.Context) ([]MetricValue, error) {
	var metrics []MetricValue
	timestamp := time.Now()

	// Get uptime percentage for last 24 hours
	oneDayAgo := timestamp.Add(-24 * time.Hour)

	var totalResults int
	err := m.db.QueryRowContext(ctx,
		`SELECT COUNT(*) FROM monitoring_results 
		 WHERE created_at > $1`,
		oneDayAgo).
		Scan(&totalResults)
	if err != nil && err != sql.ErrNoRows {
		return nil, fmt.Errorf("failed to count total results: %w", err)
	}

	var successfulResults int
	err = m.db.QueryRowContext(ctx,
		`SELECT COUNT(*) FROM monitoring_results 
		 WHERE created_at > $1 AND status_code >= 200 AND status_code < 300`,
		oneDayAgo).
		Scan(&successfulResults)
	if err != nil && err != sql.ErrNoRows {
		return nil, fmt.Errorf("failed to count successful results: %w", err)
	}

	var uptimePercentage float64 = 0
	if totalResults > 0 {
		uptimePercentage = float64(successfulResults) / float64(totalResults) * 100
	}

	metrics = append(metrics, MetricValue{
		Name:      "monitoring_system_uptime_percent_24h",
		Type:      MetricTypeGauge,
		Value:     uptimePercentage,
		Timestamp: timestamp,
		Tags: map[string]string{
			"collector": m.name,
			"period":    "24h",
		},
		Description: "System uptime percentage in last 24 hours",
	})

	return metrics, nil
}
