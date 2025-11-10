<?php

namespace App\Repository;

use App\Entity\UserModuleSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

class UserModuleSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserModuleSubscription::class);
    }

    /**
     * Check if a module is enabled for a user
     */
    public function isModuleEnabled(Uuid $userId, string $moduleName): bool
    {
        $result = $this->findOneBy([
            'user' => $userId,
            'moduleName' => $moduleName,
            'enabled' => true,
        ]);

        return $result !== null;
    }

    /**
     * Get all enabled modules for a user
     */
    public function findEnabledModulesForUser(Uuid $userId): array
    {
        return $this->findBy([
            'user' => $userId,
            'enabled' => true,
        ]);
    }

    /**
     * Get all modules for a user (enabled and disabled)
     */
    public function findAllModulesForUser(Uuid $userId): array
    {
        return $this->findBy([
            'user' => $userId,
        ]);
    }

    /**
     * Get usage stats for a module across all users
     */
    public function getModuleStats(string $moduleName): array
    {
        $qb = $this->createQueryBuilder('m');

        $total = $qb
            ->select('COUNT(m.id) as total')
            ->where('m.moduleName = :moduleName')
            ->setParameter('moduleName', $moduleName)
            ->getQuery()
            ->getSingleScalarResult();

        $qb = $this->createQueryBuilder('m');
        $enabled = $qb
            ->select('COUNT(m.id) as enabled')
            ->where('m.moduleName = :moduleName')
            ->andWhere('m.enabled = true')
            ->setParameter('moduleName', $moduleName)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'module' => $moduleName,
            'total_subscriptions' => (int) $total,
            'enabled_subscriptions' => (int) $enabled,
            'adoption_rate' => $total > 0 ? ($enabled / $total) * 100 : 0,
        ];
    }

    /**
     * Get module adoption by tier
     */
    public function getModuleAdoptionByTier(string $moduleName): array
    {
        $qb = $this->createQueryBuilder('m')
            ->select('m.tier, COUNT(m.id) as count, SUM(CASE WHEN m.enabled = true THEN 1 ELSE 0 END) as enabled_count')
            ->where('m.moduleName = :moduleName')
            ->setParameter('moduleName', $moduleName)
            ->groupBy('m.tier')
            ->getQuery()
            ->getArrayResult();

        return $qb;
    }
}
