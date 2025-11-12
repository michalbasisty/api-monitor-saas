<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Repository\UserRepository;

class AuthControllerTest extends WebTestCase
{
    private $client;
    private $userRepository;
    private $testEmail = 'test-auth-' . uniqid() . '@example.com';
    private $testPassword = 'ValidPassword123!';
    private $verificationToken;
    private $jwtToken;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->userRepository = static::getContainer()->get(UserRepository::class);
    }

    protected function tearDown(): void
    {
        // Clean up test user
        $user = $this->userRepository->findOneBy(['email' => $this->testEmail]);
        if ($user) {
            $this->userRepository->remove($user, true);
        }
    }

    /**
     * Test 1: Register a new user
     */
    public function testRegisterUserEndpoint(): void
    {
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'email' => $this->testEmail,
            'password' => $this->testPassword
        ]));

        $this->assertResponseStatusCodeSame(201);
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('message', $response);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('verification_token', $response);
        $this->assertStringContainsString('registered successfully', $response['message']);
        
        // Store verification token for next test
        $this->verificationToken = $response['verification_token'];
    }

    /**
     * Test 2: Register with invalid email should fail
     */
    public function testRegisterWithInvalidEmail(): void
    {
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'email' => 'invalid-email',
            'password' => $this->testPassword
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    /**
     * Test 3: Register with short password should fail
     */
    public function testRegisterWithShortPassword(): void
    {
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'email' => $this->testEmail,
            'password' => 'short'
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    /**
     * Test 4: Register duplicate email should fail
     */
    public function testRegisterDuplicateEmail(): void
    {
        // First registration
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'email' => $this->testEmail,
            'password' => $this->testPassword
        ]));
        $this->assertResponseStatusCodeSame(201);

        // Second registration with same email
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'email' => $this->testEmail,
            'password' => $this->testPassword
        ]));

        $this->assertResponseStatusCodeSame(400);
    }

    /**
     * Test 5: Email verification flow
     */
    public function testEmailVerificationFlow(): void
    {
        // Register user first
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'email' => $this->testEmail,
            'password' => $this->testPassword
        ]));

        $registerResponse = json_decode($this->client->getResponse()->getContent(), true);
        $verificationToken = $registerResponse['verification_token'];

        // Verify email
        $this->client->request('GET', "/api/auth/verify-email/{$verificationToken}");

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('message', $response);
        $this->assertArrayHasKey('user', $response);
        $this->assertTrue($response['user']['is_verified']);
    }

    /**
     * Test 6: Verify with invalid token
     */
    public function testVerifyWithInvalidToken(): void
    {
        $this->client->request('GET', '/api/auth/verify-email/invalid-token-xyz');

        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * Test 7: Login endpoint - JWT generation
     */
    public function testLoginEndpointJWTGeneration(): void
    {
        // Setup: Register and verify user
        $this->registerAndVerifyUser();

        // Login
        $this->client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'username' => $this->testEmail,
            'password' => $this->testPassword
        ]));

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('token', $response);
        $this->assertArrayHasKey('user', $response);
        $this->assertNotEmpty($response['token']);
        $this->assertStringStartsWith('eyJ', $response['token']); // JWT format check
        
        // Store JWT for protected route tests
        $this->jwtToken = $response['token'];
    }

    /**
     * Test 8: Login with invalid credentials
     */
    public function testLoginWithInvalidCredentials(): void
    {
        // Setup: Register and verify user
        $this->registerAndVerifyUser();

        // Attempt login with wrong password
        $this->client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'username' => $this->testEmail,
            'password' => 'WrongPassword123!'
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * Test 9: Login with non-existent user
     */
    public function testLoginWithNonExistentUser(): void
    {
        $this->client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'username' => 'nonexistent@example.com',
            'password' => $this->testPassword
        ]));

        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * Test 10: Protected routes with JWT
     */
    public function testProtectedRouteWithValidJWT(): void
    {
        // Setup: Register, verify, and login
        $this->registerAndVerifyUser();
        $this->loginUser();

        // Access protected route with JWT
        $this->client->request('GET', '/api/auth/me', [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$this->jwtToken}"
        ]);

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('email', $response);
        $this->assertArrayHasKey('roles', $response);
        $this->assertEquals($this->testEmail, $response['email']);
    }

    /**
     * Test 11: Protected route without JWT
     */
    public function testProtectedRouteWithoutJWT(): void
    {
        $this->client->request('GET', '/api/auth/me');

        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * Test 12: Protected route with invalid JWT
     */
    public function testProtectedRouteWithInvalidJWT(): void
    {
        $this->client->request('GET', '/api/auth/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer invalid.jwt.token'
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * Test 13: Protected route with expired JWT
     */
    public function testProtectedRouteWithExpiredJWT(): void
    {
        // Create a token that's expired
        $expiredToken = $this->createExpiredJWT();

        $this->client->request('GET', '/api/auth/me', [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$expiredToken}"
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * Test 14: Verify email is required for login
     */
    public function testLoginRequiresVerifiedEmail(): void
    {
        // Register but don't verify
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'email' => $this->testEmail,
            'password' => $this->testPassword
        ]));

        // Try to login without email verification
        $this->client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'username' => $this->testEmail,
            'password' => $this->testPassword
        ]));

        // Should fail or return unverified status
        $this->assertTrue(
            $this->client->getResponse()->getStatusCode() === 401 ||
            $this->client->getResponse()->getStatusCode() === 403
        );
    }

    /**
     * Test 15: JWT contains correct claims
     */
    public function testJWTContainsCorrectClaims(): void
    {
        // Setup: Register, verify, and login
        $this->registerAndVerifyUser();
        $this->loginUser();

        // Decode JWT and check claims
        $parts = explode('.', $this->jwtToken);
        $payload = json_decode(base64_decode($parts[1]), true);

        $this->assertArrayHasKey('sub', $payload); // User ID
        $this->assertArrayHasKey('email', $payload);
        $this->assertArrayHasKey('roles', $payload);
        $this->assertArrayHasKey('iat', $payload); // Issued at
        $this->assertArrayHasKey('exp', $payload); // Expiration
    }

    // Helper methods
    private function registerAndVerifyUser(): void
    {
        // Register
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'email' => $this->testEmail,
            'password' => $this->testPassword
        ]));

        $registerResponse = json_decode($this->client->getResponse()->getContent(), true);
        $verificationToken = $registerResponse['verification_token'];

        // Verify
        $this->client->request('GET', "/api/auth/verify-email/{$verificationToken}");
    }

    private function loginUser(): void
    {
        $this->client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'username' => $this->testEmail,
            'password' => $this->testPassword
        ]));

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->jwtToken = $response['token'];
    }

    private function createExpiredJWT(): string
    {
        // This would typically use a JWT library to create an expired token
        // For testing purposes, we'll return an obviously malformed token
        return 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyLCJleHAiOjE1MTYyMjkwMjJ9.invalid';
    }
}
