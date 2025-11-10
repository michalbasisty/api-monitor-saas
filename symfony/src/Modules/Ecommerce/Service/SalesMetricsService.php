<?php

namespace App\Modules\Ecommerce\Service;

use App\Exception\StoreNotFoundException;
use App\Modules\Ecommerce\Entity\SalesMetric;
use App\Modules\Ecommerce\Entity\Store;
use Doctrine\ORM\EntityManagerInterface;

class SalesMetricsService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function getStoreById(string $storeId): Store
    {
        $store = $this->em->getRepository(Store::class)->find($storeId);
        if (!$store) {
            throw new StoreNotFoundException($storeId);
        }
        return $store;
    }

    public function recordSalesMetric(Store $store, array $data): SalesMetric
    {
        $metric = new SalesMetric();
        $metric->setStore($store);
        $metric->setTimestamp($data['timestamp'] ?? new \DateTime());
        $metric->setRevenuePerMinute($data['revenue_per_minute'] ?? null);
        $metric->setOrdersPerMinute($data['orders_per_minute'] ?? null);
        $metric->setCheckoutSuccessRate($data['checkout_success_rate'] ?? null);
        $metric->setAvgOrderValue($data['avg_order_value'] ?? null);
        $metric->setEstimatedLostRevenue($data['estimated_lost_revenue'] ?? null);
        $metric->setStatus($data['status'] ?? 'normal');

        $this->em->persist($metric);
        $this->em->flush();

        return $metric;
    }

    public function getLatestMetric(Store $store): ?SalesMetric
    {
        return $this->em->getRepository(SalesMetric::class)
            ->createQueryBuilder('m')
            ->where('m.store = :store')
            ->setParameter('store', $store)
            ->orderBy('m.timestamp', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getMetricsForPeriod(Store $store, \DateTime $from, \DateTime $to): array
    {
        return $this->em->getRepository(SalesMetric::class)
            ->createQueryBuilder('m')
            ->where('m.store = :store')
            ->andWhere('m.timestamp >= :from')
            ->andWhere('m.timestamp <= :to')
            ->setParameter('store', $store)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('m.timestamp', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function calculateLostRevenue(Store $store, \DateTime $from, \DateTime $to): string
    {
        // Get metrics for the period and sum estimated lost revenue
        $metrics = $this->getMetricsForPeriod($store, $from, $to);
        $totalLost = '0.00';

        foreach ($metrics as $metric) {
            if ($metric->getEstimatedLostRevenue()) {
                $totalLost = bcadd($totalLost, $metric->getEstimatedLostRevenue(), 2);
            }
        }

        return $totalLost;
    }
}
