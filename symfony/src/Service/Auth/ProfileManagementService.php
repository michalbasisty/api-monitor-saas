<?php

namespace App\Service\Auth;

use App\Entity\User;
use App\Exception\AuthException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProfileManagementService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
    ) {}

    public function updateProfile(User $user, array $data): User
    {
        $hasChanges = false;

        if (isset($data['company_id'])) {
            $companyId = $data['company_id'] ? Uuid::fromString($data['company_id']) : null;
            $user->setCompanyId($companyId);
            $hasChanges = true;
        }

        if ($hasChanges) {
            $this->validateUser($user);
            $user->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }

        return $user;
    }

    public function deleteAccount(User $user): void
    {
        $user->setRoles([]);
        $user->setIsVerified(false);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
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

    private function validateUser(User $user): void
    {
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            throw new AuthException('Validation failed: ' . implode(', ', $errorMessages));
        }
    }
}
