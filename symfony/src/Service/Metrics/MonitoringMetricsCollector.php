<?php

namespace App\Service\Metrics;

use DateTime;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use RuntimeException;

class MonitoringMetricsCollector
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger
    ) {}

    public function getMonitoringMetrics(DateTime $startTime, DateTime $endTime): array
    {
        try {
            return [
                'endpoints_total' => $this->countEndpoints(),
                'endpoints_active' => $this->countActiveEndpoints(),
                'checks_24h' => $this->countChecks($startTime),
                'successful_checks' => $this->countSuccessfulChecks($startTime),
                'failed_checks' => $this->countFailedChecks($startTime),
                'avg_response_time' => $this->getAverageResponseTime($startTime),
                'alerts_configured' => $this->countAlerts(),
                'alerts_triggered_24h' => $this->countTriggeredAlerts($startTime),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get monitoring metrics', ['error' => $e->getMessage()]);
            throw new RuntimeException('Failed to get monitoring metrics: ' . $e->getMessage());
        }
    }

    private function countEndpoints(): int
    {
        $result = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM endpoints WHERE deleted_at IS NULL'
        );
        return (int) $result;
    }

    private function countActiveEndpoints(): int
    {
        $result = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM endpoints WHERE deleted_at IS NULL AND is_active = true'
        );
        return (int) $result;
    }

    private function countChecks(DateTime $startTime): int
    {
        $result = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM monitoring_results WHERE created_at > ?',
            [$startTime->format('Y-m-d H:i:s')]
        );
        return (int) $result;
    }

    private function countSuccessfulChecks(DateTime $startTime): int
    {
        $result = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM monitoring_results WHERE created_at > ? AND status_code >= 200 AND status_code < 300',
            [$startTime->format('Y-m-d H:i:s')]
        );
        return (int) $result;
    }

    private function countFailedChecks(DateTime $startTime): int
    {
        $result = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM monitoring_results WHERE created_at > ? AND (status_code >= 400 OR status_code = 0)',
            [$startTime->format('Y-m-d H:i:s')]
        );
        return (int) $result;
    }

    private function getAverageResponseTime(DateTime $startTime): ?float
    {
        $result = $this->connection->fetchOne(
            'SELECT AVG(response_time_ms) FROM monitoring_results WHERE created_at > ? AND response_time_ms > 0',
            [$startTime->format('Y-m-d H:i:s')]
        );
        return $result ? (float) $result : null;
    }

    private function countAlerts(): int
    {
        $result = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM alerts WHERE deleted_at IS NULL'
        );
        return (int) $result;
    }

    private function countTriggeredAlerts(DateTime $startTime): int
    {
        $result = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM alerts WHERE triggered_at IS NOT NULL AND triggered_at > ?',
            [$startTime->format('Y-m-d H:i:s')]
        );
        return (int) $result;
    }
}
