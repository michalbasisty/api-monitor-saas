<?php

namespace App\Repository;

use App\Entity\UserModuleSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for UserModuleSubscription entities.
 *
 * @extends ServiceEntityRepository<UserModuleSubscription>
 */
class UserModuleSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserModuleSubscription::class);
    }

    /**
     * Check whether a module is enabled for a given user.
     */
    public function isModuleEnabled(string $userId, string $moduleName): bool
    {
        $result = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.user = :userId')
            ->andWhere('u.moduleName = :moduleName')
            ->andWhere('u.enabled = true')
            ->setParameter('userId', $userId)
            ->setParameter('moduleName', $moduleName)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result > 0;
    }

    /**
     * Find a subscription by user and module.
     */
    public function findByUserAndModule(string $userId, string $moduleName): ?UserModuleSubscription
    {
        return $this->createQueryBuilder('u')
            ->where('u.user = :userId')
            ->andWhere('u.moduleName = :moduleName')
            ->setParameter('userId', $userId)
            ->setParameter('moduleName', $moduleName)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * List all subscriptions for a given user.
     *
     * @return UserModuleSubscription[]
     */
    public function findAllByUser(string $userId): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
    }
}
