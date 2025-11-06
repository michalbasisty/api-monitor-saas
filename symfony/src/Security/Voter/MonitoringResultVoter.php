<?php

namespace App\Security\Voter;

use App\Entity\MonitoringResult;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class MonitoringResultVoter extends Voter
{
    public const VIEW = 'monitoring.view';
    public const EXPORT = 'monitoring.export';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::EXPORT])) {
            return false;
        }

        return $subject instanceof MonitoringResult;
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
            self::VIEW => $this->canView($subject, $user),
            self::EXPORT => $this->canExport($subject, $user),
            default => false,
        };
    }

    private function canView(mixed $result, User $user): bool
    {
        if (!$result instanceof MonitoringResult) {
            return false;
        }

        // Users can view results for their own endpoints
        // This would need to be checked via endpoint ownership
        return true; // Simplified - would check endpoint ownership in real implementation
    }

    private function canExport(mixed $result, User $user): bool
    {
        // Export permissions could be restricted to premium users
        return in_array('ROLE_USER', $user->getRoles(), true);
    }
}
