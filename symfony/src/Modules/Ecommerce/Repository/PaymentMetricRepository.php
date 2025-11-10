<?php

namespace App\Modules\Ecommerce\Repository;

use App\Modules\Ecommerce\Entity\PaymentGateway;
use App\Modules\Ecommerce\Entity\PaymentMetric;
use App\Modules\Ecommerce\Entity\Store;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentMetric>
 */
class PaymentMetricRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentMetric::class);
    }

    public function getRecentMetrics(Store $store, int $hoursBack = 1): array
    {
        $from = new \DateTime("-{$hoursBack} hours");

        return $this->createQueryBuilder('m')
            ->where('m.store = :store')
            ->andWhere('m.createdAt > :from')
            ->setParameter('store', $store)
            ->setParameter('from', $from)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getAuthorizationSuccessRate(PaymentGateway $gateway, \DateTime $from, \DateTime $to): float
    {
        $total = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.gateway = :gateway')
            ->andWhere('m.createdAt >= :from')
            ->andWhere('m.createdAt <= :to')
            ->setParameter('gateway', $gateway)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return 0;
        }

        $authorized = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.gateway = :gateway')
            ->andWhere('m.status = :status')
            ->andWhere('m.createdAt >= :from')
            ->andWhere('m.createdAt <= :to')
            ->setParameter('gateway', $gateway)
            ->setParameter('status', 'authorized')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        return ($authorized / $total) * 100;
    }

    public function getWebhookReliability(PaymentGateway $gateway, \DateTime $from, \DateTime $to): float
    {
        $total = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.gateway = :gateway')
            ->andWhere('m.createdAt >= :from')
            ->andWhere('m.createdAt <= :to')
            ->setParameter('gateway', $gateway)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return 0;
        }

        $received = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.gateway = :gateway')
            ->andWhere('m.webhookReceived = true')
            ->andWhere('m.createdAt >= :from')
            ->andWhere('m.createdAt <= :to')
            ->setParameter('gateway', $gateway)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        return ($received / $total) * 100;
    }

    public function getAverageAuthTime(PaymentGateway $gateway, \DateTime $from, \DateTime $to): int
    {
        $result = $this->createQueryBuilder('m')
            ->select('AVG(m.authorizationTimeMs) as avg_time')
            ->where('m.gateway = :gateway')
            ->andWhere('m.createdAt >= :from')
            ->andWhere('m.createdAt <= :to')
            ->setParameter('gateway', $gateway)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getOneOrNullResult();

        return (int) ($result['avg_time'] ?? 0);
    }
}
