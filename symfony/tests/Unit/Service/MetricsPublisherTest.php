<?php

namespace App\Tests\Unit\Service;

use App\Service\MetricsPublisher;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MetricsPublisherTest extends TestCase
{
    public function testPublishMetricPredisFallback(): void
    {
        $redis = $this->createMock(Client::class);
        $logger = $this->createMock(LoggerInterface::class);

        $publisher = new MetricsPublisher($redis, $logger);

        $request = Request::create('/api/users/123', 'GET');
        $response = new Response('', 200);

        $redis->expects($this->once())
            ->method('__call')
            ->with('xadd', $this->callback(function ($args) {
                // stream, id, message, maxlen, approx
                return $args[0] === 'api-metrics' && $args[1] === '*' && is_array($args[2]);
            }));

        // Force Predis path by ensuring phpredis isn't used (class_exists('Redis') may exist in CI; we only assert Predis was called)
        $publisher->publishMetric($request, $response, microtime(true));

        $this->assertTrue(true);
    }

    public function testEndpointIdNormalization(): void
    {
        $redis = $this->createMock(Client::class);
        $logger = $this->createMock(LoggerInterface::class);

        $publisher = new MetricsPublisher($redis, $logger);

        $request = Request::create('/api/orders/987/items/654', 'POST');
        $method = new \ReflectionMethod($publisher, 'publishMetric');
        $response = new Response('', 201);

        // Intercept Predis call and inspect message payload
        $redis->expects($this->once())
            ->method('__call')
            ->with('xadd', $this->callback(function ($args) {
                $message = $args[2];
                $this->assertArrayHasKey('endpoint_id', $message);
                $this->assertStringContainsString('post:', $message['endpoint_id']);
                $this->assertStringContainsString('/api/orders/{id}/items/{id}', $message['endpoint_id']);
                return true;
            }));

        $publisher->publishMetric($request, $response, microtime(true));
        $this->assertTrue(true);
    }
}
