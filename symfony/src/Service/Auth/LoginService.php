<?php

namespace App\Service\Auth;

use App\Entity\User;
use App\Exception\AuthException;
use Doctrine\ORM\EntityManagerInterface;

class LoginService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PasswordManagementService $passwordService
    ) {}

    public function login(string $email, string $password): User
    {
        $user = $this->entityManager->getRepository(User::class)->findByEmail($email);

        if (!$user || !$this->passwordService->isPasswordValid($user, $password)) {
            throw new AuthException('Invalid credentials');
        }

        if (!$user->isVerified()) {
            throw new AuthException('Account not verified');
        }

        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $user;
    }
}
