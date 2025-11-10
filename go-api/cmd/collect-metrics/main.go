package main

import (
	"context"
	"database/sql"
	"flag"
	"fmt"
	"log"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/go-redis/redis/v8"
	"github.com/stripe/stripe-go/v72"

	"api-monitor-saas/internal/database"
	"api-monitor-saas/internal/metrics"
)

func main() {
	// Parse flags
	dbURL := flag.String("db", "", "Database connection URL")
	redisURL := flag.String("redis", "", "Redis connection URL")
	stripeKey := flag.String("stripe-key", "", "Stripe API key")
	interval := flag.Duration("interval", 5*time.Minute, "Collection interval")
	once := flag.Bool("once", false, "Run collection once and exit")

	flag.Parse()

	// Validate required flags
	if *dbURL == "" {
		fmt.Fprintf(os.Stderr, "Error: --db flag is required\n")
		os.Exit(1)
	}

	if *redisURL == "" {
		fmt.Fprintf(os.Stderr, "Error: --redis flag is required\n")
		os.Exit(1)
	}

	// Initialize database connection
	db, err := database.New(*dbURL)
	if err != nil {
		log.Fatalf("Failed to connect to database: %v", err)
	}
	defer db.Close()

	// Initialize Redis connection
	redisClient := redis.NewClient(&redis.Options{
		Addr: *redisURL,
	})

	if err := redisClient.Ping(context.Background()).Err(); err != nil {
		log.Fatalf("Failed to connect to Redis: %v", err)
	}
	defer redisClient.Close()

	log.Println("Metrics collection worker started")
	log.Printf("Interval: %v", *interval)

	// Create aggregator
	aggregator := metrics.NewMetricsAggregator(5)

	// Register collectors
	aggregator.AddCollector(metrics.NewSystemMetricsCollector())
	aggregator.AddCollector(metrics.NewMonitoringMetricsCollector(db))

	// Add Stripe collector if API key provided
	if *stripeKey != "" {
		stripe.Key = *stripeKey
		aggregator.AddCollector(metrics.NewStripeMetricsCollector(*stripeKey))
	}

	// Create store
	store := metrics.NewPostgresMetricsStore(db)

	// Create publisher
	publisher := metrics.NewRedisMetricsPublisher(redisClient, "metrics:stream")

	// Add logging handler
	publisher.Subscribe(func(ctx context.Context, metrics []metrics.MetricValue) error {
		for _, m := range metrics {
			log.Printf("Published metric: %s = %.2f", m.Name, m.Value)
		}
		return nil
	})

	// Run once if requested
	if *once {
		err := collectMetrics(context.Background(), aggregator, store, publisher)
		if err != nil {
			log.Printf("Collection error: %v", err)
			os.Exit(1)
		}
		return
	}

	// Run continuously
	ticker := time.NewTicker(*interval)
	defer ticker.Stop()

	// Set up signal handling
	sigChan := make(chan os.Signal, 1)
	signal.Notify(sigChan, syscall.SIGINT, syscall.SIGTERM)

	// Collect immediately on startup
	err = collectMetrics(context.Background(), aggregator, store, publisher)
	if err != nil {
		log.Printf("Initial collection error: %v", err)
	}

	for {
		select {
		case <-ticker.C:
			ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
			err := collectMetrics(ctx, aggregator, store, publisher)
			cancel()

			if err != nil {
				log.Printf("Collection error: %v", err)
			}

		case sig := <-sigChan:
			log.Printf("Received signal: %v", sig)
			return
		}
	}
}

// collectMetrics performs the metrics collection cycle
func collectMetrics(
	ctx context.Context,
	aggregator metrics.MetricsAggregator,
	store metrics.MetricsStore,
	publisher metrics.MetricsPublisher,
) error {
	log.Println("Starting metrics collection...")

	startTime := time.Now()

	// Collect all metrics
	allMetrics, err := aggregator.CollectAll(ctx)
	if err != nil {
		return fmt.Errorf("collection failed: %w", err)
	}

	// Flatten metrics for storage and publishing
	var flatMetrics []metrics.MetricValue
	for collectorName, metricsList := range allMetrics {
		log.Printf("Collector '%s' collected %d metrics", collectorName, len(metricsList))
		flatMetrics = append(flatMetrics, metricsList...)
	}

	// Store metrics
	if err := store.Store(ctx, flatMetrics); err != nil {
		return fmt.Errorf("storage failed: %w", err)
	}

	// Publish metrics
	if err := publisher.Publish(ctx, flatMetrics); err != nil {
		return fmt.Errorf("publishing failed: %w", err)
	}

	elapsed := time.Since(startTime)
	log.Printf("Metrics collection completed: %d metrics in %v", len(flatMetrics), elapsed)

	return nil
}
