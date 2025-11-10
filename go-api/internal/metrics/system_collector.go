package metrics

import (
	"context"
	"fmt"
	"os"
	"runtime"
	"time"
)

// SystemMetricsCollector collects system metrics (memory, CPU, goroutines)
type SystemMetricsCollector struct {
	name               string
	enabled            bool
	lastUpdateTime     time.Time
	collectionInterval time.Duration
}

// NewSystemMetricsCollector creates a new system metrics collector
func NewSystemMetricsCollector() *SystemMetricsCollector {
	return &SystemMetricsCollector{
		name:               "system",
		enabled:            true,
		collectionInterval: 30 * time.Second,
	}
}

// Name returns the collector name
func (s *SystemMetricsCollector) Name() string {
	return s.name
}

// IsEnabled returns if the collector is enabled
func (s *SystemMetricsCollector) IsEnabled() bool {
	return s.enabled
}

// SetEnabled sets the enabled state
func (s *SystemMetricsCollector) SetEnabled(enabled bool) {
	s.enabled = enabled
}

// GetLastUpdateTime returns when metrics were last collected
func (s *SystemMetricsCollector) GetLastUpdateTime() time.Time {
	return s.lastUpdateTime
}

// GetCollectionInterval returns the collection interval
func (s *SystemMetricsCollector) GetCollectionInterval() time.Duration {
	return s.collectionInterval
}

// Collect gathers system metrics
func (s *SystemMetricsCollector) Collect(ctx context.Context) ([]MetricValue, error) {
	if !s.enabled {
		return []MetricValue{}, nil
	}

	var metrics []MetricValue
	timestamp := time.Now()

	// Collect memory metrics
	memMetrics := s.collectMemoryMetrics(timestamp)
	metrics = append(metrics, memMetrics...)

	// Collect goroutine metrics
	goMetrics := s.collectGoroutineMetrics(timestamp)
	metrics = append(metrics, goMetrics...)

	// Collect process metrics
	procMetrics := s.collectProcessMetrics(timestamp)
	metrics = append(metrics, procMetrics...)

	s.lastUpdateTime = timestamp
	return metrics, nil
}

// collectMemoryMetrics collects Go runtime memory metrics
func (s *SystemMetricsCollector) collectMemoryMetrics(timestamp time.Time) []MetricValue {
	var metrics []MetricValue
	var m runtime.MemStats
	runtime.ReadMemStats(&m)

	metrics = append(metrics,
		MetricValue{
			Name:      "system_memory_alloc_bytes",
			Type:      MetricTypeGauge,
			Value:     float64(m.Alloc),
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": s.name,
				"type":      "alloc",
			},
			Description: "Current memory allocation in bytes",
		},
		MetricValue{
			Name:      "system_memory_total_alloc_bytes",
			Type:      MetricTypeCounter,
			Value:     float64(m.TotalAlloc),
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": s.name,
				"type":      "total_alloc",
			},
			Description: "Total memory allocated in bytes",
		},
		MetricValue{
			Name:      "system_memory_sys_bytes",
			Type:      MetricTypeGauge,
			Value:     float64(m.Sys),
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": s.name,
				"type":      "sys",
			},
			Description: "Memory obtained from system in bytes",
		},
		MetricValue{
			Name:      "system_memory_gc_pause_ns",
			Type:      MetricTypeHistogram,
			Value:     float64(m.PauseNs[(m.NumGC+255)%256]),
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": s.name,
				"type":      "gc_pause",
			},
			Description: "Last garbage collection pause duration in nanoseconds",
		},
		MetricValue{
			Name:      "system_gc_runs_total",
			Type:      MetricTypeCounter,
			Value:     float64(m.NumGC),
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": s.name,
				"type":      "gc_runs",
			},
			Description: "Total number of garbage collection runs",
		},
	)

	return metrics
}

// collectGoroutineMetrics collects goroutine metrics
func (s *SystemMetricsCollector) collectGoroutineMetrics(timestamp time.Time) []MetricValue {
	return []MetricValue{
		{
			Name:      "system_goroutines_total",
			Type:      MetricTypeGauge,
			Value:     float64(runtime.NumGoroutine()),
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": s.name,
				"type":      "goroutines",
			},
			Description: "Number of active goroutines",
		},
	}
}

// collectProcessMetrics collects process-related metrics
func (s *SystemMetricsCollector) collectProcessMetrics(timestamp time.Time) []MetricValue {
	var metrics []MetricValue

	// Get process ID
	pid := os.Getpid()

	metrics = append(metrics,
		MetricValue{
			Name:      "system_process_id",
			Type:      MetricTypeGauge,
			Value:     float64(pid),
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": s.name,
				"type":      "pid",
			},
			Description: "Current process ID",
		},
	)

	return metrics
}
