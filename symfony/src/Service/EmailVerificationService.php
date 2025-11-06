<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;

class EmailVerificationService
{
    private const TOKEN_EXPIRY_HOURS = 24;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer
    ) {}

    public function generateVerificationToken(User $user): string
    {
        $token = Uuid::v4()->toRfc4122();
        $expiresAt = new \DateTimeImmutable('+' . self::TOKEN_EXPIRY_HOURS . ' hours');

        $user->setVerificationToken($token);
        $user->setVerificationTokenExpiresAt($expiresAt);

        $this->entityManager->flush();

        return $token;
    }

    public function verifyEmail(string $token): ?User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'verification_token' => $token
        ]);

        if (!$user) {
            return null;
        }

        if ($user->isVerified()) {
            return null;
        }

        $expiresAt = $user->getVerificationTokenExpiresAt();
        if ($expiresAt && $expiresAt < new \DateTimeImmutable()) {
            return null;
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $user->setVerificationTokenExpiresAt(null);

        $this->entityManager->flush();

        return $user;
    }

    public function isTokenValid(User $user): bool
    {
        if ($user->isVerified()) {
            return false;
        }

        $expiresAt = $user->getVerificationTokenExpiresAt();
        if (!$expiresAt) {
            return false;
        }

        return $expiresAt >= new \DateTimeImmutable();
    }

    public function sendVerificationEmail(User $user): void
    {
        $token = $user->getVerificationToken();
        if (!$token) {
            throw new \InvalidArgumentException('No verification token found for user');
        }

        $email = (new Email())
            ->from('noreply@apimonitor.com')
            ->to($user->getEmail())
            ->subject('Verify your email address')
            ->html('<p>Please click the link to verify your email: <a href="http://localhost/api/auth/verify-email/' . $token . '">Verify Email</a></p>');

        $this->mailer->send($email);
    }

    public function sendPasswordResetEmail(User $user): void
    {
        $token = $user->getResetToken();
        if (!$token) {
            throw new \InvalidArgumentException('No reset token found for user');
        }

        $email = (new Email())
            ->from('noreply@example.com')
            ->to($user->getEmail())
            ->subject('Reset your password')
            ->html('<p>Please click the link to reset your password: <a href="http://localhost/api/auth/reset-password/' . $token . '">Reset Password</a></p>');

        $this->mailer->send($email);
    }
}
