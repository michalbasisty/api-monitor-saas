<?php

namespace App\Modules\Ecommerce\Repository;

use App\Modules\Ecommerce\Entity\Store;
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

    public function findByUser(string $userId): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByUrl(string $storeUrl): ?Store
    {
        return $this->createQueryBuilder('s')
            ->where('s.storeUrl = :url')
            ->setParameter('url', $storeUrl)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
