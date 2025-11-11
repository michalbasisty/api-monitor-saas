package middleware

import (
	"net"
	"net/http"
	"sync"
	"time"
)

// TokenBucket implements token bucket rate limiting algorithm
type TokenBucket struct {
	capacity      float64
	tokens        float64
	refillRate    float64
	lastRefillTime time.Time
	mu            sync.Mutex
}

// NewTokenBucket creates a new token bucket
func NewTokenBucket(capacity, refillRate float64) *TokenBucket {
	return &TokenBucket{
		capacity:       capacity,
		tokens:         capacity,
		refillRate:     refillRate,
		lastRefillTime: time.Now(),
	}
}

// Allow checks if a token can be consumed
func (tb *TokenBucket) Allow() bool {
	tb.mu.Lock()
	defer tb.mu.Unlock()

	// Refill tokens based on elapsed time
	now := time.Now()
	elapsed := now.Sub(tb.lastRefillTime).Seconds()
	tb.tokens += elapsed * tb.refillRate

	// Cap tokens at capacity
	if tb.tokens > tb.capacity {
		tb.tokens = tb.capacity
	}

	tb.lastRefillTime = now

	// Check if we have at least 1 token
	if tb.tokens >= 1 {
		tb.tokens--
		return true
	}

	return false
}

// RateLimiter limits requests per IP address
type RateLimiter struct {
	buckets map[string]*TokenBucket
	capacity float64
	refillRate float64
	cleanupInterval time.Duration
	mu      sync.RWMutex
	quit    chan struct{}
}

// NewRateLimiter creates a new rate limiter
// capacity: max requests per second per IP
// refillRate: tokens refilled per second
func NewRateLimiter(capacity, refillRate float64, cleanupInterval time.Duration) *RateLimiter {
	rl := &RateLimiter{
		buckets:         make(map[string]*TokenBucket),
		capacity:        capacity,
		refillRate:      refillRate,
		cleanupInterval: cleanupInterval,
		quit:            make(chan struct{}),
	}

	// Start cleanup goroutine
	go rl.cleanup()

	return rl
}

// Allow checks if request from IP is allowed
func (rl *RateLimiter) Allow(ip string) bool {
	rl.mu.Lock()
	bucket, exists := rl.buckets[ip]
	if !exists {
		bucket = NewTokenBucket(rl.capacity, rl.refillRate)
		rl.buckets[ip] = bucket
	}
	rl.mu.Unlock()

	return bucket.Allow()
}

// cleanup periodically removes old buckets
func (rl *RateLimiter) cleanup() {
	ticker := time.NewTicker(rl.cleanupInterval)
	defer ticker.Stop()

	for {
		select {
		case <-ticker.C:
			rl.mu.Lock()
			// Remove buckets that haven't been accessed recently
			now := time.Now()
			for ip, bucket := range rl.buckets {
				if now.Sub(bucket.lastRefillTime) > 5*time.Minute {
					delete(rl.buckets, ip)
				}
			}
			rl.mu.Unlock()
		case <-rl.quit:
			return
		}
	}
}

// Close stops the rate limiter
func (rl *RateLimiter) Close() {
	close(rl.quit)
}

// RateLimitMiddleware creates HTTP middleware for rate limiting
func RateLimitMiddleware(limiter *RateLimiter, rateLimit, refillRate float64) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			// Extract client IP
			ip := getClientIP(r)

			// Check rate limit
			if !limiter.Allow(ip) {
				w.Header().Set("Content-Type", "application/json")
				w.Header().Set("X-RateLimit-Limit", "10")
				w.Header().Set("X-RateLimit-Remaining", "0")
				w.WriteHeader(http.StatusTooManyRequests)
				w.Write([]byte(`{"error": "rate limit exceeded"}`))
				return
			}

			next.ServeHTTP(w, r)
		})
	}
}

// getClientIP extracts the client IP from request
func getClientIP(r *http.Request) string {
	// Check X-Forwarded-For header (for proxied requests)
	if forwarded := r.Header.Get("X-Forwarded-For"); forwarded != "" {
		// X-Forwarded-For can contain multiple IPs, get the first one
		ips := parseIPList(forwarded)
		if len(ips) > 0 {
			return ips[0]
		}
	}

	// Check X-Real-IP header
	if realIP := r.Header.Get("X-Real-IP"); realIP != "" {
		return realIP
	}

	// Fall back to remote address
	ip, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		return r.RemoteAddr
	}

	return ip
}

// parseIPList parses comma-separated IP list
func parseIPList(ips string) []string {
	var result []string
	for i := 0; i < len(ips); i++ {
		// Skip spaces
		for i < len(ips) && ips[i] == ' ' {
			i++
		}

		// Extract IP
		start := i
		for i < len(ips) && ips[i] != ',' {
			i++
		}

		if start < i {
			result = append(result, ips[start:i])
		}
	}

	return result
}
