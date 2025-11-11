<?php

namespace App\Service\Metrics;

use DateTime;

class MetricsCollectionService
{
    public function __construct(
        private SystemMetricsCollector $systemCollector,
        private MonitoringMetricsCollector $monitoringCollector,
        private RevenueMetricsCollector $revenueCollector,
        private RealtimeMetricsCollector $realtimeCollector
    ) {}

    public function getMetrics(DateTime $startTime, DateTime $endTime, ?string $metricName = null): array
    {
        return $this->systemCollector->getMetrics($startTime, $endTime, $metricName);
    }

    public function getMetricAggregates(string $metricName, DateTime $startTime, DateTime $endTime): array
    {
        return $this->systemCollector->getAggregates($metricName, $startTime, $endTime);
    }

    public function getSystemHealth(): array
    {
        return $this->systemCollector->getSystemHealth();
    }

    public function getMonitoringMetrics(DateTime $startTime, DateTime $endTime): array
    {
        return $this->monitoringCollector->getMonitoringMetrics($startTime, $endTime);
    }

    public function getRevenueMetrics(DateTime $startTime, DateTime $endTime): array
    {
        return $this->revenueCollector->getRevenueMetrics($startTime, $endTime);
    }

    public function getRealtimeMetrics(int $limit = 100): array
    {
        return $this->realtimeCollector->getRealtimeMetrics($limit);
    }

    public function clearOldMetrics(int $retentionDays = 90): int
    {
        return $this->systemCollector->clearOldMetrics($retentionDays);
    }
}
