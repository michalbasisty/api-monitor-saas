<?php

namespace App\Service;

use DateTime;
use Doctrine\DBAL\Connection;
use Predis\Client as RedisClient;
use Psr\Log\LoggerInterface;
use RuntimeException;

class MetricsCollectionService
{
    private Connection $connection;
    private RedisClient $redisClient;
    private LoggerInterface $logger;

    public function __construct(
        Connection $connection,
        RedisClient $redisClient,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->redisClient = $redisClient;
        $this->logger = $logger;
    }

    /**
     * Retrieve metrics for a specific time range
     */
    public function getMetrics(DateTime $startTime, DateTime $endTime, ?string $metricName = null): array
    {
        try {
            $sql = '
                SELECT name, type, value, timestamp, tags, description
                FROM system_metrics
                WHERE timestamp BETWEEN :startTime AND :endTime
            ';

            $params = [
                'startTime' => $startTime->format('Y-m-d H:i:s'),
                'endTime' => $endTime->format('Y-m-d H:i:s'),
            ];

            if ($metricName) {
                $sql .= ' AND name = :name';
                $params['name'] = $metricName;
            }

            $sql .= ' ORDER BY timestamp DESC';

            $stmt = $this->connection->prepare($sql);
            $result = $stmt->executeQuery($params);

            $metrics = [];
            while ($row = $result->fetchAssociative()) {
                $row['tags'] = json_decode($row['tags'], true) ?? [];
                $metrics[] = $row;
            }

            return $metrics;
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve metrics', ['error' => $e->getMessage()]);
            throw new RuntimeException('Failed to retrieve metrics: ' . $e->getMessage());
        }
    }

    /**
     * Get aggregated metric statistics
     */
    public function getMetricAggregates(string $metricName, DateTime $startTime, DateTime $endTime): array
    {
        try {
            $sql = '
                SELECT 
                    COUNT(*) as count,
                    AVG(value) as average,
                    MIN(value) as minimum,
                    MAX(value) as maximum,
                    STDDEV(value) as stddev
                FROM system_metrics
                WHERE name = :name AND timestamp BETWEEN :startTime AND :endTime
            ';

            $stmt = $this->connection->prepare($sql);
            $result = $stmt->executeQuery([
                'name' => $metricName,
                'startTime' => $startTime->format('Y-m-d H:i:s'),
                'endTime' => $endTime->format('Y-m-d H:i:s'),
            ]);

            return $result->fetchAssociative() ?: [];
        } catch (\Exception $e) {
            $this->logger->error('Failed to aggregate metrics', ['error' => $e->getMessage()]);
            throw new RuntimeException('Failed to aggregate metrics: ' . $e->getMessage());
        }
    }

    /**
     * Get system health metrics
     */
    public function getSystemHealth(): array
    {
        try {
            $oneDayAgo = new DateTime('-1 day');

            $sql = '
                SELECT 
                    name,
                    ROUND(AVG(value), 2) as avg_value,
                    ROUND(MAX(value), 2) as max_value,
                    ROUND(MIN(value), 2) as min_value
                FROM system_metrics
                WHERE timestamp > :startTime
                GROUP BY name
            ';

            $stmt = $this->connection->prepare($sql);
            $result = $stmt->executeQuery([
                'startTime' => $oneDayAgo->format('Y-m-d H:i:s'),
            ]);

            $metrics = [];
            while ($row = $result->fetchAssociative()) {
                $metrics[$row['name']] = $row;
            }

            return $metrics;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get system health', ['error' => $e->getMessage()]);
            throw new RuntimeException('Failed to get system health: ' . $e->getMessage());
        }
    }

    /**
     * Get monitoring performance metrics
     */
    public function getMonitoringMetrics(DateTime $startTime, DateTime $endTime): array
    {
        try {
            $metrics = [
                'endpoints_total' => $this->countEndpoints(),
                'endpoints_active' => $this->countActiveEndpoints(),
                'checks_24h' => $this->countChecks($startTime),
                'successful_checks' => $this->countSuccessfulChecks($startTime),
                'failed_checks' => $this->countFailedChecks($startTime),
                'avg_response_time' => $this->getAverageResponseTime($startTime),
                'alerts_configured' => $this->countAlerts(),
                'alerts_triggered_24h' => $this->countTriggeredAlerts($startTime),
            ];

            return $metrics;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get monitoring metrics', ['error' => $e->getMessage()]);
            throw new RuntimeException('Failed to get monitoring metrics: ' . $e->getMessage());
        }
    }

    /**
     * Get revenue metrics from Stripe
     */
    public function getRevenueMetrics(DateTime $startTime, DateTime $endTime): array
    {
        try {
            $sql = '
                SELECT 
                    name,
                    value,
                    timestamp,
                    tags
                FROM system_metrics
                WHERE name LIKE :pattern 
                AND timestamp BETWEEN :startTime AND :endTime
                ORDER BY timestamp DESC
            ';

            $stmt = $this->connection->prepare($sql);
            $result = $stmt->executeQuery([
                'pattern' => 'stripe_%',
                'startTime' => $startTime->format('Y-m-d H:i:s'),
                'endTime' => $endTime->format('Y-m-d H:i:s'),
            ]);

            $metrics = [];
            while ($row = $result->fetchAssociative()) {
                $row['tags'] = json_decode($row['tags'], true) ?? [];
                $metrics[] = $row;
            }

            return $metrics;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get revenue metrics', ['error' => $e->getMessage()]);
            throw new RuntimeException('Failed to get revenue metrics: ' . $e->getMessage());
        }
    }

    /**
     * Get metrics from Redis stream
     */
    public function getRealtimeMetrics(int $limit = 100): array
    {
        try {
            $entries = $this->redisClient->xrevrange('metrics:stream', '+', '-', 'COUNT', $limit);

            $metrics = [];
            foreach ($entries as $id => $data) {
                $metrics[] = [
                    'id' => $id,
                    'data' => $data,
                ];
            }

            return $metrics;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get realtime metrics', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Clear old metrics (data retention)
     */
    public function clearOldMetrics(int $retentionDays = 90): int
    {
        try {
            $cutoffDate = new DateTime(-$retentionDays . ' days');

            $sql = 'DELETE FROM system_metrics WHERE timestamp < :cutoffDate';

            $stmt = $this->connection->prepare($sql);
            $result = $stmt->executeStatement([
                'cutoffDate' => $cutoffDate->format('Y-m-d H:i:s'),
            ]);

            $this->logger->info("Deleted $result old metrics");

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to clear old metrics', ['error' => $e->getMessage()]);
            throw new RuntimeException('Failed to clear old metrics: ' . $e->getMessage());
        }
    }

    // Helper methods
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
