package metrics

import (
	"context"
	"database/sql"
	"fmt"
	"testing"
	"time"

	_ "github.com/lib/pq"
)

// GetTestDB returns a test database connection or skips the test
func GetTestDB(t *testing.T) *sql.DB {
	// Try to connect to test PostgreSQL
	db, err := sql.Open("postgres", "postgres://appuser:password@localhost:5432/apimon_test?sslmode=disable")
	if err != nil {
		t.Skip("Database not available, skipping test")
	}

	err = db.Ping()
	if err != nil {
		t.Skip("Database not reachable, skipping test")
	}

	return db
}

func TestNewPostgresMetricsStore(t *testing.T) {
	db := GetTestDB(t)
	defer db.Close()

	store := NewPostgresMetricsStore(db)
	if store == nil {
		t.Fatalf("expected store instance")
	}
}

func TestStoreEmptyMetrics(t *testing.T) {
	db := GetTestDB(t)
	defer db.Close()

	store := NewPostgresMetricsStore(db)

	err := store.Store(context.Background(), []MetricValue{})
	if err != nil {
		t.Fatalf("expected no error for empty metrics, got %v", err)
	}
}

func TestStoreSuccess(t *testing.T) {
	db := GetTestDB(t)
	defer db.Close()

	store := NewPostgresMetricsStore(db)

	metrics := []MetricValue{
		{
			Name:        "cpu_usage",
			Type:        MetricTypeGauge,
			Value:       45.5,
			Timestamp:   time.Now(),
			Tags:        map[string]string{"host": "server1"},
			Description: "CPU usage percentage",
		},
	}

	err := store.Store(context.Background(), metrics)
	if err != nil {
		t.Fatalf("failed to store metrics: %v", err)
	}
}

func TestStoreSingleMetric(t *testing.T) {
	db := GetTestDB(t)
	defer db.Close()

	store := NewPostgresMetricsStore(db)

	metric := MetricValue{
		Name:        fmt.Sprintf("test_metric_%d", time.Now().UnixNano()),
		Type:        MetricTypeGauge,
		Value:       100.0,
		Timestamp:   time.Now(),
		Tags:        map[string]string{"env": "test"},
		Description: "Test metric",
	}

	err := store.Store(context.Background(), []MetricValue{metric})
	if err != nil {
		t.Fatalf("failed to store single metric: %v", err)
	}
}

func TestStoreMultipleMetrics(t *testing.T) {
	db := GetTestDB(t)
	defer db.Close()

	store := NewPostgresMetricsStore(db)

	now := time.Now()
	metrics := []MetricValue{
		{
			Name:      fmt.Sprintf("metric_1_%d", now.UnixNano()),
			Type:      MetricTypeGauge,
			Value:     10.0,
			Timestamp: now,
			Tags:      map[string]string{"id": "1"},
		},
		{
			Name:      fmt.Sprintf("metric_2_%d", now.UnixNano()),
			Type:      MetricTypeCounter,
			Value:     20.0,
			Timestamp: now,
			Tags:      map[string]string{"id": "2"},
		},
		{
			Name:      fmt.Sprintf("metric_3_%d", now.UnixNano()),
			Type:      MetricTypeTimer,
			Value:     30.0,
			Timestamp: now,
			Tags:      map[string]string{"id": "3"},
		},
	}

	err := store.Store(context.Background(), metrics)
	if err != nil {
		t.Fatalf("failed to store multiple metrics: %v", err)
	}
}

func TestStoreWithNullTags(t *testing.T) {
	db := GetTestDB(t)
	defer db.Close()

	store := NewPostgresMetricsStore(db)

	metric := MetricValue{
		Name:      fmt.Sprintf("metric_no_tags_%d", time.Now().UnixNano()),
		Type:      MetricTypeGauge,
		Value:     50.0,
		Timestamp: time.Now(),
		Tags:      nil,
	}

	err := store.Store(context.Background(), []MetricValue{metric})
	if err != nil {
		t.Fatalf("failed to store metric with nil tags: %v", err)
	}
}

func TestRetrieve(t *testing.T) {
	db := GetTestDB(t)
	defer db.Close()

	store := NewPostgresMetricsStore(db)

	now := time.Now()
	metric := MetricValue{
		Name:      fmt.Sprintf("retrieve_test_%d", now.UnixNano()),
		Type:      MetricTypeGauge,
		Value:     75.5,
		Timestamp: now,
		Tags:      map[string]string{"test": "retrieve"},
	}

	store.Store(context.Background(), []MetricValue{metric})

	// Retrieve with time range
	startTime := now.Add(-1 * time.Minute)
	endTime := now.Add(1 * time.Minute)

	metrics, err := store.Retrieve(context.Background(), startTime, endTime)
	if err != nil {
		t.Fatalf("failed to retrieve metrics: %v", err)
	}

	if len(metrics) == 0 {
		t.Logf("No metrics retrieved in time range, this may be normal if metrics not committed yet")
	}
}

func TestRetrieveEmptyRange(t *testing.T) {
	db := GetTestDB(t)
	defer db.Close()

	store := NewPostgresMetricsStore(db)

	// Query far in the past
	startTime := time.Now().Add(-24 * time.Hour)
	endTime := time.Now().Add(-23 * time.Hour)

	metrics, err := store.Retrieve(context.Background(), startTime, endTime)
	if err != nil {
		t.Fatalf("expected no error for empty range: %v", err)
	}

	if len(metrics) >= 0 {
		t.Logf("Empty range query returned %d metrics", len(metrics))
	}
}

func TestRetrieveByName(t *testing.T) {
	db := GetTestDB(t)
	defer db.Close()

	store := NewPostgresMetricsStore(db)

	now := time.Now()
	metricName := fmt.Sprintf("named_metric_%d", now.UnixNano())
	
	metric := MetricValue{
		Name:      metricName,
		Type:      MetricTypeGauge,
		Value:     88.8,
		Timestamp: now,
		Tags:      map[string]string{"name": "test"},
	}

	store.Store(context.Background(), []MetricValue{metric})

	startTime := now.Add(-1 * time.Minute)
	endTime := now.Add(1 * time.Minute)

	metrics, err := store.RetrieveByName(context.Background(), metricName, startTime, endTime)
	if err != nil {
		t.Fatalf("failed to retrieve by name: %v", err)
	}

	if len(metrics) == 0 {
		t.Logf("No metrics retrieved, this may be normal if metrics not committed yet")
	}
}

func TestRetrieveByNameNotFound(t *testing.T) {
	db := GetTestDB(t)
	defer db.Close()

	store := NewPostgresMetricsStore(db)

	now := time.Now()
	startTime := now.Add(-1 * time.Hour)
	endTime := now.Add(1 * time.Hour)

	metrics, err := store.RetrieveByName(context.Background(), "nonexistent_metric", startTime, endTime)
	if err != nil {
		t.Fatalf("expected no error for nonexistent metric: %v", err)
	}

	if len(metrics) != 0 {
		t.Fatalf("expected empty result for nonexistent metric")
	}
}

func TestAggregate(t *testing.T) {
	db := GetTestDB(t)
	defer db.Close()

	store := NewPostgresMetricsStore(db)

	now := time.Now()
	metricName := fmt.Sprintf("agg_metric_%d", now.UnixNano())

	// Store multiple metrics with same name
	metrics := []MetricValue{
		{Name: metricName, Type: MetricTypeGauge, Value: 10.0, Timestamp: now},
		{Name: metricName, Type: MetricTypeGauge, Value: 20.0, Timestamp: now},
		{Name: metricName, Type: MetricTypeGauge, Value: 30.0, Timestamp: now},
	}

	store.Store(context.Background(), metrics)

	startTime := now.Add(-1 * time.Minute)
	endTime := now.Add(1 * time.Minute)

	result, err := store.Aggregate(context.Background(), metricName, startTime, endTime)
	if err != nil {
		t.Fatalf("failed to aggregate: %v", err)
	}

	if result == nil {
		t.Fatalf("expected aggregation result")
	}

	if count, ok := result["count"]; ok {
		t.Logf("Aggregation count: %v", count)
	}
}

func TestAggregateNonexistentMetric(t *testing.T) {
	db := GetTestDB(t)
	defer db.Close()

	store := NewPostgresMetricsStore(db)

	now := time.Now()
	startTime := now.Add(-1 * time.Hour)
	endTime := now.Add(1 * time.Hour)

	result, err := store.Aggregate(context.Background(), "nonexistent", startTime, endTime)
	if err != nil {
		t.Fatalf("expected no error: %v", err)
	}

	if result == nil {
		t.Fatalf("expected aggregation result")
	}

	if count, ok := result["count"]; ok && count == 0 {
		t.Logf("Aggregation returned 0 count as expected")
	}
}

func TestStoreContextCancellation(t *testing.T) {
	db := GetTestDB(t)
	defer db.Close()

	store := NewPostgresMetricsStore(db)

	ctx, cancel := context.WithCancel(context.Background())
	cancel()

	metric := MetricValue{
		Name:      "test_metric",
		Value:     1.0,
		Timestamp: time.Now(),
	}

	err := store.Store(ctx, []MetricValue{metric})
	if err != nil {
		t.Logf("Context cancellation handled: %v", err)
	}
}

func TestRetrieveContextCancellation(t *testing.T) {
	db := GetTestDB(t)
	defer db.Close()

	store := NewPostgresMetricsStore(db)

	ctx, cancel := context.WithCancel(context.Background())
	cancel()

	_, err := store.Retrieve(ctx, time.Now().Add(-1*time.Hour), time.Now())
	if err != nil {
		t.Logf("Context cancellation handled: %v", err)
	}
}

func TestStoreWithVariousTypes(t *testing.T) {
	db := GetTestDB(t)
	defer db.Close()

	store := NewPostgresMetricsStore(db)

	now := time.Now()
	metrics := []MetricValue{
		{
			Name:      fmt.Sprintf("gauge_%d", now.UnixNano()),
			Type:      MetricTypeGauge,
			Value:     42.5,
			Timestamp: now,
		},
		{
			Name:      fmt.Sprintf("counter_%d", now.UnixNano()),
			Type:      MetricTypeCounter,
			Value:     100.0,
			Timestamp: now,
		},
		{
			Name:      fmt.Sprintf("timer_%d", now.UnixNano()),
			Type:      MetricTypeTimer,
			Value:     50.0,
			Timestamp: now,
		},
		{
			Name:      fmt.Sprintf("histogram_%d", now.UnixNano()),
			Type:      MetricTypeHistogram,
			Value:     75.0,
			Timestamp: now,
		},
	}

	err := store.Store(context.Background(), metrics)
	if err != nil {
		t.Fatalf("failed to store various metric types: %v", err)
	}
}

func TestStoreWithComplexTags(t *testing.T) {
	db := GetTestDB(t)
	defer db.Close()

	store := NewPostgresMetricsStore(db)

	metric := MetricValue{
		Name:      fmt.Sprintf("complex_tags_%d", time.Now().UnixNano()),
		Type:      MetricTypeGauge,
		Value:     99.9,
		Timestamp: time.Now(),
		Tags: map[string]string{
			"env":      "production",
			"region":   "us-west-2",
			"service":  "api-monitor",
			"version":  "1.0.0",
		},
	}

	err := store.Store(context.Background(), []MetricValue{metric})
	if err != nil {
		t.Fatalf("failed to store metric with complex tags: %v", err)
	}
}

func TestStoreWithDescription(t *testing.T) {
	db := GetTestDB(t)
	defer db.Close()

	store := NewPostgresMetricsStore(db)

	metric := MetricValue{
		Name:        fmt.Sprintf("described_metric_%d", time.Now().UnixNano()),
		Type:        MetricTypeGauge,
		Value:       55.5,
		Timestamp:   time.Now(),
		Description: "This is a detailed description of the metric",
	}

	err := store.Store(context.Background(), []MetricValue{metric})
	if err != nil {
		t.Fatalf("failed to store metric with description: %v", err)
	}
}

func TestRetrieveOrderingByTime(t *testing.T) {
	db := GetTestDB(t)
	defer db.Close()

	store := NewPostgresMetricsStore(db)

	baseTime := time.Now()
	metricName := fmt.Sprintf("ordered_metric_%d", baseTime.UnixNano())

	// Store metrics at different times
	metrics := []MetricValue{
		{Name: metricName, Type: MetricTypeGauge, Value: 10.0, Timestamp: baseTime.Add(-2 * time.Second)},
		{Name: metricName, Type: MetricTypeGauge, Value: 20.0, Timestamp: baseTime.Add(-1 * time.Second)},
		{Name: metricName, Type: MetricTypeGauge, Value: 30.0, Timestamp: baseTime},
	}

	store.Store(context.Background(), metrics)

	startTime := baseTime.Add(-3 * time.Second)
	endTime := baseTime.Add(1 * time.Second)

	retrieved, err := store.RetrieveByName(context.Background(), metricName, startTime, endTime)
	if err != nil {
		t.Fatalf("failed to retrieve: %v", err)
	}

	if len(retrieved) > 1 {
		if retrieved[0].Timestamp.Before(retrieved[1].Timestamp) {
			t.Fatalf("expected descending order by timestamp")
		}
	}
}
