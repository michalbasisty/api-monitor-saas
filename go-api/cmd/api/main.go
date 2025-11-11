package main

import (
	"context"
	"fmt"
	"net/http"
	"os"
	"os/signal"
	"sync"
	"sync/atomic"
	"syscall"
	"time"

	"api-monitor-go/internal/config"
	"api-monitor-go/internal/database"
	"api-monitor-go/internal/logger"
	"api-monitor-go/internal/middleware"
	"api-monitor-go/internal/monitoring"
	"api-monitor-go/internal/websocket"
	"github.com/joho/godotenv"
	"github.com/redis/go-redis/v9"
	gorillaWs "github.com/gorilla/websocket"
)

var upgrader = gorillaWs.Upgrader{
	CheckOrigin: func(r *http.Request) bool {
		origin := r.Header.Get("Origin")
		allowedOrigins := []string{
			"http://localhost",
			"http://localhost:80",
			"http://localhost:4200",
			"http://localhost:3000",
			"http://angular:80",
		}

		// Add FRONTEND_URL from config if set
		if cfg := config.Load(); cfg.FrontendURL != "" && cfg.FrontendURL != "http://localhost" {
			allowedOrigins = append(allowedOrigins, cfg.FrontendURL)
		}

		for _, allowed := range allowedOrigins {
			if origin == allowed {
				return true
			}
		}
		return false
	},
}

var (
	isHealthy int32 = 1
	mu        sync.RWMutex
)

func main() {
	// Load environment variables
	if err := godotenv.Load("../../.env"); err != nil {
		// Not critical, continue with env variables
	}

	// Initialize logger
	log := logger.New()
	log.SetLevel(logger.LevelInfo)

	// Load configuration
	cfg := config.Load()

	// Validate required configuration
	if cfg.DatabaseURL == "" {
		log.Fatal("DATABASE_URL environment variable is required")
	}

	// Initialize database
	db, err := database.NewDB()
	if err != nil {
		log.Fatalf("failed to initialize database: %v", err)
	}
	defer db.Close()

	// Initialize Redis client
	rdb := redis.NewClient(&redis.Options{
		Addr: cfg.RedisHost + ":" + cfg.RedisPort,
		DB:   cfg.RedisDB,
	})
	defer rdb.Close()

	// Test Redis connection
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	if err := rdb.Ping(ctx).Err(); err != nil {
		cancel()
		log.Fatalf("failed to connect to Redis: %v", err)
	}
	cancel()

	log.Info("database and Redis connections established")

	// Initialize repository
	repo := database.NewRepository(db.Postgres)

	// Initialize WebSocket hub
	hub := websocket.NewHub()
	go hub.Run()

	// Initialize monitoring service with config
	monitorService := monitoring.NewService(repo, hub, rdb, cfg.SymfonyAPIURL, cfg.HTTPClientTimeout)

	// Initialize rate limiter (10 requests per second per IP)
	rateLimiter := middleware.NewRateLimiter(10, 10, 5*time.Minute)
	defer rateLimiter.Close()

	// Setup HTTP handlers with middleware
	mux := http.NewServeMux()

	// Health check endpoint (no rate limiting)
	mux.HandleFunc("/health", handleHealth())

	// WebSocket endpoint (with rate limiting)
	mux.HandleFunc("/ws", middleware.RateLimitMiddleware(rateLimiter, 10, 10)(
		http.HandlerFunc(handleWebSocket(hub)),
	).ServeHTTP)

	// Monitoring endpoint (with rate limiting)
	mux.HandleFunc("/monitor", middleware.RateLimitMiddleware(rateLimiter, 10, 10)(
		http.HandlerFunc(handleMonitor(monitorService)),
	).ServeHTTP)

	// Create HTTP server with timeouts
	server := &http.Server{
		Addr:         ":" + cfg.Port,
		Handler:      mux,
		ReadTimeout:  15 * time.Second,
		WriteTimeout: 15 * time.Second,
		IdleTimeout:  60 * time.Second,
	}

	// Start server in goroutine
	go func() {
		log.Infof("Go API server starting on port %s", cfg.Port)
		if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			log.Errorf("server error: %v", err)
			atomic.StoreInt32(&isHealthy, 0)
		}
	}()

	// Graceful shutdown
	sigChan := make(chan os.Signal, 1)
	signal.Notify(sigChan, os.Interrupt, syscall.SIGTERM)
	<-sigChan

	log.Info("shutting down server...")
	atomic.StoreInt32(&isHealthy, 0)

	ctx, cancel = context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()

	if err := server.Shutdown(ctx); err != nil {
		log.Errorf("server shutdown error: %v", err)
	}

	log.Info("server stopped")
}

// handleWebSocket handles WebSocket upgrade and registration
func handleWebSocket(hub *websocket.Hub) http.HandlerFunc {
	log := logger.New()
	return func(w http.ResponseWriter, r *http.Request) {
		conn, err := upgrader.Upgrade(w, r, nil)
		if err != nil {
			log.Errorf("WebSocket upgrade failed: %v", err)
			http.Error(w, "WebSocket upgrade failed", http.StatusBadRequest)
			return
		}

		// Set read/write deadlines for WebSocket
		conn.SetReadDeadline(time.Now().Add(60 * time.Second))
		conn.SetWriteDeadline(time.Now().Add(60 * time.Second))

		hub.Register(conn)
		log.Info("WebSocket client connected")
	}
}

// handleMonitor handles the monitoring trigger endpoint
func handleMonitor(monitorService *monitoring.Service) http.HandlerFunc {
	log := logger.New()
	return func(w http.ResponseWriter, r *http.Request) {
		go func() {
			if err := monitorService.MonitorEndpoints(); err != nil {
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
		mu.RLock()
		healthy := atomic.LoadInt32(&isHealthy) == 1
		mu.RUnlock()

		w.Header().Set("Content-Type", "application/json")

		if !healthy {
			w.WriteHeader(http.StatusServiceUnavailable)
			fmt.Fprintf(w, `{"status":"unhealthy"}`)
			return
		}

		w.WriteHeader(http.StatusOK)
		fmt.Fprintf(w, `{"status":"healthy"}`)
	}
}
