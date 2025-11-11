package config

import (
	"os"
	"strconv"
	"time"
)

// Config holds all application configuration
type Config struct {
	// Server
	Port string

	// Database
	DatabaseURL string

	// Redis
	RedisHost string
	RedisPort string
	RedisDB   int

	// External APIs
	SymfonyAPIURL string

	// Frontend
	FrontendURL string

	// Monitoring
	MonitoringTimeout time.Duration
	HTTPClientTimeout time.Duration

	// Redis Streams
	MetricsStream string
	AlertsStream  string
}

// Load loads configuration from environment variables with defaults
func Load() *Config {
	return &Config{
		Port:                  getEnv("GO_API_PORT", "8080"),
		DatabaseURL:           getEnv("DATABASE_URL", ""),
		RedisHost:             getEnv("REDIS_HOST", "redis"),
		RedisPort:             getEnv("REDIS_PORT", "6379"),
		RedisDB:               getEnvInt("REDIS_DB", 0),
		SymfonyAPIURL:         getEnv("SYMFONY_API_URL", "http://symfony:8000"),
		FrontendURL:           getEnv("FRONTEND_URL", "http://localhost"),
		MonitoringTimeout:     getEnvDuration("MONITORING_TIMEOUT", 30*time.Second),
		HTTPClientTimeout:     getEnvDuration("HTTP_CLIENT_TIMEOUT", 30*time.Second),
		MetricsStream:         "api-metrics",
		AlertsStream:          "alerts-fired",
	}
}

// Helper functions
func getEnv(key, defaultVal string) string {
	if value, exists := os.LookupEnv(key); exists {
		return value
	}
	return defaultVal
}

func getEnvInt(key string, defaultVal int) int {
	if value, exists := os.LookupEnv(key); exists {
		if intVal, err := strconv.Atoi(value); err == nil {
			return intVal
		}
	}
	return defaultVal
}

func getEnvDuration(key string, defaultVal time.Duration) time.Duration {
	if value, exists := os.LookupEnv(key); exists {
		if duration, err := time.ParseDuration(value); err == nil {
			return duration
		}
	}
	return defaultVal
}
