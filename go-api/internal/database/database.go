package database

import (
	"context"
	"database/sql"
	"fmt"
	"os"

	_ "github.com/lib/pq"
	"github.com/redis/go-redis/v9"
)

type DB struct {
	Postgres *sql.DB
	Redis    *redis.Client
}

func NewDB() (*DB, error) {
	// PostgreSQL connection
	host := getEnv("POSTGRES_HOST", "postgres")
	port := getEnv("POSTGRES_PORT", "5432")
	user := getEnv("POSTGRES_USER", "appuser")
	password := getEnv("POSTGRES_PASSWORD", "")
	dbname := getEnv("POSTGRES_DB", "apimon")

	connStr := fmt.Sprintf("host=%s port=%s user=%s password=%s dbname=%s sslmode=disable",
		host, port, user, password, dbname)

	pg, err := sql.Open("postgres", connStr)
	if err != nil {
		return nil, fmt.Errorf("failed to connect to postgres: %w", err)
	}

	if err = pg.Ping(); err != nil {
		return nil, fmt.Errorf("failed to ping postgres: %w", err)
	}

	// Redis connection
	rHost := getEnv("REDIS_HOST", "redis")
	rPort := getEnv("REDIS_PORT", "6379")
	addr := fmt.Sprintf("%s:%s", rHost, rPort)

	rdb := redis.NewClient(&redis.Options{
		Addr: addr,
	})

	if err := rdb.Ping(context.Background()).Err(); err != nil {
		// Redis connection failure is not fatal
		fmt.Printf("Redis connection failed: %v\n", err)
		rdb = nil
	}

	return &DB{
		Postgres: pg,
		Redis:    rdb,
	}, nil
}

func (db *DB) Close() error {
	if db.Postgres != nil {
		db.Postgres.Close()
	}
	if db.Redis != nil {
		db.Redis.Close()
	}
	return nil
}

func getEnv(key, defaultValue string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return defaultValue
}
