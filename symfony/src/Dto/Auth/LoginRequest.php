<?php

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class LoginRequest
{
    #[Assert\NotBlank(message: 'Username is required')]
    public string $username;

    #[Assert\NotBlank(message: 'Password is required')]
    public string $password;

    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->username = $data['username'] ?? '';
        $request->password = $data['password'] ?? '';

        return $request;
    }
}
