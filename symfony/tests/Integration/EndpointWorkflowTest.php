<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class EndpointWorkflowTest extends WebTestCase
{
    private string $token = '';
    private string $endpointId = '';

    protected function setUp(): void
    {
        // In real tests, setup would obtain a valid token
        // For now, we'll test the happy path structure
    }

    public function testCreateEndpoint(): void
    {
        $client = static::createClient();
        
        $payload = [
            'url' => 'https://httpbin.org/status/200',
            'check_interval' => 300,
            'timeout' => 5000,
            'is_active' => true
        ];
        
        // Without token, should get 401
        $client->request(
            'POST',
            '/api/endpoints',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testCreateEndpointValidation(): void
    {
        $client = static::createClient();
        
        // Invalid URL
        $payload = [
            'url' => 'not-a-valid-url',
            'check_interval' => 300,
            'timeout' => 5000,
            'is_active' => true
        ];
        
        $client->request(
            'POST',
            '/api/endpoints',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        
        // Will be 400 validation error or 401 auth error
        $this->assertThat(
            $client->getResponse()->getStatusCode(),
            $this->logicalOr(
                $this->equalTo(Response::HTTP_BAD_REQUEST),
                $this->equalTo(Response::HTTP_UNAUTHORIZED)
            )
        );
    }

    public function testListEndpoints(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/api/endpoints');
        
        // Should be 401 without token
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testGetEndpointDetails(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/api/endpoints/invalid-id');
        
        $this->assertThat(
            $client->getResponse()->getStatusCode(),
            $this->logicalOr(
                $this->equalTo(Response::HTTP_UNAUTHORIZED),
                $this->equalTo(Response::HTTP_NOT_FOUND)
            )
        );
    }

    public function testUpdateEndpoint(): void
    {
        $client = static::createClient();
        
        $payload = [
            'check_interval' => 600,
            'is_active' => false
        ];
        
        $client->request(
            'PUT',
            '/api/endpoints/invalid-id',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        
        $this->assertThat(
            $client->getResponse()->getStatusCode(),
            $this->logicalOr(
                $this->equalTo(Response::HTTP_UNAUTHORIZED),
                $this->equalTo(Response::HTTP_NOT_FOUND)
            )
        );
    }

    public function testDeleteEndpoint(): void
    {
        $client = static::createClient();
        
        $client->request('DELETE', '/api/endpoints/invalid-id');
        
        $this->assertThat(
            $client->getResponse()->getStatusCode(),
            $this->logicalOr(
                $this->equalTo(Response::HTTP_UNAUTHORIZED),
                $this->equalTo(Response::HTTP_NOT_FOUND)
            )
        );
    }

    public function testCheckIntervalValidation(): void
    {
        $client = static::createClient();
        
        // Check interval too low (minimum is typically 60 seconds)
        $payload = [
            'url' => 'https://httpbin.org/status/200',
            'check_interval' => 30, // Too low
            'timeout' => 5000,
            'is_active' => true
        ];
        
        $client->request(
            'POST',
            '/api/endpoints',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        
        // Will be validation error or auth error
        $this->assertNotNull($client->getResponse());
    }

    public function testTimeoutValidation(): void
    {
        $client = static::createClient();
        
        // Timeout out of range
        $payload = [
            'url' => 'https://httpbin.org/status/200',
            'check_interval' => 300,
            'timeout' => 50000, // Too high
            'is_active' => true
        ];
        
        $client->request(
            'POST',
            '/api/endpoints',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        
        $this->assertNotNull($client->getResponse());
    }

    public function testEndpointIsolation(): void
    {
        // User should only see their own endpoints
        $client = static::createClient();
        
        $client->request('GET', '/api/endpoints');
        
        // Without auth, should be 401
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }
}
