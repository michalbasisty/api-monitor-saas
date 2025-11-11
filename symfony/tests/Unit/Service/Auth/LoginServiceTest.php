<?php

namespace App\Tests\Unit\Service\Auth;

use PHPUnit\Framework\TestCase;
use App\Service\Auth\LoginService;
use App\Service\Auth\PasswordManagementService;
use App\Entity\User;
use App\Exception\AuthException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;

class LoginServiceTest extends TestCase
{
    private LoginService $service;
    private MockObject $entityManager;
    private MockObject $passwordService;
    private MockObject $userRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordService = $this->createMock(PasswordManagementService::class);
        $this->userRepository = $this->createMock(EntityRepository::class);

        $this->service = new LoginService($this->entityManager, $this->passwordService);
    }

    // ============================================
    // Successful Login Tests
    // ============================================

    public function testLoginSuccessfulWithValidCredentials(): void
    {
        $email = 'user@example.com';
        $password = 'password123';
        $user = $this->createMockUser($email, verified: true);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);

        $this->passwordService
            ->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, $password)
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->login($email, $password);

        $this->assertSame($user, $result);
    }

    public function testLoginUpdatesLastLoginTimestamp(): void
    {
        $email = 'user@example.com';
        $password = 'password123';
        $user = $this->createMockUser($email, verified: true);
        $before = new \DateTimeImmutable();

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);

        $this->passwordService
            ->expects($this->once())
            ->method('isPasswordValid')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Capture the timestamp that was set
        $user->expects($this->once())
            ->method('setLastLoginAt')
            ->with($this->isInstanceOf(\DateTimeImmutable::class));

        $this->service->login($email, $password);
    }

    public function testLoginReturnedUserIsVerified(): void
    {
        $email = 'verified@example.com';
        $password = 'password123';
        $user = $this->createMockUser($email, verified: true);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->passwordService->method('isPasswordValid')->willReturn(true);
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->login($email, $password);

        $this->assertTrue($result->isVerified());
    }

    // ============================================
    // Invalid Credentials Tests
    // ============================================

    public function testLoginThrowsExceptionForInvalidPassword(): void
    {
        $email = 'user@example.com';
        $password = 'wrongpassword';
        $user = $this->createMockUser($email, verified: true);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);

        $this->passwordService
            ->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, $password)
            ->willReturn(false);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid credentials');

        $this->service->login($email, $password);
    }

    public function testLoginThrowsExceptionForNonExistentUser(): void
    {
        $email = 'nonexistent@example.com';
        $password = 'password123';

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn(null);

        $this->passwordService
            ->expects($this->never())
            ->method('isPasswordValid');

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid credentials');

        $this->service->login($email, $password);
    }

    public function testLoginThrowsExceptionForIncorrectPassword(): void
    {
        $email = 'user@example.com';
        $password = 'password123';
        $user = $this->createMockUser($email, verified: true);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->userRepository->method('findByEmail')->willReturn($user);

        $this->passwordService
            ->expects($this->once())
            ->method('isPasswordValid')
            ->willReturn(false);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid credentials');

        $this->service->login($email, $password);
    }

    // ============================================
    // Account Verification Tests
    // ============================================

    public function testLoginThrowsExceptionIfAccountNotVerified(): void
    {
        $email = 'unverified@example.com';
        $password = 'password123';
        $user = $this->createMockUser($email, verified: false);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);

        $this->passwordService
            ->expects($this->once())
            ->method('isPasswordValid')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Account not verified');

        $this->service->login($email, $password);
    }

    public function testLoginDoesNotUpdateLastLoginIfNotVerified(): void
    {
        $email = 'unverified@example.com';
        $password = 'password123';
        $user = $this->createMockUser($email, verified: false);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->passwordService->method('isPasswordValid')->willReturn(true);

        $user->expects($this->never())->method('setLastLoginAt');

        $this->expectException(AuthException::class);

        $this->service->login($email, $password);
    }

    // ============================================
    // Edge Cases Tests
    // ============================================

    public function testLoginWithEmailCaseSensitivity(): void
    {
        $email = 'User@Example.com';
        $password = 'password123';
        $user = $this->createMockUser($email, verified: true);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);

        $this->passwordService
            ->expects($this->once())
            ->method('isPasswordValid')
            ->willReturn(true);

        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->login($email, $password);

        $this->assertSame($user, $result);
    }

    public function testLoginWithEmptyEmail(): void
    {
        $email = '';
        $password = 'password123';

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn(null);

        $this->expectException(AuthException::class);

        $this->service->login($email, $password);
    }

    public function testLoginWithEmptyPassword(): void
    {
        $email = 'user@example.com';
        $password = '';
        $user = $this->createMockUser($email, verified: true);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->userRepository->method('findByEmail')->willReturn($user);

        $this->passwordService
            ->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, '')
            ->willReturn(false);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid credentials');

        $this->service->login($email, $password);
    }

    // ============================================
    // Persistence Tests
    // ============================================

    public function testLoginPersistsChangesToDB(): void
    {
        $email = 'user@example.com';
        $password = 'password123';
        $user = $this->createMockUser($email, verified: true);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->passwordService->method('isPasswordValid')->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush')
            ->with(); // Verify flush is called with no arguments

        $this->service->login($email, $password);
    }

    // ============================================
    // Helper Methods
    // ============================================

    private function createMockUser(string $email, bool $verified = true): MockObject
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn($email);
        $user->method('isVerified')->willReturn($verified);
        return $user;
    }
}
