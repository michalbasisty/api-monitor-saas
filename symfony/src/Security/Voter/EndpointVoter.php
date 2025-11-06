<?php

namespace App\Security\Voter;

use App\Entity\Endpoint;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class EndpointVoter extends Voter
{
    public const VIEW = 'endpoint.view';
    public const EDIT = 'endpoint.edit';
    public const DELETE = 'endpoint.delete';
    public const CREATE = 'endpoint.create';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::CREATE])) {
            return false;
        }

        if ($attribute === self::CREATE) {
            return true;
        }

        return $subject instanceof Endpoint;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if (!$user->isVerified()) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => true,
            self::CREATE => true,
            self::EDIT => $this->canEdit($subject, $user),
            self::DELETE => $this->canDelete($subject, $user),
            default => false,
        };
    }

    private function canEdit(mixed $endpoint, User $user): bool
    {
        if (!$endpoint instanceof Endpoint) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return $endpoint->getUserId() === $user->getId();
    }

    private function canDelete(mixed $endpoint, User $user): bool
    {
        if (!$endpoint instanceof Endpoint) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return $endpoint->getUserId() === $user->getId();
    }
}
