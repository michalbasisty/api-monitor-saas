package resilience

import (
	"context"
	"errors"
	"testing"
	"time"
)

// TestIsRetryableError tests the IsRetryableError function with various error patterns
func TestIsRetryableError(t *testing.T) {
	tests := []struct {
		name     string
		err      error
		expected bool
	}{
		{
			name:     "nil error should not be retryable",
			err:      nil,
			expected: false,
		},
		{
			name:     "connection refused should be retryable",
			err:      errors.New("connection refused"),
			expected: true,
		},
		{
			name:     "connection refused uppercase should be retryable",
			err:      errors.New("Connection Refused"),
			expected: true,
		},
		{
			name:     "connection reset should be retryable",
			err:      errors.New("connection reset"),
			expected: true,
		},
		{
			name:     "i/o timeout should be retryable",
			err:      errors.New("i/o timeout"),
			expected: true,
		},
		{
			name:     "temporary failure should be retryable",
			err:      errors.New("temporary failure"),
			expected: true,
		},
		{
			name:     "deadline exceeded should be retryable",
			err:      errors.New("deadline exceeded"),
			expected: true,
		},
		{
			name:     "no such host should be retryable",
			err:      errors.New("no such host"),
			expected: true,
		},
		{
			name:     "broken pipe should be retryable",
			err:      errors.New("broken pipe"),
			expected: true,
		},
		{
			name:     "network unreachable should be retryable",
			err:      errors.New("network unreachable"),
			expected: true,
		},
		{
			name:     "unknown error should not be retryable",
			err:      errors.New("some unknown error"),
			expected: false,
		},
		{
			name:     "database error should not be retryable",
			err:      errors.New("database constraint violation"),
			expected: false,
		},
		{
			name:     "invalid input should not be retryable",
			err:      errors.New("invalid input format"),
			expected: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := IsRetryableError(tt.err)
			if result != tt.expected {
				t.Errorf("IsRetryableError(%v) = %v, want %v", tt.err, result, tt.expected)
			}
		})
	}
}

// TestRetryerDo tests the Do method with successful and failing functions
func TestRetryerDo(t *testing.T) {
	tests := []struct {
		name           string
		config         RetryConfig
		fn             func() error
		expectedError  bool
		expectedCalls  int
	}{
		{
			name: "successful on first attempt",
			config: RetryConfig{
				MaxAttempts:  3,
				InitialDelay: 1 * time.Millisecond,
				MaxDelay:     10 * time.Millisecond,
				Multiplier:   2.0,
				Jitter:       false,
			},
			fn: func() error {
				return nil
			},
			expectedError: false,
			expectedCalls: 1,
		},
		{
			name: "successful after retries",
			config: RetryConfig{
				MaxAttempts:  3,
				InitialDelay: 1 * time.Millisecond,
				MaxDelay:     10 * time.Millisecond,
				Multiplier:   2.0,
				Jitter:       false,
			},
			fn: func() error {
				var callCount int
				return func() error {
					callCount++
					if callCount < 3 {
						return errors.New("temporary error")
					}
					return nil
				}()
			},
			expectedError: false,
			expectedCalls: 1,
		},
		{
			name: "fails after max retries",
			config: RetryConfig{
				MaxAttempts:  3,
				InitialDelay: 1 * time.Millisecond,
				MaxDelay:     10 * time.Millisecond,
				Multiplier:   2.0,
				Jitter:       false,
			},
			fn: func() error {
				return errors.New("persistent error")
			},
			expectedError: true,
			expectedCalls: 3,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			retrier := NewRetrier(tt.config)
			err := retrier.Do(tt.fn)

			if (err != nil) != tt.expectedError {
				t.Errorf("Retrier.Do() error = %v, wantErr %v", err, tt.expectedError)
			}
		})
	}
}

// TestRetryerDoWithContext tests the DoWithContext method
func TestRetryerDoWithContext(t *testing.T) {
	tests := []struct {
		name          string
		config        RetryConfig
		setupContext  func() (context.Context, context.CancelFunc)
		fn            func(ctx context.Context) error
		expectedError bool
		errorType     string
	}{
		{
			name: "successful with context",
			config: RetryConfig{
				MaxAttempts:  3,
				InitialDelay: 1 * time.Millisecond,
				MaxDelay:     10 * time.Millisecond,
				Multiplier:   2.0,
				Jitter:       false,
			},
			setupContext: func() (context.Context, context.CancelFunc) {
				return context.WithTimeout(context.Background(), 5*time.Second)
			},
			fn: func(ctx context.Context) error {
				return nil
			},
			expectedError: false,
		},
		{
			name: "context cancelled before completion",
			config: RetryConfig{
				MaxAttempts:  5,
				InitialDelay: 10 * time.Millisecond,
				MaxDelay:     100 * time.Millisecond,
				Multiplier:   2.0,
				Jitter:       false,
			},
			setupContext: func() (context.Context, context.CancelFunc) {
				return context.WithTimeout(context.Background(), 15*time.Millisecond)
			},
			fn: func(ctx context.Context) error {
				return errors.New("temporary error")
			},
			expectedError: true,
			errorType:     "context.deadlineExceeded",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			ctx, cancel := tt.setupContext()
			defer cancel()

			retrier := NewRetrier(tt.config)
			err := retrier.DoWithContext(ctx, tt.fn)

			if (err != nil) != tt.expectedError {
				t.Errorf("Retrier.DoWithContext() error = %v, wantErr %v", err, tt.expectedError)
			}
		})
	}
}

// TestCalculateDelay tests exponential backoff calculation
func TestCalculateDelay(t *testing.T) {
	config := RetryConfig{
		InitialDelay: 100 * time.Millisecond,
		MaxDelay:     1000 * time.Millisecond,
		Multiplier:   2.0,
		Jitter:       false,
	}

	retrier := NewRetrier(config)

	tests := []struct {
		attempt int
		minDuration time.Duration
		maxDuration time.Duration
	}{
		{
			attempt:     0,
			minDuration: 100 * time.Millisecond,
			maxDuration: 100 * time.Millisecond,
		},
		{
			attempt:     1,
			minDuration: 200 * time.Millisecond,
			maxDuration: 200 * time.Millisecond,
		},
		{
			attempt:     2,
			minDuration: 400 * time.Millisecond,
			maxDuration: 400 * time.Millisecond,
		},
		{
			attempt:     3,
			minDuration: 800 * time.Millisecond,
			maxDuration: 800 * time.Millisecond,
		},
		{
			attempt:     4, // Should be capped at maxDelay (1000ms)
			minDuration: 1000 * time.Millisecond,
			maxDuration: 1000 * time.Millisecond,
		},
	}

	for _, tt := range tests {
		t.Run("attempt_"+string(rune(tt.attempt)), func(t *testing.T) {
			delay := retrier.calculateDelay(tt.attempt)

			if delay < tt.minDuration || delay > tt.maxDuration {
				t.Errorf("calculateDelay(%d) = %v, want between %v and %v", 
					tt.attempt, delay, tt.minDuration, tt.maxDuration)
			}
		})
	}
}

// TestCalculateDelayWithJitter tests exponential backoff with jitter
func TestCalculateDelayWithJitter(t *testing.T) {
	config := RetryConfig{
		InitialDelay: 100 * time.Millisecond,
		MaxDelay:     1000 * time.Millisecond,
		Multiplier:   2.0,
		Jitter:       true,
	}

	retrier := NewRetrier(config)

	// Test jitter is applied (run multiple times to verify randomness)
	results := make(map[time.Duration]int)
	for i := 0; i < 10; i++ {
		delay := retrier.calculateDelay(1)
		results[delay]++
	}

	// With jitter, we should get different values (at least 2 different)
	if len(results) < 2 {
		t.Errorf("calculateDelay with jitter produced only %d unique values, expected at least 2", len(results))
	}
}
