<?php

namespace App\Repository;

use App\Entity\UserModuleSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserModuleSubscription>
 */
class UserModuleSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserModuleSubscription::class);
    }

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

    public function findAllByUser(string $userId): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
    }
}
