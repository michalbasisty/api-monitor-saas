<?php

namespace App\Security;

use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class SimpleUserPasswordHasher implements UserPasswordHasherInterface
{
    public function hashPassword(PasswordAuthenticatedUserInterface $user, string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_DEFAULT);
    }

    public function isPasswordValid(PasswordAuthenticatedUserInterface $user, string $plainPassword): bool
    {
        $hash = $user->getPassword();
        if ($hash === null) {
            return false;
        }
        return password_verify($plainPassword, $hash);
    }

    public function needsRehash(PasswordAuthenticatedUserInterface $user): bool
    {
        $hash = $user->getPassword();
        if ($hash === null) {
            return false;
        }
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}
