<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\AuthException;
use Doctrine\ORM\EntityManagerInterface;
// Use Symfony's password hasher interface and other services
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\EmailVerificationService;
use Symfony\Component\Uid\Uuid;

class AuthService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
        private EmailVerificationService $emailVerificationService
    ) {}

    public function register(array $data): User
    {
        $this->validateRegistrationData($data);

        if ($this->entityManager->getRepository(User::class)->findByEmail($data['email'])) {
        throw new AuthException('User already exists');
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));

        // Optional fields
        if (isset($data['company_id'])) {
            $user->setCompanyId($data['company_id']);
        }
        if (isset($data['subscription_tier'])) {
            $user->setSubscriptionTier($data['subscription_tier']);
        }

        $this->entityManager->persist($user);

        // Generate verification token
        $token = bin2hex(random_bytes(32));
        $user->setVerificationToken($token);
        $user->setVerificationTokenExpiresAt(new \DateTimeImmutable('+24 hours'));

        $this->entityManager->flush();

        // Send verification email (non-blocking)
        try {
            $this->emailVerificationService->sendVerificationEmail($user);
        } catch (\Exception $e) {
            error_log('Failed to send verification email: ' . $e->getMessage());
        }

        return $user;
    }

    public function verifyEmail(string $token): User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'verificationToken' => $token
        ]);

        if (!$user) {
        throw new AuthException('Invalid verification token');
        }

        if ($user->getVerificationTokenExpiresAt() < new \DateTimeImmutable()) {
        throw new AuthException('Verification token has expired');
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $user->setVerificationTokenExpiresAt(null);

        $this->entityManager->flush();

        return $user;
    }

    public function login(string $email, string $password): User
    {
        $user = $this->entityManager->getRepository(User::class)->findByEmail($email);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
        throw new AuthException('Invalid credentials');
        }

        if (!$user->isVerified()) {
        throw new AuthException('Account not verified');
        }

        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $user;
    }

    public function forgotPassword(string $email): void
    {
        $user = $this->entityManager->getRepository(User::class)->findByEmail($email);

        if (!$user) {
            // Don't reveal if email exists for security
            return;
        }

        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $user->setResetToken($token);
        $user->setResetTokenExpiresAt($expiresAt);

        $this->entityManager->flush();

        // Send password reset email (non-blocking)
        try {
            $this->emailVerificationService->sendPasswordResetEmail($user);
        } catch (\Exception $e) {
            error_log('Failed to send password reset email: ' . $e->getMessage());
        }
    }

    public function resetPassword(string $token, string $password): User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['resetToken' => $token]);

        if (!$user) {
        throw new AuthException('Invalid or expired token');
        }

        if ($user->getResetTokenExpiresAt() < new \DateTimeImmutable()) {
        throw new AuthException('Token has expired');
        }

        // Validate password
        $tempUser = new User();
        $errors = $this->validator->validateProperty($tempUser, 'password', $password);
        if (count($errors) > 0) {
            throw new AuthException('Password validation failed');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);

        $this->entityManager->flush();

        return $user;
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        // Verify current password
        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            throw new AuthException('Current password is incorrect');
        }

        // Validate new password
        $tempUser = new User();
        $errors = $this->validator->validateProperty($tempUser, 'password', $newPassword);
        if (count($errors) > 0) {
            throw new AuthException('New password validation failed');
        }

        // Hash and set new password
        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $user->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    public function updateProfile(User $user, array $data): User
    {
        $hasChanges = false;

        // Update company association
        if (isset($data['company_id'])) {
            $companyId = $data['company_id'] ? Uuid::fromString($data['company_id']) : null;
            $user->setCompanyId($companyId);
            $hasChanges = true;
        }

        // Validate changes
        if ($hasChanges) {
            $errors = $this->validator->validate($user);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                throw new AuthException('Validation failed: ' . implode(', ', $errorMessages));
            }

            $user->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }

        return $user;
    }

    public function deleteAccount(User $user): void
    {
        // Mark user as inactive instead of deleting (for data integrity)
        $user->setRoles([]); // Remove all roles
        $user->setIsVerified(false);
        $user->setUpdatedAt(new \DateTimeImmutable());

        // You might want to add an 'is_active' field to User entity
        // For now, we'll just update the user

        $this->entityManager->flush();
    }

    public function resendVerificationEmail(string $email): void
    {
        $user = $this->entityManager->getRepository(User::class)->findByEmail($email);

        if (!$user) {
            // Don't reveal if email exists
            return;
        }

        if ($user->isVerified()) {
            throw new AuthException('Account is already verified');
        }

        // Check if token is still valid
        if ($user->getVerificationToken() && $this->emailVerificationService->isTokenValid($user)) {
        // Resend existing token
        try {
            $this->emailVerificationService->sendVerificationEmail($user);
            } catch (\Exception $e) {
                 error_log('Failed to resend verification email: ' . $e->getMessage());
            }
            return;
        }

         // Generate new token
        $token = bin2hex(random_bytes(32));
         $user->setVerificationToken($token);
        $user->setVerificationTokenExpiresAt(new \DateTimeImmutable('+24 hours'));

         $this->entityManager->flush();

         try {
             $this->emailVerificationService->sendVerificationEmail($user);
         } catch (\Exception $e) {
             error_log('Failed to send verification email: ' . $e->getMessage());
         }
     }

    public function getUserProfile(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'subscription_tier' => $user->getSubscriptionTier(),
            'company_id' => $user->getCompanyId(),
            'is_verified' => $user->isVerified(),
            'created_at' => $user->getCreatedAt()->format('c'),
            'last_login_at' => $user->getLastLoginAt()?->format('c'),
        ];
    }

    private function validateRegistrationData(array $data): void
    {
        if (!isset($data['email']) || !isset($data['password'])) {
            throw new AuthException('Email and password are required');
        }

        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new AuthException('Invalid email format');
        }

        // Validate password using Symfony validator
        $tempUser = new User();
        $tempUser->setEmail($data['email']); // Set email for validation
        $tempUser->setPassword($data['password']); // Though password is hashed later, for validation
        $errors = $this->validator->validate($tempUser);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            throw new AuthException('Validation failed: ' . implode(', ', $errorMessages));
        }
    }
}
