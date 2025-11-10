<?php

namespace App\Modules\Ecommerce\Metrics;

use App\Modules\Ecommerce\Entity\Store;

/**
 * Interface for payment gateway metrics collectors
 */
interface MetricsCollectorInterface
{
    /**
     * Get the name/type of this collector
     */
    public function getType(): string;

    /**
     * Verify the connection to the payment provider
     */
    public function verify(array $config): bool;

    /**
     * Collect payment transaction metrics
     */
    public function collectPaymentMetrics(Store $store, array $config, string $timeframe = '24h'): PaymentMetricsDto;

    /**
     * Collect checkout/cart abandonment data
     */
    public function collectAbandonmentMetrics(Store $store, array $config, string $timeframe = '24h'): AbandonmentMetricsDto;

    /**
     * Collect sales metrics
     */
    public function collectSalesMetrics(Store $store, array $config, string $timeframe = '24h'): SalesMetricsDto;

    /**
     * Collect real-time transaction data
     */
    public function collectRealtimeMetrics(Store $store, array $config): RealtimeMetricsDto;

    /**
     * List recent transactions
     */
    public function getRecentTransactions(Store $store, array $config, int $limit = 100): array;

    /**
     * Get webhook events from provider
     */
    public function getWebhookEvents(Store $store, array $config, \DateTime $since): array;
}
