<?php

namespace App\Modules\Ecommerce\Repository;

use App\Modules\Ecommerce\Entity\EcommerceAlert;
use App\Modules\Ecommerce\Entity\Store;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EcommerceAlert>
 */
class AlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EcommerceAlert::class);
    }

    /**
     * @return EcommerceAlert[]
     */
    public function findByStore(Store $store, int $limit = 100): array
    {
        return $this->createQueryBuilder('ea')
            ->andWhere('ea.store = :store')
            ->setParameter('store', $store)
            ->orderBy('ea.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return EcommerceAlert[]
     */
    public function findByStoreAndStatus(Store $store, string $status): array
    {
        return $this->createQueryBuilder('ea')
            ->andWhere('ea.store = :store')
            ->andWhere('ea.status = :status')
            ->setParameter('store', $store)
            ->setParameter('status', $status)
            ->orderBy('ea.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return EcommerceAlert[]
     */
    public function findByStoreAndType(Store $store, string $type): array
    {
        return $this->createQueryBuilder('ea')
            ->andWhere('ea.store = :store')
            ->andWhere('ea.type = :type')
            ->setParameter('store', $store)
            ->setParameter('type', $type)
            ->orderBy('ea.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return EcommerceAlert[]
     */
    public function findBySeverity(Store $store, string $severity): array
    {
        return $this->createQueryBuilder('ea')
            ->andWhere('ea.store = :store')
            ->andWhere('ea.severity = :severity')
            ->setParameter('store', $store)
            ->setParameter('severity', $severity)
            ->orderBy('ea.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
