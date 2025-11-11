package main

import (
	"context"
	"fmt"
	"net/http"
	"os"
	"os/signal"
	"sync/atomic"
	"syscall"
	"time"

	"api-monitor-go/internal/container"
	"api-monitor-go/internal/logger"
	"api-monitor-go/internal/middleware"
	"github.com/gorilla/websocket"
	"github.com/joho/godotenv"
)

var isHealthy int32 = 1

func main() {
	// Load environment variables
	if err := godotenv.Load("../../.env"); err != nil {
		// Not critical, continue with env variables
	}

	// Initialize container with all dependencies
	cnt, err := container.NewContainer()
	if err != nil {
		// Cannot use logger before container init
		fmt.Fprintf(os.Stderr, "failed to initialize container: %v\n", err)
		os.Exit(1)
	}
	defer cnt.Close()

	log := cnt.Logger()

	// Setup HTTP handlers
	mux := http.NewServeMux()

	// Health check endpoint (no rate limiting)
	mux.HandleFunc("/health", handleHealth())

	// WebSocket endpoint (with rate limiting)
	mux.HandleFunc("/ws", middleware.RateLimitMiddleware(cnt.RateLimiter(), 10, 10)(
		http.HandlerFunc(handleWebSocket(cnt)),
	).ServeHTTP)

	// Monitoring endpoint (with rate limiting)
	mux.HandleFunc("/monitor", middleware.RateLimitMiddleware(cnt.RateLimiter(), 10, 10)(
		http.HandlerFunc(handleMonitor(cnt)),
	).ServeHTTP)

	// Create HTTP server with timeouts
	server := &http.Server{
		Addr:         ":" + cnt.Config().Port,
		Handler:      mux,
		ReadTimeout:  15 * time.Second,
		WriteTimeout: 15 * time.Second,
		IdleTimeout:  60 * time.Second,
	}

	// Start server in goroutine
	go func() {
		log.Infof("Go API server starting on port %s", cnt.Config().Port)
		if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Errorf("server error: %v", err)
			atomic.StoreInt32(&isHealthy, 0)
		}
	}()

	// Wait for shutdown signal
	sigChan := make(chan os.Signal, 1)
	signal.Notify(sigChan, os.Interrupt, syscall.SIGTERM)
	<-sigChan

	log.Info("shutting down server...")
	atomic.StoreInt32(&isHealthy, 0)

	// Graceful shutdown with timeout
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()

	if err := server.Shutdown(ctx); err != nil {
		log.Errorf("server shutdown error: %v", err)
	}

	log.Info("server stopped")
}

// handleWebSocket handles WebSocket upgrade and registration
func handleWebSocket(cnt *container.Container) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		log := cnt.Logger().WithField("endpoint", "/ws")

		upgrader := websocket.Upgrader{
			CheckOrigin: checkOrigin(cnt),
		}

		conn, err := upgrader.Upgrade(w, r, nil)
		if err != nil {
			log.Errorf("WebSocket upgrade failed: %v", err)
			http.Error(w, "WebSocket upgrade failed", http.StatusBadRequest)
			return
		}

		// Set read/write deadlines for WebSocket
		conn.SetReadDeadline(time.Now().Add(60 * time.Second))
		conn.SetWriteDeadline(time.Now().Add(60 * time.Second))

		cnt.WebSocketHub().Register(conn)
		log.Info("WebSocket client connected")
	}
}

// handleMonitor handles the monitoring trigger endpoint
func handleMonitor(cnt *container.Container) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		log := cnt.Logger().WithField("endpoint", "/monitor")

		// Run monitoring asynchronously
		go func() {
			if err := cnt.MonitoringService().MonitorEndpoints(); err != nil {
				log.Errorf("monitoring failed: %v", err)
			}
		}()

		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusAccepted)
		fmt.Fprintf(w, `{"status":"monitoring started"}`)
	}
}

// handleHealth handles the health check endpoint
func handleHealth() http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")

		healthy := atomic.LoadInt32(&isHealthy) == 1

		if !healthy {
			w.WriteHeader(http.StatusServiceUnavailable)
			fmt.Fprintf(w, `{"status":"unhealthy"}`)
			return
		}

		w.WriteHeader(http.StatusOK)
		fmt.Fprintf(w, `{"status":"healthy"}`)
	}
}

// checkOrigin returns a function that validates CORS origins
func checkOrigin(cnt *container.Container) func(*http.Request) bool {
	return func(r *http.Request) bool {
		origin := r.Header.Get("Origin")
		allowedOrigins := []string{
			"http://localhost",
			"http://localhost:80",
			"http://localhost:4200",
			"http://localhost:3000",
			"http://angular:80",
		}

		// Add FRONTEND_URL from config if set
		if cnt.Config().FrontendURL != "" && cnt.Config().FrontendURL != "http://localhost" {
			allowedOrigins = append(allowedOrigins, cnt.Config().FrontendURL)
		}

		for _, allowed := range allowedOrigins {
			if origin == allowed {
				return true
			}
		}
		return false
	}
}
