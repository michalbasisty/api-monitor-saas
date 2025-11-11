<?php

namespace App\Service\Auth;

use App\Entity\User;
use App\Exception\AuthException;
use Doctrine\ORM\EntityManagerInterface;

class EmailVerificationTokenService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function generateVerificationToken(User $user): void
    {
        $token = bin2hex(random_bytes(32));
        $user->setVerificationToken($token);
        $user->setVerificationTokenExpiresAt(new \DateTimeImmutable('+24 hours'));
        $this->entityManager->flush();
    }

    public function verifyToken(string $token): User
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

        return $user;
    }

    public function markAsVerified(User $user): void
    {
        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $user->setVerificationTokenExpiresAt(null);
        $this->entityManager->flush();
    }

    public function generatePasswordResetToken(User $user): void
    {
        $token = bin2hex(random_bytes(32));
        $user->setResetToken($token);
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $this->entityManager->flush();
    }

    public function isTokenValid(User $user): bool
    {
        return $user->getVerificationToken() !== null && 
               $user->getVerificationTokenExpiresAt() > new \DateTimeImmutable();
    }

    public function clearTokens(User $user): void
    {
        $user->setVerificationToken(null);
        $user->setVerificationTokenExpiresAt(null);
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);
        $this->entityManager->flush();
    }
}
