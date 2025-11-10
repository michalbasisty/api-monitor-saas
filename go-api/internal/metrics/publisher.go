package metrics

import (
	"context"
	"encoding/json"
	"fmt"
	"sync"

	"github.com/go-redis/redis/v8"
)

// RedisMetricsPublisher publishes metrics to Redis streams
type RedisMetricsPublisher struct {
	client   *redis.Client
	handlers []MetricsHandler
	mu       sync.RWMutex
	streamKey string
}

// NewRedisMetricsPublisher creates a new Redis metrics publisher
func NewRedisMetricsPublisher(client *redis.Client, streamKey string) *RedisMetricsPublisher {
	if streamKey == "" {
		streamKey = "metrics:stream"
	}

	return &RedisMetricsPublisher{
		client:    client,
		handlers:  make([]MetricsHandler, 0),
		streamKey: streamKey,
	}
}

// Publish sends metrics to interested subscribers
func (p *RedisMetricsPublisher) Publish(ctx context.Context, metrics []MetricValue) error {
	if len(metrics) == 0 {
		return nil
	}

	// Publish to Redis stream
	err := p.publishToRedis(ctx, metrics)
	if err != nil {
		return fmt.Errorf("failed to publish to Redis: %w", err)
	}

	// Call local handlers
	p.mu.RLock()
	handlers := p.handlers
	p.mu.RUnlock()

	for _, handler := range handlers {
		go func(h MetricsHandler) {
			_ = h(ctx, metrics)
		}(handler)
	}

	return nil
}

// Subscribe registers a handler for metric updates
func (p *RedisMetricsPublisher) Subscribe(handler MetricsHandler) error {
	if handler == nil {
		return fmt.Errorf("handler cannot be nil")
	}

	p.mu.Lock()
	defer p.mu.Unlock()

	p.handlers = append(p.handlers, handler)
	return nil
}

// Unsubscribe removes a handler
func (p *RedisMetricsPublisher) Unsubscribe(handler MetricsHandler) error {
	p.mu.Lock()
	defer p.mu.Unlock()

	for i, h := range p.handlers {
		if fmt.Sprintf("%p", h) == fmt.Sprintf("%p", handler) {
			p.handlers = append(p.handlers[:i], p.handlers[i+1:]...)
			return nil
		}
	}

	return fmt.Errorf("handler not found")
}

// publishToRedis publishes metrics to Redis stream
func (p *RedisMetricsPublisher) publishToRedis(ctx context.Context, metrics []MetricValue) error {
	for _, metric := range metrics {
		data := map[string]interface{}{
			"name":        metric.Name,
			"type":        metric.Type,
			"value":       metric.Value,
			"timestamp":   metric.Timestamp.Unix(),
			"description": metric.Description,
		}

		// Add tags as individual fields
		for k, v := range metric.Tags {
			data["tag_"+k] = v
		}

		// Add to Redis stream
		err := p.client.XAdd(ctx, &redis.XAddArgs{
			Stream: p.streamKey,
			Values: data,
		}).Err()

		if err != nil {
			return fmt.Errorf("failed to add metric to stream: %w", err)
		}

		// Also publish to a pub/sub channel for real-time updates
		metricsJSON, err := json.Marshal(metric)
		if err != nil {
			return fmt.Errorf("failed to marshal metric: %w", err)
		}

		channel := fmt.Sprintf("metrics:%s", metric.Name)
		err = p.client.Publish(ctx, channel, string(metricsJSON)).Err()
		if err != nil {
			return fmt.Errorf("failed to publish to channel: %w", err)
		}
	}

	return nil
}

// GetHandlerCount returns the number of registered handlers
func (p *RedisMetricsPublisher) GetHandlerCount() int {
	p.mu.RLock()
	defer p.mu.RUnlock()

	return len(p.handlers)
}
