<?php

namespace App\Service\Auth;

use App\Entity\User;
use App\Exception\AuthException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PasswordManagementService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator
    ) {}

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            throw new AuthException('Current password is incorrect');
        }

        $this->validatePassword($newPassword);

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $user->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
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

        $this->validatePassword($password);

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);

        $this->entityManager->flush();

        return $user;
    }

    public function hashPassword(User $user, string $password): string
    {
        return $this->passwordHasher->hashPassword($user, $password);
    }

    public function isPasswordValid(User $user, string $password): bool
    {
        return $this->passwordHasher->isPasswordValid($user, $password);
    }

    private function validatePassword(string $password): void
    {
        $tempUser = new User();
        $errors = $this->validator->validateProperty($tempUser, 'password', $password);
        if (count($errors) > 0) {
            throw new AuthException('Password validation failed');
        }
    }
}
