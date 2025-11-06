<?php

namespace App\Dto\Auth;

class UserResponse
{
    public string $id;
    public string $email;
    public array $roles;
    public string $subscriptionTier;
    public bool $isVerified;
    public ?string $companyId;
    public string $createdAt;
    public ?string $lastLoginAt;

    public static function fromUser($user): self
    {
        $response = new self();
        $response->id = $user->getId();
        $response->email = $user->getEmail();
        $response->roles = $user->getRoles();
        $response->subscriptionTier = $user->getSubscriptionTier();
        $response->isVerified = $user->isVerified();
        $response->companyId = $user->getCompanyId();
        $response->createdAt = $user->getCreatedAt()->format('c');
        $response->lastLoginAt = $user->getLastLoginAt()?->format('c');

        return $response;
    }
}
