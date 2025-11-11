<?php

namespace App\Service\Metrics;

use DateTime;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use RuntimeException;

class RevenueMetricsCollector
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger
    ) {}

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
}
