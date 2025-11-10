<?php

namespace App\Modules\Ecommerce\Metrics;

/**
 * Data Transfer Object for real-time metrics
 */
class RealtimeMetricsDto
{
    public function __construct(
        public readonly int $activeSessionsCount,
        public readonly int $cartViewsPerMinute,
        public readonly int $completionsPerMinute,
        public readonly int $abandonmentsPerMinute,
        public readonly float $revenuePerMinute,
        public readonly float $avgSessionDuration,
        public readonly float $currentConversionRate,
        public readonly array $currentCheckoutSteps = [],
        public readonly array $failingPaymentGateways = [],
        public readonly \DateTime $timestamp = new \DateTime(),
    ) {}

    public function toArray(): array
    {
        return [
            'activeSessionsCount' => $this->activeSessionsCount,
            'cartViewsPerMinute' => $this->cartViewsPerMinute,
            'completionsPerMinute' => $this->completionsPerMinute,
            'abandonmentsPerMinute' => $this->abandonmentsPerMinute,
            'revenuePerMinute' => round($this->revenuePerMinute, 2),
            'avgSessionDuration' => round($this->avgSessionDuration, 0),
            'currentConversionRate' => round($this->currentConversionRate, 2),
            'currentCheckoutSteps' => $this->currentCheckoutSteps,
            'failingPaymentGateways' => $this->failingPaymentGateways,
            'timestamp' => $this->timestamp->format('c'),
        ];
    }
}
