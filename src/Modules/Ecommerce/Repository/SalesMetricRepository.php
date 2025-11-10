<?php

namespace App\Modules\Ecommerce\Repository;

use App\Modules\Ecommerce\Entity\SalesMetric;
use App\Modules\Ecommerce\Entity\Store;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SalesMetric>
 */
class SalesMetricRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SalesMetric::class);
    }

    /**
     * @return SalesMetric[]
     */
    public function findByStore(Store $store, int $limit = 100): array
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('sm.store = :store')
            ->setParameter('store', $store)
            ->orderBy('sm.timestamp', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return SalesMetric[]
     */
    public function findByStoreAndDateRange(Store $store, \DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('sm.store = :store')
            ->andWhere('sm.timestamp >= :start')
            ->andWhere('sm.timestamp <= :end')
            ->setParameter('store', $store)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('sm.timestamp', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getAggregatedMetrics(Store $store, \DateTime $startDate, \DateTime $endDate): array
    {
        $result = $this->createQueryBuilder('sm')
            ->select('
                SUM(sm.revenue) as totalRevenue,
                SUM(sm.orderCount) as totalOrders,
                AVG(sm.avgOrderValue) as avgOrderValue,
                SUM(sm.conversionRate * sm.orderCount) / SUM(sm.orderCount) as avgConversionRate
            ')
            ->andWhere('sm.store = :store')
            ->andWhere('sm.timestamp >= :start')
            ->andWhere('sm.timestamp <= :end')
            ->setParameter('store', $store)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getOneOrNullResult();

        return $result ?? [];
    }
}
