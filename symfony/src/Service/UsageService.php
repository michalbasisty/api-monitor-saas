<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class UsageService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getLimitsForUser(User $user): array
    {
        $tier = $user->getSubscriptionTier();

        $limits = [
            'free' => [
                'endpoints' => 5,
                'monitors_per_day' => 100,
                'alerts' => 3,
            ],
            'pro' => [
                'endpoints' => 50,
                'monitors_per_day' => 1000,
                'alerts' => 20,
            ],
            'enterprise' => [
                'endpoints' => 500,
                'monitors_per_day' => 10000,
                'alerts' => 100,
            ],
        ];

        return $limits[$tier] ?? $limits['free'];
    }

    public function checkEndpointLimit(User $user): bool
    {
        $limits = $this->getLimitsForUser($user);

        $endpointCount = $this->entityManager
            ->getRepository(\App\Entity\Endpoint::class)
            ->count(['user' => $user]);

        return $endpointCount < $limits['endpoints'];
    }

    public function checkAlertLimit(User $user): bool
    {
        $limits = $this->getLimitsForUser($user);

        $alertCount = $this->entityManager
            ->getRepository(\App\Entity\Alert::class)
            ->count(['user' => $user]);

        return $alertCount < $limits['alerts'];
    }

    public function checkMonitorLimit(User $user): bool
    {
        $limits = $this->getLimitsForUser($user);

        // Count monitoring results for today
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        $monitorCount = $this->entityManager
            ->getRepository(\App\Entity\MonitoringResult::class)
            ->createQueryBuilder('mr')
            ->join('mr.endpoint', 'e')
            ->where('e.user = :user')
            ->andWhere('mr.checkedAt >= :today')
            ->andWhere('mr.checkedAt < :tomorrow')
            ->setParameter('user', $user)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->select('COUNT(mr.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return $monitorCount < $limits['monitors_per_day'];
    }

    public function getUsageStats(User $user): array
    {
        $limits = $this->getLimitsForUser($user);

        // Current endpoint count
        $endpointCount = $this->entityManager
            ->getRepository(\App\Entity\Endpoint::class)
            ->count(['user' => $user]);

        // Current alert count
        $alertCount = $this->entityManager
            ->getRepository(\App\Entity\Alert::class)
            ->count(['user' => $user]);

        // Today's monitor count
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        $monitorCount = $this->entityManager
            ->getRepository(\App\Entity\MonitoringResult::class)
            ->createQueryBuilder('mr')
            ->join('mr.endpoint', 'e')
            ->where('e.user = :user')
            ->andWhere('mr.checkedAt >= :today')
            ->andWhere('mr.checkedAt < :tomorrow')
            ->setParameter('user', $user)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->select('COUNT(mr.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'tier' => $user->getSubscriptionTier(),
            'limits' => $limits,
            'current' => [
                'endpoints' => (int) $endpointCount,
                'alerts' => (int) $alertCount,
                'monitors_today' => (int) $monitorCount,
            ],
        ];
    }
}
