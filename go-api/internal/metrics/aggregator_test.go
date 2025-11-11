package metrics

import (
	"context"
	"fmt"
	"sync"
	"testing"
	"time"
)

// MockCollector implements MetricsCollector for testing
type MockCollector struct {
	name      string
	enabled   bool
	metrics   []MetricValue
	err       error
	callCount int
	mu        sync.Mutex
}

func (m *MockCollector) Name() string {
	return m.name
}

func (m *MockCollector) Collect(ctx context.Context) ([]MetricValue, error) {
	m.mu.Lock()
	m.callCount++
	m.mu.Unlock()
	if m.err != nil {
		return nil, m.err
	}
	return m.metrics, nil
}

func (m *MockCollector) IsEnabled() bool {
	return m.enabled
}

func (m *MockCollector) SetEnabled(enabled bool) {
	m.enabled = enabled
}

func (m *MockCollector) GetCallCount() int {
	m.mu.Lock()
	defer m.mu.Unlock()
	return m.callCount
}

func TestNewMetricsAggregator(t *testing.T) {
	tests := []struct {
		name       string
		maxWorkers int
		expected   int
	}{
		{"Positive workers", 10, 10},
		{"Zero workers defaults to 5", 0, 5},
		{"Negative workers defaults to 5", -5, 5},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			agg := NewMetricsAggregator(tt.maxWorkers)
			if agg.maxWorkers != tt.expected {
				t.Fatalf("expected %d, got %d", tt.expected, agg.maxWorkers)
			}
		})
	}
}

func TestAddCollectorSuccess(t *testing.T) {
	agg := NewMetricsAggregator(5)
	collector := &MockCollector{name: "test_collector"}

	err := agg.AddCollector(collector)
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}

	collectors := agg.GetCollectors()
	if len(collectors) != 1 || collectors[0] != "test_collector" {
		t.Fatalf("expected 1 collector named test_collector, got %v", collectors)
	}
}

func TestAddCollectorNil(t *testing.T) {
	agg := NewMetricsAggregator(5)

	err := agg.AddCollector(nil)
	if err == nil {
		t.Fatalf("expected error for nil collector")
	}
}

func TestAddCollectorEmptyName(t *testing.T) {
	agg := NewMetricsAggregator(5)
	collector := &MockCollector{name: ""}

	err := agg.AddCollector(collector)
	if err == nil {
		t.Fatalf("expected error for empty name")
	}
}

func TestAddCollectorDuplicate(t *testing.T) {
	agg := NewMetricsAggregator(5)
	collector1 := &MockCollector{name: "test"}
	collector2 := &MockCollector{name: "test"}

	agg.AddCollector(collector1)
	err := agg.AddCollector(collector2)
	if err == nil {
		t.Fatalf("expected error for duplicate collector")
	}
}

func TestRemoveCollectorSuccess(t *testing.T) {
	agg := NewMetricsAggregator(5)
	collector := &MockCollector{name: "test"}
	agg.AddCollector(collector)

	err := agg.RemoveCollector("test")
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}

	collectors := agg.GetCollectors()
	if len(collectors) != 0 {
		t.Fatalf("expected 0 collectors, got %d", len(collectors))
	}
}

func TestRemoveCollectorNotFound(t *testing.T) {
	agg := NewMetricsAggregator(5)

	err := agg.RemoveCollector("nonexistent")
	if err == nil {
		t.Fatalf("expected error for nonexistent collector")
	}
}

func TestCollectAllSuccess(t *testing.T) {
	agg := NewMetricsAggregator(5)

	collector1 := &MockCollector{
		name:    "cpu",
		enabled: true,
		metrics: []MetricValue{
			{Name: "cpu_usage", Value: 45.5},
		},
	}

	collector2 := &MockCollector{
		name:    "memory",
		enabled: true,
		metrics: []MetricValue{
			{Name: "memory_usage", Value: 60.0},
		},
	}

	agg.AddCollector(collector1)
	agg.AddCollector(collector2)

	result, err := agg.CollectAll(context.Background())
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}

	if len(result) != 2 {
		t.Fatalf("expected 2 collectors, got %d", len(result))
	}

	if len(result["cpu"]) != 1 || result["cpu"][0].Value != 45.5 {
		t.Fatalf("unexpected cpu metrics")
	}

	if len(result["memory"]) != 1 || result["memory"][0].Value != 60.0 {
		t.Fatalf("unexpected memory metrics")
	}
}

func TestCollectAllDisabledCollector(t *testing.T) {
	agg := NewMetricsAggregator(5)

	collector1 := &MockCollector{
		name:    "cpu",
		enabled: true,
		metrics: []MetricValue{{Name: "cpu_usage", Value: 45.5}},
	}

	collector2 := &MockCollector{
		name:    "network",
		enabled: false,
		metrics: []MetricValue{{Name: "network_usage", Value: 10.0}},
	}

	agg.AddCollector(collector1)
	agg.AddCollector(collector2)

	result, err := agg.CollectAll(context.Background())
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}

	if len(result) != 1 {
		t.Fatalf("expected 1 collector (disabled excluded), got %d", len(result))
	}

	if _, exists := result["cpu"]; !exists {
		t.Fatalf("expected cpu collector in results")
	}
	if _, exists := result["network"]; exists {
		t.Fatalf("expected network collector to be excluded")
	}
}

func TestCollectAllCollectorError(t *testing.T) {
	agg := NewMetricsAggregator(5)

	collector1 := &MockCollector{
		name:    "cpu",
		enabled: true,
		metrics: []MetricValue{{Name: "cpu_usage", Value: 45.5}},
	}

	collector2 := &MockCollector{
		name:    "memory",
		enabled: true,
		err:     fmt.Errorf("collection failed"),
	}

	agg.AddCollector(collector1)
	agg.AddCollector(collector2)

	result, err := agg.CollectAll(context.Background())
	if err == nil {
		t.Fatalf("expected error from collector")
	}

	if len(result) != 1 {
		t.Fatalf("expected partial results, got %d collectors", len(result))
	}
}

func TestCollectAllConcurrency(t *testing.T) {
	agg := NewMetricsAggregator(2)

	numCollectors := 10
	for i := 0; i < numCollectors; i++ {
		collector := &MockCollector{
			name:    fmt.Sprintf("collector_%d", i),
			enabled: true,
			metrics: []MetricValue{
				{Name: fmt.Sprintf("metric_%d", i), Value: float64(i)},
			},
		}
		agg.AddCollector(collector)
	}

	result, err := agg.CollectAll(context.Background())
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}

	if len(result) != numCollectors {
		t.Fatalf("expected %d collectors, got %d", numCollectors, len(result))
	}
}

func TestCollectFromSuccess(t *testing.T) {
	agg := NewMetricsAggregator(5)

	collector := &MockCollector{
		name:    "cpu",
		enabled: true,
		metrics: []MetricValue{
			{Name: "cpu_usage", Value: 45.5},
		},
	}

	agg.AddCollector(collector)

	result, err := agg.CollectFrom(context.Background(), "cpu")
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}

	if len(result) != 1 || result[0].Value != 45.5 {
		t.Fatalf("unexpected result")
	}
}

func TestCollectFromNotFound(t *testing.T) {
	agg := NewMetricsAggregator(5)

	result, err := agg.CollectFrom(context.Background(), "nonexistent")
	if err == nil {
		t.Fatalf("expected error for nonexistent collector")
	}

	if result != nil {
		t.Fatalf("expected nil result")
	}
}

func TestCollectFromDisabled(t *testing.T) {
	agg := NewMetricsAggregator(5)

	collector := &MockCollector{
		name:    "cpu",
		enabled: false,
		metrics: []MetricValue{{Name: "cpu_usage", Value: 45.5}},
	}

	agg.AddCollector(collector)

	result, err := agg.CollectFrom(context.Background(), "cpu")
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}

	if len(result) != 0 {
		t.Fatalf("expected empty result for disabled collector")
	}
}

func TestGetCollectors(t *testing.T) {
	agg := NewMetricsAggregator(5)

	collector1 := &MockCollector{name: "cpu"}
	collector2 := &MockCollector{name: "memory"}
	collector3 := &MockCollector{name: "network"}

	agg.AddCollector(collector1)
	agg.AddCollector(collector2)
	agg.AddCollector(collector3)

	collectors := agg.GetCollectors()
	if len(collectors) != 3 {
		t.Fatalf("expected 3 collectors, got %d", len(collectors))
	}

	collectorMap := make(map[string]bool)
	for _, c := range collectors {
		collectorMap[c] = true
	}

	for _, name := range []string{"cpu", "memory", "network"} {
		if !collectorMap[name] {
			t.Fatalf("expected collector %s", name)
		}
	}
}

func TestCollectAllEmptyCollectors(t *testing.T) {
	agg := NewMetricsAggregator(5)

	result, err := agg.CollectAll(context.Background())
	if err != nil {
		t.Fatalf("expected no error, got %v", err)
	}

	if len(result) != 0 {
		t.Fatalf("expected empty result")
	}
}

func TestCollectAllContextCancellation(t *testing.T) {
	agg := NewMetricsAggregator(1)

	collector := &MockCollector{
		name:    "slow",
		enabled: true,
		metrics: []MetricValue{{Name: "metric", Value: 1}},
	}

	agg.AddCollector(collector)

	ctx, cancel := context.WithCancel(context.Background())
	cancel()

	result, err := agg.CollectAll(ctx)
	if len(result) == 0 && err != nil {
		t.Logf("Context cancellation handled: %v", err)
	}
}

func TestAddRemoveMultipleCollectors(t *testing.T) {
	agg := NewMetricsAggregator(5)

	for i := 0; i < 5; i++ {
		collector := &MockCollector{name: fmt.Sprintf("collector_%d", i)}
		err := agg.AddCollector(collector)
		if err != nil {
			t.Fatalf("failed to add collector: %v", err)
		}
	}

	collectors := agg.GetCollectors()
	if len(collectors) != 5 {
		t.Fatalf("expected 5 collectors, got %d", len(collectors))
	}

	for i := 0; i < 3; i++ {
		err := agg.RemoveCollector(fmt.Sprintf("collector_%d", i))
		if err != nil {
			t.Fatalf("failed to remove collector: %v", err)
		}
	}

	collectors = agg.GetCollectors()
	if len(collectors) != 2 {
		t.Fatalf("expected 2 collectors after removal, got %d", len(collectors))
	}
}

func TestCollectorEnableDisable(t *testing.T) {
	agg := NewMetricsAggregator(5)

	collector := &MockCollector{
		name:    "test",
		enabled: true,
		metrics: []MetricValue{{Name: "metric", Value: 1}},
	}

	agg.AddCollector(collector)

	result1, _ := agg.CollectAll(context.Background())
	if len(result1) != 1 {
		t.Fatalf("expected 1 result when enabled")
	}

	collector.SetEnabled(false)

	result2, _ := agg.CollectAll(context.Background())
	if len(result2) != 0 {
		t.Fatalf("expected 0 results when disabled")
	}
}

func TestCollectorNameConsistency(t *testing.T) {
	agg := NewMetricsAggregator(5)

	collector := &MockCollector{name: "test_collector"}
	agg.AddCollector(collector)

	collectors := agg.GetCollectors()
	if collectors[0] != "test_collector" {
		t.Fatalf("collector name mismatch")
	}

	result, err := agg.CollectFrom(context.Background(), "test_collector")
	if err != nil {
		t.Fatalf("failed to collect from collector")
	}

	if result != nil {
		t.Logf("collected from collector: %v", result)
	}
}
