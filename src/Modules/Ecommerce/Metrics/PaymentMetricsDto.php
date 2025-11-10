<?php

namespace App\Modules\Ecommerce\Metrics;

/**
 * Data Transfer Object for payment metrics
 */
class PaymentMetricsDto
{
    public function __construct(
        public readonly string $timeframe,
        public readonly int $totalTransactions,
        public readonly int $successfulTransactions,
        public readonly int $failedTransactions,
        public readonly int $declinedTransactions,
        public readonly float $successRate,
        public readonly float $failureRate,
        public readonly float $declineRate,
        public readonly float $totalAmount,
        public readonly float $averageAmount,
        public readonly int $avgAuthTimeMs,
        public readonly int $avgProcessingTimeMs,
        public readonly array $byGateway = [],
        public readonly array $byStatus = [],
        public readonly \DateTime $collectedAt = new \DateTime(),
    ) {}

    public function toArray(): array
    {
        return [
            'timeframe' => $this->timeframe,
            'totalTransactions' => $this->totalTransactions,
            'successfulTransactions' => $this->successfulTransactions,
            'failedTransactions' => $this->failedTransactions,
            'declinedTransactions' => $this->declinedTransactions,
            'successRate' => round($this->successRate, 2),
            'failureRate' => round($this->failureRate, 2),
            'declineRate' => round($this->declineRate, 2),
            'totalAmount' => round($this->totalAmount, 2),
            'averageAmount' => round($this->averageAmount, 2),
            'avgAuthTimeMs' => $this->avgAuthTimeMs,
            'avgProcessingTimeMs' => $this->avgProcessingTimeMs,
            'byGateway' => $this->byGateway,
            'byStatus' => $this->byStatus,
            'collectedAt' => $this->collectedAt->format('c'),
        ];
    }
}
