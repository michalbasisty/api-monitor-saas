<?php

namespace App\Service\Auth;

use App\Entity\User;
use App\Exception\AuthException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegistrationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private PasswordManagementService $passwordService,
        private EmailVerificationTokenService $tokenService
    ) {}

    public function register(array $data): User
    {
        $this->validateRegistrationData($data);

        if ($this->entityManager->getRepository(User::class)->findByEmail($data['email'])) {
            throw new AuthException('User already exists');
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setPassword($this->passwordService->hashPassword($user, $data['password']));

        if (isset($data['company_id'])) {
            $user->setCompanyId(Uuid::fromString($data['company_id']));
        }
        if (isset($data['subscription_tier'])) {
            $user->setSubscriptionTier($data['subscription_tier']);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->tokenService->generateVerificationToken($user);

        return $user;
    }

    private function validateRegistrationData(array $data): void
    {
        if (!isset($data['email']) || !isset($data['password'])) {
            throw new AuthException('Email and password are required');
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new AuthException('Invalid email format');
        }

        $tempUser = new User();
        $tempUser->setEmail($data['email']);
        $tempUser->setPassword($data['password']);
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
