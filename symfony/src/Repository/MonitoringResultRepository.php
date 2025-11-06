<?php

namespace App\Repository;

use App\Entity\Endpoint;
use App\Entity\MonitoringResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MonitoringResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MonitoringResult::class);
    }

    public function findByEndpoint(Endpoint $endpoint, int $limit = 100): array
    {
        return $this->createQueryBuilder('mr')
            ->where('mr.endpoint_id = :endpointId')
            ->setParameter('endpointId', $endpoint->getId())
            ->orderBy('mr.checked_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByEndpointInTimeRange(
        Endpoint $endpoint,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): array {
        return $this->createQueryBuilder('mr')
            ->where('mr.endpoint_id = :endpointId')
            ->andWhere('mr.checked_at >= :from')
            ->andWhere('mr.checked_at <= :to')
            ->setParameter('endpointId', $endpoint->getId())
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('mr.checked_at', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getLatestResult(Endpoint $endpoint): ?MonitoringResult
    {
        return $this->createQueryBuilder('mr')
            ->where('mr.endpoint_id = :endpointId')
            ->setParameter('endpointId', $endpoint->getId())
            ->orderBy('mr.checked_at', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getAverageResponseTime(Endpoint $endpoint, int $lastHours = 24): ?float
    {
        $from = new \DateTimeImmutable("-{$lastHours} hours");

        $result = $this->createQueryBuilder('mr')
            ->select('AVG(mr.response_time) as avg_time')
            ->where('mr.endpoint_id = :endpointId')
            ->andWhere('mr.checked_at >= :from')
            ->andWhere('mr.response_time IS NOT NULL')
            ->setParameter('endpointId', $endpoint->getId())
            ->setParameter('from', $from)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float) $result : null;
    }

    public function getUptime(Endpoint $endpoint, int $lastHours = 24): float
    {
        $from = new \DateTimeImmutable("-{$lastHours} hours");

        $total = $this->createQueryBuilder('mr')
            ->select('COUNT(mr.id)')
            ->where('mr.endpoint_id = :endpointId')
            ->andWhere('mr.checked_at >= :from')
            ->setParameter('endpointId', $endpoint->getId())
            ->setParameter('from', $from)
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return 0.0;
        }

        $successful = $this->createQueryBuilder('mr')
            ->select('COUNT(mr.id)')
            ->where('mr.endpoint_id = :endpointId')
            ->andWhere('mr.checked_at >= :from')
            ->andWhere('mr.status_code >= 200')
            ->andWhere('mr.status_code < 300')
            ->andWhere('mr.error_message IS NULL')
            ->setParameter('endpointId', $endpoint->getId())
            ->setParameter('from', $from)
            ->getQuery()
            ->getSingleScalarResult();

        return ($successful / $total) * 100;
    }

    public function deleteOlderThan(\DateTimeImmutable $date): int
    {
        return $this->createQueryBuilder('mr')
            ->delete()
            ->where('mr.checked_at < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
