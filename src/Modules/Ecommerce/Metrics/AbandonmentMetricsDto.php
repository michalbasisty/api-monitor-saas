<?php

namespace App\Modules\Ecommerce\Metrics;

/**
 * Data Transfer Object for abandonment metrics
 */
class AbandonmentMetricsDto
{
    public function __construct(
        public readonly string $timeframe,
        public readonly int $totalSessions,
        public readonly int $completedCheckouts,
        public readonly int $abandonedCheckouts,
        public readonly float $abandonmentRate,
        public readonly array $abandonmentByStep = [],
        public readonly float $estimatedLostRevenue = 0.0,
        public readonly float $averageCartValue = 0.0,
        public readonly int $avgTimeInCheckout = 0,
        public readonly \DateTime $collectedAt = new \DateTime(),
    ) {}

    public function toArray(): array
    {
        return [
            'timeframe' => $this->timeframe,
            'totalSessions' => $this->totalSessions,
            'completedCheckouts' => $this->completedCheckouts,
            'abandonedCheckouts' => $this->abandonedCheckouts,
            'abandonmentRate' => round($this->abandonmentRate, 2),
            'abandonmentByStep' => $this->abandonmentByStep,
            'estimatedLostRevenue' => round($this->estimatedLostRevenue, 2),
            'averageCartValue' => round($this->averageCartValue, 2),
            'avgTimeInCheckout' => $this->avgTimeInCheckout,
            'collectedAt' => $this->collectedAt->format('c'),
        ];
    }
}
