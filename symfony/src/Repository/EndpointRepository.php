<?php

namespace App\Repository;

use App\Entity\Endpoint;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EndpointRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Endpoint::class);
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.user_id = :userId')
            ->setParameter('userId', $user->getId())
            ->orderBy('e.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByUserAndId(User $user, string $id): ?Endpoint
    {
        return $this->createQueryBuilder('e')
            ->where('e.id = :id')
            ->andWhere('e.user_id = :userId')
            ->setParameter('id', $id)
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveEndpoints(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.is_active = :active')
            ->setParameter('active', true)
            ->orderBy('e.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByUser(User $user): int
    {
        return $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.user_id = :userId')
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
