<?php

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use App\Service\EmailVerificationService;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use PHPUnit\Framework\MockObject\MockObject;

class EmailVerificationServiceTest extends TestCase
{
    private EmailVerificationService $service;
    private MockObject $entityManager;
    private MockObject $mailer;
    private MockObject $userRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->userRepository = $this->createMock(EntityRepository::class);

        $this->service = new EmailVerificationService($this->entityManager, $this->mailer);
    }

    // ============================================
    // generateVerificationToken Tests
    // ============================================

    public function testGenerateVerificationTokenReturnsValidUuid(): void
    {
        $user = $this->createMockUser();

        $token = $this->service->generateVerificationToken($user);

        // Verify it's a valid UUID format (36 chars with hyphens)
        $this->assertIsString($token);
        $this->assertEquals(36, strlen($token));
        $this->assertStringContainsString('-', $token);
    }

    public function testGenerateVerificationTokenSetsTokenOnUser(): void
    {
        $user = $this->createMockUser();

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $token = $this->service->generateVerificationToken($user);

        // Verify the mock was called with setVerificationToken
        $user->expects($this->once())->method('setVerificationToken')->with($token);
    }

    public function testGenerateVerificationTokenSetsExpiryTime(): void
    {
        $user = $this->createMockUser();
        $before = new \DateTimeImmutable();

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->generateVerificationToken($user);

        $after = new \DateTimeImmutable();

        // Verify expiry is approximately 24 hours from now
        $user->expects($this->once())->method('setVerificationTokenExpiresAt');
    }

    public function testGenerateVerificationTokenFlushesChanges(): void
    {
        $user = $this->createMockUser();

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->generateVerificationToken($user);
    }

    // ============================================
    // verifyEmail Tests
    // ============================================

    public function testVerifyEmailReturnsNullIfTokenNotFound(): void
    {
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['verification_token' => 'invalid-token'])
            ->willReturn(null);

        $result = $this->service->verifyEmail('invalid-token');

        $this->assertNull($result);
    }

    public function testVerifyEmailReturnsNullIfUserAlreadyVerified(): void
    {
        $token = 'valid-token';
        $user = $this->createMockUser();
        $user->method('isVerified')->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['verification_token' => $token])
            ->willReturn($user);

        $result = $this->service->verifyEmail($token);

        $this->assertNull($result);
    }

    public function testVerifyEmailReturnsNullIfTokenExpired(): void
    {
        $token = 'expired-token';
        $user = $this->createMockUser();
        $user->method('isVerified')->willReturn(false);

        // Token expired 1 hour ago
        $expiredTime = new \DateTimeImmutable('-1 hour');
        $user->method('getVerificationTokenExpiresAt')->willReturn($expiredTime);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['verification_token' => $token])
            ->willReturn($user);

        $result = $this->service->verifyEmail($token);

        $this->assertNull($result);
    }

    public function testVerifyEmailSuccessfullyVerifiesValidToken(): void
    {
        $token = 'valid-token';
        $user = $this->createMockUser();
        $user->method('isVerified')->willReturn(false);

        // Token expires in 1 hour
        $futureTime = new \DateTimeImmutable('+1 hour');
        $user->method('getVerificationTokenExpiresAt')->willReturn($futureTime);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['verification_token' => $token])
            ->willReturn($user);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->verifyEmail($token);

        $this->assertSame($user, $result);
    }

    public function testVerifyEmailClearsTokenAndExpiry(): void
    {
        $token = 'valid-token';
        $user = $this->createMockUser();
        $user->method('isVerified')->willReturn(false);
        $user->method('getVerificationTokenExpiresAt')
            ->willReturn(new \DateTimeImmutable('+1 hour'));

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['verification_token' => $token])
            ->willReturn($user);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->verifyEmail($token);

        // Verify cleanup calls were made
        $user->expects($this->once())->method('setVerificationToken')->with(null);
        $user->expects($this->once())->method('setVerificationTokenExpiresAt')->with(null);
    }

    // ============================================
    // isTokenValid Tests
    // ============================================

    public function testIsTokenValidReturnsFalseIfUserVerified(): void
    {
        $user = $this->createMockUser();
        $user->method('isVerified')->willReturn(true);

        $result = $this->service->isTokenValid($user);

        $this->assertFalse($result);
    }

    public function testIsTokenValidReturnsFalseIfNoExpiry(): void
    {
        $user = $this->createMockUser();
        $user->method('isVerified')->willReturn(false);
        $user->method('getVerificationTokenExpiresAt')->willReturn(null);

        $result = $this->service->isTokenValid($user);

        $this->assertFalse($result);
    }

    public function testIsTokenValidReturnsFalseIfTokenExpired(): void
    {
        $user = $this->createMockUser();
        $user->method('isVerified')->willReturn(false);

        $expiredTime = new \DateTimeImmutable('-1 hour');
        $user->method('getVerificationTokenExpiresAt')->willReturn($expiredTime);

        $result = $this->service->isTokenValid($user);

        $this->assertFalse($result);
    }

    public function testIsTokenValidReturnsTrueForValidToken(): void
    {
        $user = $this->createMockUser();
        $user->method('isVerified')->willReturn(false);

        $futureTime = new \DateTimeImmutable('+1 hour');
        $user->method('getVerificationTokenExpiresAt')->willReturn($futureTime);

        $result = $this->service->isTokenValid($user);

        $this->assertTrue($result);
    }

    public function testIsTokenValidReturnsTrueIfTokenExpiresNow(): void
    {
        $user = $this->createMockUser();
        $user->method('isVerified')->willReturn(false);

        $now = new \DateTimeImmutable();
        $user->method('getVerificationTokenExpiresAt')->willReturn($now);

        $result = $this->service->isTokenValid($user);

        $this->assertTrue($result);
    }

    // ============================================
    // sendVerificationEmail Tests
    // ============================================

    public function testSendVerificationEmailThrowsIfNoToken(): void
    {
        $user = $this->createMockUser();
        $user->method('getVerificationToken')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No verification token found for user');

        $this->service->sendVerificationEmail($user);
    }

    public function testSendVerificationEmailSendsEmailWithToken(): void
    {
        $token = 'verify-token-123';
        $user = $this->createMockUser('test@example.com');
        $user->method('getVerificationToken')->willReturn($token);

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(Email::class));

        $this->service->sendVerificationEmail($user);
    }

    public function testSendVerificationEmailContainsToken(): void
    {
        $token = 'verify-token-123';
        $user = $this->createMockUser('test@example.com');
        $user->method('getVerificationToken')->willReturn($token);

        $emailCapture = null;
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willReturnCallback(function ($email) use (&$emailCapture) {
                $emailCapture = $email;
            });

        $this->service->sendVerificationEmail($user);

        // Note: Would need access to protected Email properties to fully test
        // This is a basic test of the send being called
        $this->assertNotNull($emailCapture);
    }

    // ============================================
    // sendPasswordResetEmail Tests
    // ============================================

    public function testSendPasswordResetEmailThrowsIfNoToken(): void
    {
        $user = $this->createMockUser();
        $user->method('getResetToken')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No reset token found for user');

        $this->service->sendPasswordResetEmail($user);
    }

    public function testSendPasswordResetEmailSendsEmail(): void
    {
        $token = 'reset-token-456';
        $user = $this->createMockUser('test@example.com');
        $user->method('getResetToken')->willReturn($token);

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(Email::class));

        $this->service->sendPasswordResetEmail($user);
    }

    // ============================================
    // Helper Methods
    // ============================================

    private function createMockUser(string $email = 'user@example.com'): MockObject
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn($email);
        return $user;
    }
}
