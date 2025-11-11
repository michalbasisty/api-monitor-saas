<?php

namespace App\Tests\Unit\Service\Metrics;

use App\Service\Metrics\RealtimeMetricsCollector;
use PHPUnit\Framework\TestCase;
use Predis\Client as RedisClient;
use Psr\Log\LoggerInterface;

class RealtimeMetricsCollectorTest extends TestCase
{
    public function testGetRealtimeMetricsReturnsEmptyOnError(): void
    {
        $redis = $this->createMock(RedisClient::class);
        $logger = $this->createMock(LoggerInterface::class);
        $collector = new RealtimeMetricsCollector($redis, $logger);

        $redis->method('__call')->willThrowException(new \Exception('redis down'));

        $result = $collector->getRealtimeMetrics(5);
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testPublishMetricEncodesTags(): void
    {
        $redis = $this->createMock(RedisClient::class);
        $logger = $this->createMock(LoggerInterface::class);
        $collector = new RealtimeMetricsCollector($redis, $logger);

        $redis->expects($this->once())
            ->method('__call')
            ->with('xadd', $this->callback(function ($args) {
                $payload = $args[2];
                $this->assertArrayHasKey('name', $payload);
                $this->assertSame('cpu_usage', $payload['name']);
                $this->assertArrayHasKey('tags', $payload);
                $this->assertIsString($payload['tags']);
                $decoded = json_decode($payload['tags'], true);
                $this->assertSame(['host' => 'api'], $decoded);
                return true;
            }));

        $ok = $collector->publishMetric('cpu_usage', 0.7, ['host' => 'api']);
        $this->assertTrue($ok);
    }
}
