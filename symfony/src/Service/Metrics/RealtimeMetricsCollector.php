<?php

namespace App\Service\Metrics;

use Predis\Client as RedisClient;
use Psr\Log\LoggerInterface;

class RealtimeMetricsCollector
{
    public function __construct(
        private RedisClient $redisClient,
        private LoggerInterface $logger
    ) {}

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

    public function publishMetric(string $metricName, float $value, array $tags = []): bool
    {
        try {
            $this->redisClient->xadd('metrics:stream', '*', [
                'name' => $metricName,
                'value' => $value,
                'timestamp' => time(),
                'tags' => json_encode($tags),
            ]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to publish metric', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
