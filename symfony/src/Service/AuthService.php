<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\AuthException;
use App\Service\Auth\RegistrationService;
use App\Service\Auth\EmailVerificationTokenService;
use App\Service\Auth\PasswordManagementService;
use App\Service\Auth\LoginService;
use App\Service\Auth\ProfileManagementService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AuthService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RegistrationService $registrationService,
        private EmailVerificationTokenService $tokenService,
        private PasswordManagementService $passwordService,
        private LoginService $loginService,
        private ProfileManagementService $profileService,
        private EmailVerificationService $emailVerificationService,
        private LoggerInterface $logger
    ) {}

    public function register(array $data): User
    {
        $user = $this->registrationService->register($data);
        
        try {
            $this->emailVerificationService->sendVerificationEmail($user);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send verification email', ['error' => $e->getMessage()]);
        }

        return $user;
    }

    public function verifyEmail(string $token): User
    {
        $user = $this->tokenService->verifyToken($token);
        $this->tokenService->markAsVerified($user);
        return $user;
    }

    public function login(string $email, string $password): User
    {
        return $this->loginService->login($email, $password);
    }

    public function forgotPassword(string $email): void
    {
        $user = $this->entityManager->getRepository(User::class)->findByEmail($email);

        if (!$user) {
            return; // Don't reveal if email exists
        }

        $this->tokenService->generatePasswordResetToken($user);

        try {
            $this->emailVerificationService->sendPasswordResetEmail($user);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send password reset email', ['error' => $e->getMessage()]);
        }
    }

    public function resetPassword(string $token, string $password): User
    {
        return $this->passwordService->resetPassword($token, $password);
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        $this->passwordService->changePassword($user, $currentPassword, $newPassword);
    }

    public function updateProfile(User $user, array $data): User
    {
        return $this->profileService->updateProfile($user, $data);
    }

    public function deleteAccount(User $user): void
    {
        $this->profileService->deleteAccount($user);
    }

    public function resendVerificationEmail(string $email): void
    {
        $user = $this->entityManager->getRepository(User::class)->findByEmail($email);

        if (!$user) {
            return; // Don't reveal if email exists
        }

        if ($user->isVerified()) {
            throw new AuthException('Account is already verified');
        }

        if ($user->getVerificationToken() && $this->tokenService->isTokenValid($user)) {
            try {
                $this->emailVerificationService->sendVerificationEmail($user);
            } catch (\Exception $e) {
                $this->logger->error('Failed to resend verification email', ['error' => $e->getMessage()]);
            }
            return;
        }

        $this->tokenService->generateVerificationToken($user);

        try {
            $this->emailVerificationService->sendVerificationEmail($user);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send verification email', ['error' => $e->getMessage()]);
        }
    }

    public function getUserProfile(User $user): array
    {
        return $this->profileService->getUserProfile($user);
    }
}
