<?php

namespace App\Tests\Unit\Service\Auth;

use App\Entity\User;
use App\Exception\AuthException;
use App\Service\Auth\PasswordManagementService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use PHPUnit\Framework\MockObject\MockObject;

class PasswordManagementServiceTest extends TestCase
{
    private PasswordManagementService $service;
    private MockObject $entityManager;
    private MockObject $passwordHasher;
    private MockObject $validator;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->service = new PasswordManagementService(
            $this->entityManager,
            $this->passwordHasher,
            $this->validator
        );
    }

    public function testChangePassword_WithCorrectPassword_UpdatesPassword(): void
    {
        $user = new User();
        $user->setPassword('hashed_old_password');

        $this->passwordHasher
            ->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'oldpass')
            ->willReturn(true);

        $this->validator
            ->expects($this->once())
            ->method('validateProperty')
            ->willReturn([]);

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashed_new_password');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->changePassword($user, 'oldpass', 'newpass');

        $this->assertEquals('hashed_new_password', $user->getPassword());
    }

    public function testChangePassword_WithIncorrectPassword_ThrowsException(): void
    {
        $user = new User();
        $user->setPassword('hashed_password');

        $this->passwordHasher
            ->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'wrongpass')
            ->willReturn(false);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Current password is incorrect');

        $this->service->changePassword($user, 'wrongpass', 'newpass');
    }

    public function testChangePassword_WithInvalidNewPassword_ThrowsException(): void
    {
        $user = new User();

        $this->passwordHasher
            ->expects($this->once())
            ->method('isPasswordValid')
            ->willReturn(true);

        $validationError = $this->createMock(\Symfony\Component\Validator\ConstraintViolation::class);
        $this->validator
            ->expects($this->once())
            ->method('validateProperty')
            ->willReturn([$validationError]);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Password validation failed');

        $this->service->changePassword($user, 'oldpass', 'weak');
    }

    public function testResetPassword_WithValidToken_UpdatesPassword(): void
    {
        $user = new User();
        $user->setResetToken('valid_token');
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->willReturn($this->createMock(\Doctrine\ORM\EntityRepository::class));

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($user);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $this->validator
            ->expects($this->once())
            ->method('validateProperty')
            ->willReturn([]);

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashed_new_password');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->resetPassword('valid_token', 'newpass');

        $this->assertNull($result->getResetToken());
        $this->assertNull($result->getResetTokenExpiresAt());
    }

    public function testResetPassword_WithExpiredToken_ThrowsException(): void
    {
        $user = new User();
        $user->setResetToken('expired_token');
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('-1 hour'));

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($user);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Token has expired');

        $this->service->resetPassword('expired_token', 'newpass');
    }

    public function testHashPassword_ReturnsHashedPassword(): void
    {
        $user = new User();

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'mypassword')
            ->willReturn('hashed_password');

        $result = $this->service->hashPassword($user, 'mypassword');

        $this->assertEquals('hashed_password', $result);
    }

    public function testIsPasswordValid_WithCorrectPassword_ReturnsTrue(): void
    {
        $user = new User();

        $this->passwordHasher
            ->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'correct_password')
            ->willReturn(true);

        $result = $this->service->isPasswordValid($user, 'correct_password');

        $this->assertTrue($result);
    }

    public function testIsPasswordValid_WithIncorrectPassword_ReturnsFalse(): void
    {
        $user = new User();

        $this->passwordHasher
            ->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'wrong_password')
            ->willReturn(false);

        $result = $this->service->isPasswordValid($user, 'wrong_password');

        $this->assertFalse($result);
    }
}
