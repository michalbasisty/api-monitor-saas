<?php

namespace App\Service\Metrics;

use Predis\Client as RedisClient;
use Psr\Log\LoggerInterface;

/**
 * Collects realtime metrics and publishes them to a Redis stream.
 * Failures are logged and should not be fatal to request handling.
 */
class RealtimeMetricsCollector
{
    public function __construct(
        private RedisClient $redisClient,
        private LoggerInterface $logger
    ) {}

    /**
     * Retrieve most recent metrics from the stream.
     *
     * @param int $limit Maximum number of entries to return.
     * @return array<int, array{id: string, data: array<string, mixed>}>
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
     * Publish a single metric entry to the stream.
     *
     * @param string $metricName Metric name.
     * @param float $value Metric value.
     * @param array<string, scalar> $tags Additional context as key/value pairs.
     * @return bool True on success; false if publish fails.
     */
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
