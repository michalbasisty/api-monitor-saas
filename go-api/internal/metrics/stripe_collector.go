package metrics

import (
	"context"
	"fmt"
	"net/http"
	"time"

	"github.com/stripe/stripe-go/v72"
	"github.com/stripe/stripe-go/v72/charge"
	"github.com/stripe/stripe-go/v72/customer"
	"github.com/stripe/stripe-go/v72/subscription"
)

// StripeMetricsCollector collects metrics from Stripe API
type StripeMetricsCollector struct {
	name                string
	enabled             bool
	lastUpdateTime      time.Time
	collectionInterval  time.Duration
	apiKey              string
	httpClient          *http.Client
}

// NewStripeMetricsCollector creates a new Stripe metrics collector
func NewStripeMetricsCollector(apiKey string) *StripeMetricsCollector {
	stripe.Key = apiKey

	return &StripeMetricsCollector{
		name:                "stripe",
		enabled:             true,
		collectionInterval:  5 * time.Minute,
		apiKey:              apiKey,
		httpClient:          &http.Client{Timeout: 10 * time.Second},
	}
}

// Name returns the collector name
func (s *StripeMetricsCollector) Name() string {
	return s.name
}

// IsEnabled returns if the collector is enabled
func (s *StripeMetricsCollector) IsEnabled() bool {
	return s.enabled
}

// SetEnabled sets the enabled state
func (s *StripeMetricsCollector) SetEnabled(enabled bool) {
	s.enabled = enabled
}

// GetLastUpdateTime returns when metrics were last collected
func (s *StripeMetricsCollector) GetLastUpdateTime() time.Time {
	return s.lastUpdateTime
}

// GetCollectionInterval returns the collection interval
func (s *StripeMetricsCollector) GetCollectionInterval() time.Duration {
	return s.collectionInterval
}

// Collect gathers metrics from Stripe
func (s *StripeMetricsCollector) Collect(ctx context.Context) ([]MetricValue, error) {
	if !s.enabled {
		return []MetricValue{}, nil
	}

	var metrics []MetricValue

	// Collect customer metrics
	customerMetrics, err := s.collectCustomerMetrics(ctx)
	if err != nil {
		return nil, fmt.Errorf("failed to collect customer metrics: %w", err)
	}
	metrics = append(metrics, customerMetrics...)

	// Collect subscription metrics
	subscriptionMetrics, err := s.collectSubscriptionMetrics(ctx)
	if err != nil {
		return nil, fmt.Errorf("failed to collect subscription metrics: %w", err)
	}
	metrics = append(metrics, subscriptionMetrics...)

	// Collect charge metrics
	chargeMetrics, err := s.collectChargeMetrics(ctx)
	if err != nil {
		return nil, fmt.Errorf("failed to collect charge metrics: %w", err)
	}
	metrics = append(metrics, chargeMetrics...)

	// Collect balance metrics
	balanceMetrics, err := s.collectBalanceMetrics(ctx)
	if err != nil {
		return nil, fmt.Errorf("failed to collect balance metrics: %w", err)
	}
	metrics = append(metrics, balanceMetrics...)

	s.lastUpdateTime = time.Now()
	return metrics, nil
}

// collectCustomerMetrics collects metrics related to Stripe customers
func (s *StripeMetricsCollector) collectCustomerMetrics(ctx context.Context) ([]MetricValue, error) {
	var metrics []MetricValue

	// Get customer count
	params := &stripe.CustomerListParams{}
	params.AddExpand("data")

	i := customer.List(params)
	totalCustomers := 0

	for i.Next() {
		totalCustomers++
	}

	if i.Err() != nil {
		return nil, fmt.Errorf("failed to list customers: %w", i.Err())
	}

	metrics = append(metrics, MetricValue{
		Name:      "stripe_customers_total",
		Type:      MetricTypeGauge,
		Value:     float64(totalCustomers),
		Timestamp: time.Now(),
		Tags: map[string]string{
			"collector": s.name,
		},
		Description: "Total number of Stripe customers",
	})

	return metrics, nil
}

// collectSubscriptionMetrics collects metrics related to Stripe subscriptions
func (s *StripeMetricsCollector) collectSubscriptionMetrics(ctx context.Context) ([]MetricValue, error) {
	var metrics []MetricValue

	// Get subscription metrics
	params := &stripe.SubscriptionListParams{}
	params.AddExpand("data.customer")

	var activeCount int64
	var pastDueCount int64
	var totalMRR float64

	i := subscription.List(params)

	for i.Next() {
		sub := i.Subscription()

		switch sub.Status {
		case stripe.SubscriptionStatusActive:
			activeCount++
		case stripe.SubscriptionStatusPastDue:
			pastDueCount++
		}

		// Calculate MRR (Monthly Recurring Revenue)
		if sub.Items != nil && len(sub.Items.Data) > 0 {
			for _, item := range sub.Items.Data {
				if item.Price.Recurring != nil {
					if item.Price.Recurring.AggregateUsage == "" {
						totalMRR += float64(item.Price.UnitAmount) / 100.0
					}
				}
			}
		}
	}

	if i.Err() != nil {
		return nil, fmt.Errorf("failed to list subscriptions: %w", i.Err())
	}

	timestamp := time.Now()

	metrics = append(metrics,
		MetricValue{
			Name:      "stripe_subscriptions_active",
			Type:      MetricTypeGauge,
			Value:     float64(activeCount),
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": s.name,
				"status":    "active",
			},
			Description: "Number of active Stripe subscriptions",
		},
		MetricValue{
			Name:      "stripe_subscriptions_past_due",
			Type:      MetricTypeGauge,
			Value:     float64(pastDueCount),
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": s.name,
				"status":    "past_due",
			},
			Description: "Number of past due Stripe subscriptions",
		},
		MetricValue{
			Name:      "stripe_mrr_total",
			Type:      MetricTypeGauge,
			Value:     totalMRR,
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": s.name,
				"currency":  "usd",
			},
			Description: "Total monthly recurring revenue",
		},
	)

	return metrics, nil
}

// collectChargeMetrics collects metrics related to Stripe charges
func (s *StripeMetricsCollector) collectChargeMetrics(ctx context.Context) ([]MetricValue, error) {
	var metrics []MetricValue

	// Get charge metrics for the last 24 hours
	oneDayAgo := time.Now().Add(-24 * time.Hour)

	params := &stripe.ChargeListParams{}
	params.Filters.AddFilter("created", "gte", fmt.Sprintf("%d", oneDayAgo.Unix()))

	var successCount int64
	var failureCount int64
	var totalAmount float64

	i := charge.List(params)

	for i.Next() {
		c := i.Charge()

		if c.Paid {
			successCount++
		} else if c.Refunded || (c.Status == stripe.ChargeStatusFailed) {
			failureCount++
		}

		totalAmount += float64(c.Amount) / 100.0
	}

	if i.Err() != nil {
		return nil, fmt.Errorf("failed to list charges: %w", i.Err())
	}

	timestamp := time.Now()

	metrics = append(metrics,
		MetricValue{
			Name:      "stripe_charges_successful_24h",
			Type:      MetricTypeCounter,
			Value:     float64(successCount),
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": s.name,
				"status":    "successful",
				"period":    "24h",
			},
			Description: "Number of successful charges in the last 24 hours",
		},
		MetricValue{
			Name:      "stripe_charges_failed_24h",
			Type:      MetricTypeCounter,
			Value:     float64(failureCount),
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": s.name,
				"status":    "failed",
				"period":    "24h",
			},
			Description: "Number of failed charges in the last 24 hours",
		},
		MetricValue{
			Name:      "stripe_charges_amount_24h",
			Type:      MetricTypeGauge,
			Value:     totalAmount,
			Timestamp: timestamp,
			Tags: map[string]string{
				"collector": s.name,
				"currency":  "usd",
				"period":    "24h",
			},
			Description: "Total charge amount in the last 24 hours",
		},
	)

	return metrics, nil
}

// collectBalanceMetrics collects metrics related to Stripe account balance
func (s *StripeMetricsCollector) collectBalanceMetrics(ctx context.Context) ([]MetricValue, error) {
	var metrics []MetricValue

	// This would require the Stripe balance API
	// For now, we'll return empty metrics as this requires more complex setup
	// In production, you would call stripe.Balance.Get()

	return metrics, nil
}
