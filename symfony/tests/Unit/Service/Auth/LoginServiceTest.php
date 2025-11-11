<?php

namespace App\Tests\Unit\Service\Auth;

use App\Entity\User;
use App\Exception\AuthException;
use App\Service\Auth\LoginService;
use App\Service\Auth\PasswordManagementService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class LoginServiceTest extends TestCase
{
    private LoginService $service;
    private MockObject $entityManager;
    private MockObject $passwordService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordService = $this->createMock(PasswordManagementService::class);

        $this->service = new LoginService(
            $this->entityManager,
            $this->passwordService
        );
    }

    public function testLogin_WithValidCredentials_ReturnsUser(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setIsVerified(true);

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())
            ->method('findByEmail')
            ->with('user@example.com')
            ->willReturn($user);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $this->passwordService
            ->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'correct_password')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->login('user@example.com', 'correct_password');

        $this->assertInstanceOf(User::class, $result);
        $this->assertNotNull($result->getLastLoginAt());
    }

    public function testLogin_WithInvalidPassword_ThrowsException(): void
    {
        $user = new User();
        $user->setIsVerified(true);

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())
            ->method('findByEmail')
            ->willReturn($user);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $this->passwordService
            ->expects($this->once())
            ->method('isPasswordValid')
            ->willReturn(false);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid credentials');

        $this->service->login('user@example.com', 'wrong_password');
    }

    public function testLogin_WithUnverifiedAccount_ThrowsException(): void
    {
        $user = new User();
        $user->setIsVerified(false);

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())
            ->method('findByEmail')
            ->willReturn($user);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $this->passwordService
            ->expects($this->once())
            ->method('isPasswordValid')
            ->willReturn(true);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Account not verified');

        $this->service->login('user@example.com', 'correct_password');
    }

    public function testLogin_WithNonexistentUser_ThrowsException(): void
    {
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())
            ->method('findByEmail')
            ->willReturn(null);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid credentials');

        $this->service->login('nonexistent@example.com', 'password');
    }

    public function testLogin_UpdatesLastLoginAt(): void
    {
        $user = new User();
        $user->setIsVerified(true);
        $user->setLastLoginAt(null);

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())
            ->method('findByEmail')
            ->willReturn($user);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $this->passwordService
            ->expects($this->once())
            ->method('isPasswordValid')
            ->willReturn(true);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->login('user@example.com', 'password');

        $this->assertNotNull($user->getLastLoginAt());
    }
}
