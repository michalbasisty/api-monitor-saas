<?php

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class RegisterRequest
{
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Please provide a valid email address')]
    public string $email;

    #[Assert\NotBlank(message: 'Password is required')]
    #[Assert\Length(min: 8, minMessage: 'Password must be at least 8 characters')]
    public string $password;

    public ?string $companyId = null;

    public ?string $subscriptionTier = 'free';

    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->email = $data['email'] ?? '';
        $request->password = $data['password'] ?? '';
        $request->companyId = $data['company_id'] ?? null;
        $request->subscriptionTier = $data['subscription_tier'] ?? 'free';

        return $request;
    }
}
