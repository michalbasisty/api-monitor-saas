package resilience

import (
	"context"
	"errors"
	"fmt"
	"sync"
	"time"
)

// CircuitBreakerState represents the state of the circuit breaker
type CircuitBreakerState string

const (
	StateClosed   CircuitBreakerState = "closed"
	StateOpen     CircuitBreakerState = "open"
	StateHalfOpen CircuitBreakerState = "half-open"
)

// CircuitBreakerError is returned when circuit breaker is open
type CircuitBreakerError struct {
	name  string
	cause error
}

func (e *CircuitBreakerError) Error() string {
	return fmt.Sprintf("circuit breaker '%s' is open: %v", e.name, e.cause)
}

// CircuitBreakerConfig holds circuit breaker configuration
type CircuitBreakerConfig struct {
	Name          string
	MaxFailures   int
	Timeout       time.Duration
	ResetInterval time.Duration
}

// CircuitBreaker implements the circuit breaker pattern
type CircuitBreaker struct {
	config       CircuitBreakerConfig
	state        CircuitBreakerState
	failures      int
	lastFailTime  time.Time
	successCount  int
	mu            sync.RWMutex
}

// NewCircuitBreaker creates a new circuit breaker
func NewCircuitBreaker(config CircuitBreakerConfig) *CircuitBreaker {
	if config.MaxFailures <= 0 {
		config.MaxFailures = 5
	}
	if config.Timeout <= 0 {
		config.Timeout = 30 * time.Second
	}
	if config.ResetInterval <= 0 {
		config.ResetInterval = 60 * time.Second
	}

	return &CircuitBreaker{
		config: config,
		state:  StateClosed,
	}
}

// Execute runs the given function with circuit breaker protection
func (cb *CircuitBreaker) Execute(fn func() error) error {
	cb.mu.RLock()
	state := cb.state
	cb.mu.RUnlock()

	// If open, check if we should try half-open
	if state == StateOpen {
		cb.mu.RLock()
		timeSinceLastFail := time.Since(cb.lastFailTime)
		cb.mu.RUnlock()

		if timeSinceLastFail < cb.config.ResetInterval {
			return &CircuitBreakerError{
				name:  cb.config.Name,
				cause: errors.New("circuit breaker is open"),
			}
		}

		cb.mu.Lock()
		cb.state = StateHalfOpen
		cb.mu.Unlock()
	}

	// Execute the function
	err := fn()

	if err != nil {
		cb.recordFailure()
		return err
	}

	cb.recordSuccess()
	return nil
}

// recordFailure records a failure and updates circuit breaker state
func (cb *CircuitBreaker) recordFailure() {
	cb.mu.Lock()
	defer cb.mu.Unlock()

	cb.failures++
	cb.lastFailTime = time.Now()
	cb.successCount = 0

	if cb.state == StateHalfOpen || cb.failures >= cb.config.MaxFailures {
		cb.state = StateOpen
	}
}

// recordSuccess records a success and resets circuit breaker if in half-open state
func (cb *CircuitBreaker) recordSuccess() {
	cb.mu.Lock()
	defer cb.mu.Unlock()

	cb.successCount++

	if cb.state == StateHalfOpen {
		// Reset circuit breaker after successful half-open test
		if cb.successCount >= 2 {
			cb.failures = 0
			cb.state = StateClosed
			cb.successCount = 0
		}
	} else if cb.state == StateClosed {
		cb.failures = 0
	}
}

// GetState returns current circuit breaker state
func (cb *CircuitBreaker) GetState() CircuitBreakerState {
	cb.mu.RLock()
	defer cb.mu.RUnlock()
	return cb.state
}

// Reset manually resets the circuit breaker
func (cb *CircuitBreaker) Reset() {
	cb.mu.Lock()
	defer cb.mu.Unlock()

	cb.state = StateClosed
	cb.failures = 0
	cb.successCount = 0
	cb.lastFailTime = time.Time{}
}

// ExecuteWithContext runs the function with context and circuit breaker protection
func (cb *CircuitBreaker) ExecuteWithContext(ctx context.Context, fn func(context.Context) error) error {
	cb.mu.RLock()
	state := cb.state
	cb.mu.RUnlock()

	// If open, check if we should try half-open
	if state == StateOpen {
		cb.mu.RLock()
		timeSinceLastFail := time.Since(cb.lastFailTime)
		cb.mu.RUnlock()

		if timeSinceLastFail < cb.config.ResetInterval {
			return &CircuitBreakerError{
				name:  cb.config.Name,
				cause: errors.New("circuit breaker is open"),
			}
		}

		cb.mu.Lock()
		cb.state = StateHalfOpen
		cb.mu.Unlock()
	}

	// Execute the function
	err := fn(ctx)

	if err != nil {
		cb.recordFailure()
		return err
	}

	cb.recordSuccess()
	return nil
}
