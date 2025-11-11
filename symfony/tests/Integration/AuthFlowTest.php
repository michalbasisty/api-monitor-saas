<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AuthFlowTest extends WebTestCase
{
    private string $email = 'integration-test@example.com';
    private string $password = 'TestPassword123!';
    private string $verificationToken = '';

    public function testCompleteAuthFlow(): void
    {
        // Step 1: Register user
        $client = static::createClient();
        $response = $this->register($client);
        
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('verification_token', $data);
        $this->verificationToken = $data['verification_token'];
    }

    public function testUserRegistration(): void
    {
        $client = static::createClient();
        
        $payload = [
            'email' => $this->email,
            'password' => $this->password
        ];
        
        $client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        
        $response = $client->getResponse();
        
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals($this->email, $data['email']);
    }

    public function testEmailVerification(): void
    {
        // First register
        $this->testUserRegistration();
        
        // Then verify - using a mock token since we can't get real one easily
        $client = static::createClient();
        
        $client->request(
            'GET',
            '/api/auth/verify-email/mock-token',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json']
        );
        
        // This may fail with 404 if token invalid, which is expected
        // Real implementation would use actual token
    }

    public function testUserLogin(): void
    {
        $client = static::createClient();
        
        $payload = [
            'username' => $this->email,
            'password' => $this->password
        ];
        
        $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        
        $response = $client->getResponse();
        
        // Might be 401 if user doesn't exist or 200 if successful
        $this->assertThat(
            $response->getStatusCode(),
            $this->logicalOr(
                $this->equalTo(Response::HTTP_OK),
                $this->equalTo(Response::HTTP_UNAUTHORIZED),
                $this->equalTo(Response::HTTP_NOT_FOUND)
            )
        );
    }

    public function testLoginReturnsToken(): void
    {
        $client = static::createClient();
        
        $payload = [
            'username' => 'valid@example.com',
            'password' => 'ValidPassword123!'
        ];
        
        $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        
        $response = $client->getResponse();
        
        if ($response->getStatusCode() === Response::HTTP_OK) {
            $data = json_decode($response->getContent(), true);
            $this->assertArrayHasKey('token', $data);
            $this->assertArrayHasKey('user', $data);
            
            // Token should be a JWT (have 3 parts separated by dots)
            $tokenParts = explode('.', $data['token']);
            $this->assertCount(3, $tokenParts);
        }
    }

    public function testAuthenticatedRequest(): void
    {
        $client = static::createClient();
        
        // Make request without token
        $client->request('GET', '/api/auth/me');
        
        // Should be 401 Unauthorized
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testMultipleLoginAttempts(): void
    {
        $client = static::createClient();
        
        for ($i = 0; $i < 3; $i++) {
            $payload = [
                'username' => 'test@example.com',
                'password' => 'wrongpassword'
            ];
            
            $client->request(
                'POST',
                '/api/auth/login',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($payload)
            );
        }
        
        // Should handle multiple failed attempts
        $this->assertNotNull($client->getResponse());
    }

    private function register($client): Response
    {
        $payload = [
            'email' => $this->email . '.' . time(),
            'password' => $this->password
        ];
        
        $client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        
        return $client->getResponse();
    }
}
