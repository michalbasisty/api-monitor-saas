<?php

namespace App\Service;

use Predis\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Publishes HTTP request/response metrics to a Redis stream.
 * Prefers phpredis if available, otherwise falls back to Predis.
 * Errors are logged and do not affect request handling.
 */
class MetricsPublisher
{
    private const STREAM_KEY = 'api-metrics';
    private const MAX_STREAM_LENGTH = 1000000; // Keep last million events

    public function __construct(private readonly Client $redis, private readonly LoggerInterface $logger)
    {
    }

    /**
     * Publish a single HTTP metric to the Redis stream.
     *
     * @param Request $request  Current HTTP request.
     * @param Response $response Current HTTP response.
     * @param float $startTime  Request start time (microtime(true)).
     * @return void
     */
    public function publishMetric(Request $request, Response $response, float $startTime): void
    {
        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $metric = [
            'endpoint_id' => $this->generateEndpointId($request),
            'response_time' => $responseTime,
            'status_code' => $response->getStatusCode(),
            'timestamp' => (new \DateTime())->format('c'),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
        ];

        try {
            // Prefer phpredis extension (faster, more direct) when available
            if (class_exists('Redis')) {
                $host = getenv('REDIS_HOST') ?: 'redis';
                $port = getenv('REDIS_PORT') ?: 6379;
                $r = new \Redis();
                $r->connect($host, (int)$port);
                // xAdd signature: xAdd(key, id, array, maxlen)
                $r->xAdd(self::STREAM_KEY, '*', $metric, self::MAX_STREAM_LENGTH);
                $this->logger->debug('Published metric to Redis stream via phpredis', ['stream' => self::STREAM_KEY, 'metric' => $metric]);
                return;
            }

            // Fallback to Predis. Use signature xadd(key, id, array $message, $maxlen = null, $approx = false)
            $this->redis->xadd(self::STREAM_KEY, '*', $metric, self::MAX_STREAM_LENGTH, true);
            $this->logger->debug('Published metric to Redis stream via Predis', ['stream' => self::STREAM_KEY, 'metric' => $metric]);
        } catch (\Throwable $e) {
            // Log error but don't break request processing
            $this->logger->error('Failed to publish metric to Redis', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Build a normalized endpoint identifier (e.g., GET:/api/users/{id}).
     * Dynamic numeric segments are replaced with {id} to improve aggregation.
     */
    private function generateEndpointId(Request $request): string
    {
        // Create a unique identifier for the endpoint based on method and path
        $path = $request->getPathInfo();
        $method = $request->getMethod();
        
        // Replace dynamic path segments with placeholders
        // e.g., /api/users/123 becomes /api/users/{id}
        $normalizedPath = preg_replace('/\/\d+/', '/{id}', $path);
        
        return strtolower($method . ':' . $normalizedPath);
    }
}