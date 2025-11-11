<?php

namespace App\Service\Metrics;

use DateTime;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use RuntimeException;

class SystemMetricsCollector
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger
    ) {}

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

    public function getAggregates(string $metricName, DateTime $startTime, DateTime $endTime): array
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
}
