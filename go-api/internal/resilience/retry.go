package resilience

import (
	"context"
	"fmt"
	"math"
	"math/rand"
	"strings"
	"time"
)

// RetryConfig holds retry configuration
type RetryConfig struct {
	MaxAttempts int
	InitialDelay time.Duration
	MaxDelay     time.Duration
	Multiplier   float64
	Jitter       bool
}

// DefaultRetryConfig returns default retry configuration
func DefaultRetryConfig() RetryConfig {
	return RetryConfig{
		MaxAttempts:  3,
		InitialDelay: 100 * time.Millisecond,
		MaxDelay:     10 * time.Second,
		Multiplier:   2.0,
		Jitter:       true,
	}
}

// Retrier handles retry logic with exponential backoff
type Retrier struct {
	config RetryConfig
}

// NewRetrier creates a new retrier with custom config
func NewRetrier(config RetryConfig) *Retrier {
	if config.MaxAttempts <= 0 {
		config.MaxAttempts = 3
	}
	if config.InitialDelay <= 0 {
		config.InitialDelay = 100 * time.Millisecond
	}
	if config.MaxDelay <= 0 {
		config.MaxDelay = 10 * time.Second
	}
	if config.Multiplier <= 0 {
		config.Multiplier = 2.0
	}

	return &Retrier{config: config}
}

// DefaultRetrier creates a retrier with default configuration
func DefaultRetrier() *Retrier {
	return NewRetrier(DefaultRetryConfig())
}

// Do executes the function with retry logic
func (r *Retrier) Do(fn func() error) error {
	var lastErr error

	for attempt := 0; attempt < r.config.MaxAttempts; attempt++ {
		err := fn()
		if err == nil {
			return nil
		}

		lastErr = err

		// Don't wait after last attempt
		if attempt < r.config.MaxAttempts-1 {
			delay := r.calculateDelay(attempt)
			time.Sleep(delay)
		}
	}

	return fmt.Errorf("max retries (%d) exceeded: %w", r.config.MaxAttempts, lastErr)
}

// DoWithContext executes the function with retry logic and context
func (r *Retrier) DoWithContext(ctx context.Context, fn func(context.Context) error) error {
	var lastErr error

	for attempt := 0; attempt < r.config.MaxAttempts; attempt++ {
		// Check context before each attempt
		select {
		case <-ctx.Done():
			return ctx.Err()
		default:
		}

		err := fn(ctx)
		if err == nil {
			return nil
		}

		lastErr = err

		// Don't wait after last attempt
		if attempt < r.config.MaxAttempts-1 {
			delay := r.calculateDelay(attempt)

			// Use context-aware sleep
			select {
			case <-time.After(delay):
				// Continue to next attempt
			case <-ctx.Done():
				return ctx.Err()
			}
		}
	}

	return fmt.Errorf("max retries (%d) exceeded: %w", r.config.MaxAttempts, lastErr)
}

// calculateDelay calculates exponential backoff with optional jitter
func (r *Retrier) calculateDelay(attempt int) time.Duration {
	// Calculate exponential backoff: initial * (multiplier ^ attempt)
	delay := time.Duration(
		float64(r.config.InitialDelay) * math.Pow(r.config.Multiplier, float64(attempt)),
	)

	// Cap at max delay
	if delay > r.config.MaxDelay {
		delay = r.config.MaxDelay
	}

	// Add jitter if enabled
	if r.config.Jitter {
		// Add random jitter up to 25% of delay
		jitter := time.Duration(rand.Int63n(int64(delay / 4)))
		delay += jitter
	}

	return delay
}

// IsRetryableError determines if an error should trigger a retry
// This is a basic implementation - can be extended for specific error types
func IsRetryableError(err error) bool {
	if err == nil {
		return false
	}

	// Define retryable error patterns
	errStr := strings.ToLower(err.Error())

	// Network-related errors that should trigger retries
	retryablePatterns := []string{
		"connection refused",
		"connection reset",
		"i/o timeout",
		"temporary failure",
		"deadline exceeded",
		"no such host",
		"broken pipe",
		"connection timeout",
		"read timeout",
		"write timeout",
		"network unreachable",
		"too many open files",
	}

	for _, pattern := range retryablePatterns {
		if strings.Contains(errStr, pattern) {
			return true
		}
	}

	return false // Non-retryable by default
}
