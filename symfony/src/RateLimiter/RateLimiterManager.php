<?php

namespace App\RateLimiter;

use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;

class RateLimiterManager
{
    private RateLimiterFactory $factory;

    public function __construct()
    {
        // In production, use Redis storage instead of InMemoryStorage
        // For now, use in-memory storage for development
        $this->factory = new RateLimiterFactory([
            'id' => 'api_limiter',
            'policy' => 'sliding_window',
            'limit' => 100,
            'interval' => '1 minute',
        ]);
    }

    /**
     * Check rate limit for login endpoint
     * Limit: 5 attempts per 15 minutes
     *
     * @param string $identifier User email or IP
     * @throws RateLimitExceededException
     */
    public function checkLoginLimit(string $identifier): void
    {
        $this->checkLimit('login:' . $identifier, 5, '15 minutes');
    }

    /**
     * Check rate limit for registration
     * Limit: 10 requests per hour per IP
     *
     * @param string $identifier Client IP
     * @throws RateLimitExceededException
     */
    public function checkRegistrationLimit(string $identifier): void
    {
        $this->checkLimit('register:' . $identifier, 10, '1 hour');
    }

    /**
     * Check rate limit for password reset
     * Limit: 3 requests per hour per email
     *
     * @param string $identifier Email address
     * @throws RateLimitExceededException
     */
    public function checkPasswordResetLimit(string $identifier): void
    {
        $this->checkLimit('password_reset:' . $identifier, 3, '1 hour');
    }

    /**
     * Check rate limit for API endpoints (general)
     * Limit: 100 requests per minute per IP
     *
     * @param string $identifier Client IP
     * @throws RateLimitExceededException
     */
    public function checkApiLimit(string $identifier): void
    {
        $this->checkLimit('api:' . $identifier, 100, '1 minute');
    }

    /**
     * Generic rate limit check
     *
     * @param string $key Unique identifier (e.g., user_id, email, IP)
     * @param int $limit Maximum requests allowed
     * @param string $interval Time window (e.g., '1 minute', '1 hour')
     * @throws RateLimitExceededException
     */
    private function checkLimit(string $key, int $limit, string $interval): void
    {
        // Parse interval to seconds
        $intervalSeconds = $this->parseInterval($interval);

        // This is a simplified implementation
        // In production, use Redis-backed rate limiter
        $cacheKey = 'rate_limit:' . $key;
        
        // Note: This is pseudo-code. Implement with actual caching backend
        // You can use Symfony's Cache component or Redis directly
        throw new \Exception(
            'Rate limiter needs Redis implementation for production use. ' .
            'See symfony/config/packages/rate_limiter.yaml for setup.'
        );
    }

    private function parseInterval(string $interval): int
    {
        $intervals = [
            '1 minute' => 60,
            '5 minutes' => 300,
            '15 minutes' => 900,
            '1 hour' => 3600,
            '24 hours' => 86400,
        ];

        return $intervals[$interval] ?? 60;
    }
}
