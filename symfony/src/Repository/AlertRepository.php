<?php

namespace App\Repository;

use App\Entity\Alert;
use App\Entity\Endpoint;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Alert::class);
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.user_id = :userId')
            ->setParameter('userId', $user->getId())
            ->orderBy('a.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByUserAndId(User $user, string $id): ?Alert
    {
        return $this->createQueryBuilder('a')
            ->where('a.id = :id')
            ->andWhere('a.user_id = :userId')
            ->setParameter('id', $id)
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByEndpoint(Endpoint $endpoint): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.endpoint_id = :endpointId')
            ->setParameter('endpointId', $endpoint->getId())
            ->orderBy('a.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveByEndpoint(Endpoint $endpoint): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.endpoint_id = :endpointId')
            ->andWhere('a.is_active = :active')
            ->setParameter('endpointId', $endpoint->getId())
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    public function findActiveAlerts(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.is_active = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    public function countByUser(User $user): int
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.user_id = :userId')
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
