package container

import (
	"context"
	"fmt"
	"time"

	"api-monitor-go/internal/config"
	"api-monitor-go/internal/database"
	"api-monitor-go/internal/logger"
	"api-monitor-go/internal/middleware"
	"api-monitor-go/internal/monitoring"
	"api-monitor-go/internal/websocket"
	"github.com/redis/go-redis/v9"
)

// Container holds all application dependencies
type Container struct {
	config      *config.Config
	db          *database.DB
	redis       *redis.Client
	repo        *database.Repository
	wsHub       *websocket.Hub
	monitorSvc  *monitoring.Service
	rateLimiter *middleware.RateLimiter
	logger      *logger.Logger
	shutdownFns []func(context.Context) error
}

// NewContainer initializes all dependencies in correct order
func NewContainer() (*Container, error) {
	c := &Container{
		shutdownFns: make([]func(context.Context) error, 0),
	}

	// Load configuration
	c.config = config.Load()

	// Initialize logger
	if err := c.initLogger(); err != nil {
		return nil, fmt.Errorf("logger initialization failed: %w", err)
	}

	// Initialize database
	if err := c.initDatabase(); err != nil {
		return nil, fmt.Errorf("database initialization failed: %w", err)
	}

	// Initialize Redis
	if err := c.initRedis(); err != nil {
		return nil, fmt.Errorf("redis initialization failed: %w", err)
	}

	// Initialize repository
	c.initRepository()

	// Initialize WebSocket hub
	c.initWebSocketHub()

	// Initialize monitoring service
	if err := c.initMonitoringService(); err != nil {
		return nil, fmt.Errorf("monitoring service initialization failed: %w", err)
	}

	// Initialize rate limiter
	if err := c.initRateLimiter(); err != nil {
		return nil, fmt.Errorf("rate limiter initialization failed: %w", err)
	}

	c.logger.Info("container initialization completed successfully")
	return c, nil
}

// initLogger initializes the logger
func (c *Container) initLogger() error {
	c.logger = logger.New()
	c.logger.SetLevel(logger.LevelInfo)

	c.shutdownFns = append(c.shutdownFns, func(ctx context.Context) error {
		c.logger.Info("logger shutdown")
		return nil
	})

	return nil
}

// initDatabase initializes database connection
func (c *Container) initDatabase() error {
	if c.config.DatabaseURL == "" {
		return fmt.Errorf("DATABASE_URL environment variable is required")
	}

	db, err := database.NewDB()
	if err != nil {
		return err
	}

	c.db = db
	c.logger.Info("database connected successfully")

	c.shutdownFns = append(c.shutdownFns, func(ctx context.Context) error {
		return c.db.Close()
	})

	return nil
}

// initRedis initializes Redis connection
func (c *Container) initRedis() error {
	rdb := redis.NewClient(&redis.Options{
		Addr: c.config.RedisHost + ":" + c.config.RedisPort,
		DB:   c.config.RedisDB,
	})

	// Test connection
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	if err := rdb.Ping(ctx).Err(); err != nil {
		return fmt.Errorf("failed to connect to Redis: %w", err)
	}

	c.redis = rdb
	c.logger.Info("redis connected successfully")

	c.shutdownFns = append(c.shutdownFns, func(ctx context.Context) error {
		return c.redis.Close()
	})

	return nil
}

// initRepository initializes the database repository
func (c *Container) initRepository() {
	c.repo = database.NewRepository(c.db.Postgres)
	c.logger.Info("repository initialized")
}

// initWebSocketHub initializes the WebSocket hub
func (c *Container) initWebSocketHub() {
	c.wsHub = websocket.NewHub()
	go c.wsHub.Run()
	c.logger.Info("websocket hub initialized and running")
}

// initMonitoringService initializes the monitoring service
func (c *Container) initMonitoringService() error {
	svc := monitoring.NewService(
		c.repo,
		c.wsHub,
		c.redis,
		c.config.SymfonyAPIURL,
		c.config.HTTPClientTimeout,
	)

	c.monitorSvc = svc
	c.logger.Info("monitoring service initialized")

	return nil
}

// initRateLimiter initializes the rate limiter middleware
func (c *Container) initRateLimiter() error {
	limiter := middleware.NewRateLimiter(10, 10, 5*time.Minute)
	c.rateLimiter = limiter
	c.logger.Info("rate limiter initialized")

	c.shutdownFns = append(c.shutdownFns, func(ctx context.Context) error {
		c.rateLimiter.Close()
		return nil
	})

	return nil
}

// Getters for dependencies

func (c *Container) Config() *config.Config {
	return c.config
}

func (c *Container) DB() *database.DB {
	return c.db
}

func (c *Container) Redis() *redis.Client {
	return c.redis
}

func (c *Container) Repository() *database.Repository {
	return c.repo
}

func (c *Container) WebSocketHub() *websocket.Hub {
	return c.wsHub
}

func (c *Container) MonitoringService() *monitoring.Service {
	return c.monitorSvc
}

func (c *Container) RateLimiter() *middleware.RateLimiter {
	return c.rateLimiter
}

func (c *Container) Logger() *logger.Logger {
	return c.logger
}

// Shutdown gracefully shuts down all dependencies in reverse order
func (c *Container) Shutdown(ctx context.Context) error {
	c.logger.Info("container shutdown started")

	// Execute shutdown functions in reverse order
	for i := len(c.shutdownFns) - 1; i >= 0; i-- {
		if err := c.shutdownFns[i](ctx); err != nil {
			c.logger.Errorf("shutdown error: %v", err)
		}
	}

	c.logger.Info("container shutdown completed")
	return nil
}

// Close is a convenience method for immediate shutdown
func (c *Container) Close() error {
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	return c.Shutdown(ctx)
}
