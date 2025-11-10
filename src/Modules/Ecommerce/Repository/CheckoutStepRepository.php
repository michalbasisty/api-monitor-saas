<?php

namespace App\Modules\Ecommerce\Repository;

use App\Modules\Ecommerce\Entity\CheckoutStep;
use App\Modules\Ecommerce\Entity\Store;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CheckoutStep>
 */
class CheckoutStepRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CheckoutStep::class);
    }

    /**
     * @return CheckoutStep[]
     */
    public function findByStore(Store $store): array
    {
        return $this->createQueryBuilder('cs')
            ->andWhere('cs.store = :store')
            ->setParameter('store', $store)
            ->orderBy('cs.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByStoreAndPosition(Store $store, int $position): ?CheckoutStep
    {
        return $this->createQueryBuilder('cs')
            ->andWhere('cs.store = :store')
            ->andWhere('cs.position = :position')
            ->setParameter('store', $store)
            ->setParameter('position', $position)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
