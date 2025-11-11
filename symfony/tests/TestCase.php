<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base test case for application tests
 * 
 * Provides common functionality for:
 * - Database setup/teardown
 * - Token generation
 * - User creation
 * - Assertion helpers
 */
abstract class TestCase extends WebTestCase
{
    protected EntityManagerInterface $em;
    protected string $testToken = '';
    protected string $testUserId = '';

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        
        // Start transaction for test isolation
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Rollback transaction after test
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        
        $this->em->close();
    }

    /**
     * Assert response is successful (200-299)
     */
    protected function assertSuccessResponse(Response $response): void
    {
        $this->assertThat(
            $response->getStatusCode(),
            $this->logicalAnd(
                $this->greaterThanOrEqual(200),
                $this->lessThan(300)
            )
        );
    }

    /**
     * Assert response is client error (400-499)
     */
    protected function assertClientErrorResponse(Response $response, int $expectedCode = null): void
    {
        if ($expectedCode !== null) {
            $this->assertEquals($expectedCode, $response->getStatusCode());
        } else {
            $this->assertThat(
                $response->getStatusCode(),
                $this->logicalAnd(
                    $this->greaterThanOrEqual(400),
                    $this->lessThan(500)
                )
            );
        }
    }

    /**
     * Assert response is server error (500-599)
     */
    protected function assertServerErrorResponse(Response $response): void
    {
        $this->assertThat(
            $response->getStatusCode(),
            $this->logicalAnd(
                $this->greaterThanOrEqual(500),
                $this->lessThan(600)
            )
        );
    }

    /**
     * Get JSON response data
     */
    protected function getJsonResponse(Response $response): array
    {
        $content = $response->getContent();
        $this->assertIsString($content);
        
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        
        return $data;
    }

    /**
     * Assert response contains key
     */
    protected function assertResponseHasKey(Response $response, string $key): void
    {
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey($key, $data);
    }

    /**
     * Assert response key equals value
     */
    protected function assertResponseKeyEquals(Response $response, string $key, $value): void
    {
        $data = $this->getJsonResponse($response);
        $this->assertArrayHasKey($key, $data);
        $this->assertEquals($value, $data[$key]);
    }

    /**
     * Create test user
     */
    protected function createTestUser(string $email = 'test@example.com', string $password = 'password123'): string
    {
        $client = static::createClient();
        
        $response = $client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email . time(),
                'password' => $password
            ])
        );
        
        $data = $this->getJsonResponse($response);
        return $data['id'] ?? '';
    }

    /**
     * Login test user and get token
     */
    protected function loginTestUser(string $email = 'test@example.com', string $password = 'password123'): string
    {
        $client = static::createClient();
        
        $response = $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => $email,
                'password' => $password
            ])
        );
        
        if ($response->getStatusCode() === 200) {
            $data = $this->getJsonResponse($response);
            return $data['token'] ?? '';
        }
        
        return '';
    }

    /**
     * Create test endpoint
     */
    protected function createTestEndpoint(string $token, string $url = 'https://httpbin.org/status/200'): string
    {
        $client = static::createClient();
        
        $response = $client->request(
            'POST',
            '/api/endpoints',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token
            ],
            json_encode([
                'url' => $url,
                'check_interval' => 300,
                'timeout' => 5000,
                'is_active' => true
            ])
        );
        
        if ($response->getStatusCode() === 201) {
            $data = $this->getJsonResponse($response);
            return $data['id'] ?? '';
        }
        
        return '';
    }

    /**
     * Make authenticated request
     */
    protected function makeAuthenticatedRequest(string $method, string $path, string $token, array $data = []): Response
    {
        $client = static::createClient();
        
        return $client->request(
            $method,
            $path,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token
            ],
            $data ? json_encode($data) : ''
        );
    }
}
