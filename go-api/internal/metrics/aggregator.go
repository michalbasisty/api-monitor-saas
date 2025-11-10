package metrics

import (
	"context"
	"fmt"
	"sync"
)

// DefaultMetricsAggregator is the default implementation of MetricsAggregator
type DefaultMetricsAggregator struct {
	collectors map[string]MetricsCollector
	mu         sync.RWMutex
	maxWorkers int
}

// NewMetricsAggregator creates a new metrics aggregator
func NewMetricsAggregator(maxWorkers int) *DefaultMetricsAggregator {
	if maxWorkers <= 0 {
		maxWorkers = 5
	}

	return &DefaultMetricsAggregator{
		collectors: make(map[string]MetricsCollector),
		maxWorkers: maxWorkers,
	}
}

// AddCollector registers a new metrics collector
func (a *DefaultMetricsAggregator) AddCollector(collector MetricsCollector) error {
	if collector == nil {
		return fmt.Errorf("collector cannot be nil")
	}

	name := collector.Name()
	if name == "" {
		return fmt.Errorf("collector name cannot be empty")
	}

	a.mu.Lock()
	defer a.mu.Unlock()

	if _, exists := a.collectors[name]; exists {
		return fmt.Errorf("collector with name '%s' already exists", name)
	}

	a.collectors[name] = collector
	return nil
}

// RemoveCollector removes a collector by name
func (a *DefaultMetricsAggregator) RemoveCollector(name string) error {
	a.mu.Lock()
	defer a.mu.Unlock()

	if _, exists := a.collectors[name]; !exists {
		return fmt.Errorf("collector with name '%s' not found", name)
	}

	delete(a.collectors, name)
	return nil
}

// CollectAll gathers metrics from all registered collectors
func (a *DefaultMetricsAggregator) CollectAll(ctx context.Context) (map[string][]MetricValue, error) {
	a.mu.RLock()
	collectorList := make([]MetricsCollector, 0, len(a.collectors))
	collectorNames := make([]string, 0, len(a.collectors))

	for name, collector := range a.collectors {
		if collector.IsEnabled() {
			collectorList = append(collectorList, collector)
			collectorNames = append(collectorNames, name)
		}
	}
	a.mu.RUnlock()

	result := make(map[string][]MetricValue)
	resultMu := sync.Mutex{}

	// Use worker pool pattern for concurrent collection
	semaphore := make(chan struct{}, a.maxWorkers)
	var wg sync.WaitGroup
	errChan := make(chan error, len(collectorList))

	for i, collector := range collectorList {
		wg.Add(1)
		go func(idx int, c MetricsCollector) {
			defer wg.Done()

			semaphore <- struct{}{}
			defer func() { <-semaphore }()

			metrics, err := c.Collect(ctx)
			if err != nil {
				errChan <- fmt.Errorf("collector '%s' failed: %w", collectorNames[idx], err)
				return
			}

			resultMu.Lock()
			result[collectorNames[idx]] = metrics
			resultMu.Unlock()
		}(i, collector)
	}

	wg.Wait()
	close(errChan)

	// Collect first error if any
	var firstErr error
	for err := range errChan {
		if firstErr == nil {
			firstErr = err
		}
	}

	if firstErr != nil {
		return result, firstErr
	}

	return result, nil
}

// CollectFrom gathers metrics from a specific collector
func (a *DefaultMetricsAggregator) CollectFrom(ctx context.Context, collectorName string) ([]MetricValue, error) {
	a.mu.RLock()
	collector, exists := a.collectors[collectorName]
	a.mu.RUnlock()

	if !exists {
		return nil, fmt.Errorf("collector with name '%s' not found", collectorName)
	}

	if !collector.IsEnabled() {
		return []MetricValue{}, nil
	}

	return collector.Collect(ctx)
}

// GetCollectors returns list of registered collectors
func (a *DefaultMetricsAggregator) GetCollectors() []string {
	a.mu.RLock()
	defer a.mu.RUnlock()

	collectors := make([]string, 0, len(a.collectors))
	for name := range a.collectors {
		collectors = append(collectors, name)
	}

	return collectors
}
