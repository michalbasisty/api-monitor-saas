package metrics

import (
	"context"
	"time"
)

// MetricType represents the type of metric being collected
type MetricType string

const (
	MetricTypeGauge   MetricType = "gauge"
	MetricTypeCounter MetricType = "counter"
	MetricTypeTimer   MetricType = "timer"
	MetricTypeHistogram MetricType = "histogram"
)

// MetricValue represents a single metric data point
type MetricValue struct {
	Name        string
	Type        MetricType
	Value       float64
	Timestamp   time.Time
	Tags        map[string]string
	Description string
}

// MetricsCollector defines the interface for collecting metrics from various sources
type MetricsCollector interface {
	// Name returns the name of the collector
	Name() string

	// Collect gathers metrics from the source
	Collect(ctx context.Context) ([]MetricValue, error)

	// IsEnabled checks if this collector is enabled
	IsEnabled() bool

	// SetEnabled sets the enabled state
	SetEnabled(enabled bool)
}

// MetricsAggregator defines the interface for aggregating collected metrics
type MetricsAggregator interface {
	// AddCollector registers a new metrics collector
	AddCollector(collector MetricsCollector) error

	// RemoveCollector removes a collector by name
	RemoveCollector(name string) error

	// CollectAll gathers metrics from all registered collectors
	CollectAll(ctx context.Context) (map[string][]MetricValue, error)

	// CollectFrom gathers metrics from a specific collector
	CollectFrom(ctx context.Context, collectorName string) ([]MetricValue, error)

	// GetCollectors returns list of registered collectors
	GetCollectors() []string
}

// MetricsStore defines the interface for storing collected metrics
type MetricsStore interface {
	// Store saves metrics to persistent storage
	Store(ctx context.Context, metrics []MetricValue) error

	// Retrieve fetches metrics for a time range
	Retrieve(ctx context.Context, startTime, endTime time.Time) ([]MetricValue, error)

	// RetrieveByName fetches metrics by name
	RetrieveByName(ctx context.Context, name string, startTime, endTime time.Time) ([]MetricValue, error)

	// Aggregate calculates aggregate statistics for a metric
	Aggregate(ctx context.Context, name string, startTime, endTime time.Time) (map[string]interface{}, error)
}

// MetricsPublisher defines the interface for publishing metrics
type MetricsPublisher interface {
	// Publish sends metrics to interested subscribers
	Publish(ctx context.Context, metrics []MetricValue) error

	// Subscribe registers a handler for metric updates
	Subscribe(handler MetricsHandler) error

	// Unsubscribe removes a handler
	Unsubscribe(handler MetricsHandler) error
}

// MetricsHandler is a function that handles published metrics
type MetricsHandler func(ctx context.Context, metrics []MetricValue) error

// ExternalMetricsCollector defines additional interface for collecting external service metrics
type ExternalMetricsCollector interface {
	MetricsCollector

	// GetLastUpdateTime returns when metrics were last collected
	GetLastUpdateTime() time.Time

	// GetCollectionInterval returns the interval between collections
	GetCollectionInterval() time.Duration
}
