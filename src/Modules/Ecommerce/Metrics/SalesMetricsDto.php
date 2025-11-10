<?php

namespace App\Modules\Ecommerce\Metrics;

/**
 * Data Transfer Object for sales metrics
 */
class SalesMetricsDto
{
    public function __construct(
        public readonly string $timeframe,
        public readonly float $totalRevenue,
        public readonly int $totalOrders,
        public readonly float $averageOrderValue,
        public readonly float $conversionRate,
        public readonly float $revenuePerMinute,
        public readonly int $ordersPerMinute,
        public readonly float $estimatedLostRevenue,
        public readonly array $hourlyData = [],
        public readonly array $dailyData = [],
        public readonly \DateTime $collectedAt = new \DateTime(),
    ) {}

    public function toArray(): array
    {
        return [
            'timeframe' => $this->timeframe,
            'totalRevenue' => round($this->totalRevenue, 2),
            'totalOrders' => $this->totalOrders,
            'averageOrderValue' => round($this->averageOrderValue, 2),
            'conversionRate' => round($this->conversionRate, 2),
            'revenuePerMinute' => round($this->revenuePerMinute, 2),
            'ordersPerMinute' => $this->ordersPerMinute,
            'estimatedLostRevenue' => round($this->estimatedLostRevenue, 2),
            'hourlyData' => $this->hourlyData,
            'dailyData' => $this->dailyData,
            'collectedAt' => $this->collectedAt->format('c'),
        ];
    }
}
