<?php

namespace App\Dto\Auth;

use Symfony\Component\Serializer\Attribute\SerializedName;

class AuthResponse
{
    public string $token;
    public UserResponse $user;

    public static function create(string $token, $user): self
    {
        $response = new self();
        $response->token = $token;
        $response->user = UserResponse::fromUser($user);

        return $response;
    }
}
