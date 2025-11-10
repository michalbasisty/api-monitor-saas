<?php

namespace App\Modules\Ecommerce\Repository;

use App\Modules\Ecommerce\Entity\CheckoutMetric;
use App\Modules\Ecommerce\Entity\CheckoutStep;
use App\Modules\Ecommerce\Entity\Store;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CheckoutMetric>
 */
class CheckoutMetricRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CheckoutMetric::class);
    }

    public function getRecentMetrics(Store $store, int $minutesBack = 5): array
    {
        $from = new \DateTime("-{$minutesBack} minutes");

        return $this->createQueryBuilder('m')
            ->where('m.store = :store')
            ->andWhere('m.timestamp > :from')
            ->setParameter('store', $store)
            ->setParameter('from', $from)
            ->orderBy('m.timestamp', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getStepMetrics(CheckoutStep $step, \DateTime $from, \DateTime $to): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.step = :step')
            ->andWhere('m.timestamp >= :from')
            ->andWhere('m.timestamp <= :to')
            ->setParameter('step', $step)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('m.timestamp', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getStepErrorRate(CheckoutStep $step, \DateTime $from, \DateTime $to): float
    {
        $total = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.step = :step')
            ->andWhere('m.timestamp >= :from')
            ->andWhere('m.timestamp <= :to')
            ->setParameter('step', $step)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return 0;
        }

        $errors = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.step = :step')
            ->andWhere('m.errorOccurred = true')
            ->andWhere('m.timestamp >= :from')
            ->andWhere('m.timestamp <= :to')
            ->setParameter('step', $step)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        return ($errors / $total) * 100;
    }

    public function getAverageLoadTime(CheckoutStep $step, \DateTime $from, \DateTime $to): int
    {
        $result = $this->createQueryBuilder('m')
            ->select('AVG(m.loadTimeMs) as avg_time')
            ->where('m.step = :step')
            ->andWhere('m.timestamp >= :from')
            ->andWhere('m.timestamp <= :to')
            ->setParameter('step', $step)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getOneOrNullResult();

        return (int) ($result['avg_time'] ?? 0);
    }
}
