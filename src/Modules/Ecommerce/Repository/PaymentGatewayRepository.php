<?php

namespace App\Modules\Ecommerce\Repository;

use App\Modules\Ecommerce\Entity\PaymentGateway;
use App\Modules\Ecommerce\Entity\Store;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentGateway>
 */
class PaymentGatewayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentGateway::class);
    }

    /**
     * @return PaymentGateway[]
     */
    public function findByStore(Store $store): array
    {
        return $this->createQueryBuilder('pg')
            ->andWhere('pg.store = :store')
            ->setParameter('store', $store)
            ->orderBy('pg.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PaymentGateway[]
     */
    public function findActiveByStore(Store $store): array
    {
        return $this->createQueryBuilder('pg')
            ->andWhere('pg.store = :store')
            ->andWhere('pg.isActive = true')
            ->setParameter('store', $store)
            ->getQuery()
            ->getResult();
    }

    public function findByStoreAndProvider(Store $store, string $provider): ?PaymentGateway
    {
        return $this->createQueryBuilder('pg')
            ->andWhere('pg.store = :store')
            ->andWhere('pg.provider = :provider')
            ->setParameter('store', $store)
            ->setParameter('provider', $provider)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
