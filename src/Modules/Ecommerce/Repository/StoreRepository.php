<?php

namespace App\Modules\Ecommerce\Repository;

use App\Modules\Ecommerce\Entity\Store;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Store>
 */
class StoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Store::class);
    }

    /**
     * @return Store[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByUserAndUrl(User $user, string $storeUrl): ?Store
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->andWhere('s.storeUrl = :url')
            ->setParameter('user', $user)
            ->setParameter('url', $storeUrl)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Store[]
     */
    public function findByPlatform(string $platform): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.platform = :platform')
            ->setParameter('platform', $platform)
            ->getQuery()
            ->getResult();
    }
}
