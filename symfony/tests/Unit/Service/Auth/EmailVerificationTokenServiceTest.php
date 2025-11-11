<?php

namespace App\Tests\Unit\Service\Auth;

use App\Entity\User;
use App\Exception\AuthException;
use App\Service\Auth\EmailVerificationTokenService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class EmailVerificationTokenServiceTest extends TestCase
{
    private EmailVerificationTokenService $service;
    private MockObject $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new EmailVerificationTokenService($this->entityManager);
    }

    public function testGenerateVerificationToken_CreatesTokenAndExpiry(): void
    {
        $user = new User();

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->generateVerificationToken($user);

        $this->assertNotNull($user->getVerificationToken());
        $this->assertNotNull($user->getVerificationTokenExpiresAt());
        $this->assertEquals(64, strlen($user->getVerificationToken()));
    }

    public function testVerifyToken_WithValidToken_ReturnsUser(): void
    {
        $user = new User();
        $user->setVerificationToken('valid_token_12345678901234567890123456789012');
        $user->setVerificationTokenExpiresAt(new \DateTimeImmutable('+24 hours'));

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['verificationToken' => 'valid_token_12345678901234567890123456789012'])
            ->willReturn($user);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $result = $this->service->verifyToken('valid_token_12345678901234567890123456789012');

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user, $result);
    }

    public function testVerifyToken_WithExpiredToken_ThrowsException(): void
    {
        $user = new User();
        $user->setVerificationToken('expired_token');
        $user->setVerificationTokenExpiresAt(new \DateTimeImmutable('-1 hour'));

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($user);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Verification token has expired');

        $this->service->verifyToken('expired_token');
    }

    public function testVerifyToken_WithInvalidToken_ThrowsException(): void
    {
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid verification token');

        $this->service->verifyToken('invalid_token');
    }

    public function testMarkAsVerified_ClearsTokenData(): void
    {
        $user = new User();
        $user->setVerificationToken('token');
        $user->setVerificationTokenExpiresAt(new \DateTimeImmutable());
        $user->setIsVerified(false);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->markAsVerified($user);

        $this->assertTrue($user->isVerified());
        $this->assertNull($user->getVerificationToken());
        $this->assertNull($user->getVerificationTokenExpiresAt());
    }

    public function testGeneratePasswordResetToken_CreatesToken(): void
    {
        $user = new User();

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->generatePasswordResetToken($user);

        $this->assertNotNull($user->getResetToken());
        $this->assertNotNull($user->getResetTokenExpiresAt());
        $this->assertEquals(64, strlen($user->getResetToken()));
    }

    public function testIsTokenValid_WithValidToken_ReturnsTrue(): void
    {
        $user = new User();
        $user->setVerificationToken('token');
        $user->setVerificationTokenExpiresAt(new \DateTimeImmutable('+24 hours'));

        $result = $this->service->isTokenValid($user);

        $this->assertTrue($result);
    }

    public function testIsTokenValid_WithExpiredToken_ReturnsFalse(): void
    {
        $user = new User();
        $user->setVerificationToken('token');
        $user->setVerificationTokenExpiresAt(new \DateTimeImmutable('-1 hour'));

        $result = $this->service->isTokenValid($user);

        $this->assertFalse($result);
    }

    public function testIsTokenValid_WithNullToken_ReturnsFalse(): void
    {
        $user = new User();
        $user->setVerificationToken(null);

        $result = $this->service->isTokenValid($user);

        $this->assertFalse($result);
    }

    public function testClearTokens_RemovesAllTokenData(): void
    {
        $user = new User();
        $user->setVerificationToken('verify_token');
        $user->setVerificationTokenExpiresAt(new \DateTimeImmutable());
        $user->setResetToken('reset_token');
        $user->setResetTokenExpiresAt(new \DateTimeImmutable());

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->clearTokens($user);

        $this->assertNull($user->getVerificationToken());
        $this->assertNull($user->getVerificationTokenExpiresAt());
        $this->assertNull($user->getResetToken());
        $this->assertNull($user->getResetTokenExpiresAt());
    }
}
