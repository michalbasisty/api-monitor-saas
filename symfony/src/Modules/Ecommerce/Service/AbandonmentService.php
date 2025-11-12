<?php

namespace App\Modules\Ecommerce\Service;

use App\Modules\Ecommerce\Entity\Abandonment;
use App\Modules\Ecommerce\Entity\CheckoutStep;
use App\Modules\Ecommerce\Entity\Store;
use Doctrine\ORM\EntityManagerInterface;

class AbandonmentService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function recordAbandonment(
        Store $store,
        string $sessionId,
        CheckoutStep $abandonedAtStep,
        string $reason = null
    ): Abandonment {
        $abandonment = new Abandonment();
        $abandonment->setStore($store);
        $abandonment->setSessionId($sessionId);
        $abandonment->setStartedAt(new \DateTime());
        $abandonment->setAbandonedAtStep($abandonedAtStep);
        $abandonment->setLastSeen(new \DateTime());
        $abandonment->setReason($reason);

        $this->em->persist($abandonment);
        $this->em->flush();

        return $abandonment;
    }

    public function getAbandonmentRate(Store $store, \DateTime $from, \DateTime $to): float
    {
        // Get count of abandonments in period
        $abandonmentCount = $this->em->getRepository(Abandonment::class)
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.store = :store')
            ->andWhere('a.createdAt >= :from')
            ->andWhere('a.createdAt <= :to')
            ->setParameter('store', $store)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        // Get total checkout sessions started in the same period
        $totalSessions = $this->em->getRepository(CheckoutStep::class)
            ->createQueryBuilder('cs')
            ->select('COUNT(DISTINCT cs.sessionId)')
            ->where('cs.store = :store')
            ->andWhere('cs.createdAt >= :from')
            ->andWhere('cs.createdAt <= :to')
            ->setParameter('store', $store)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        if ($totalSessions == 0) {
            return 0.0;
        }

        return (float) ($abandonmentCount / $totalSessions);
    }

    public function getAbandonmentsByStep(Store $store): array
    {
        return $this->em->getRepository(Abandonment::class)
            ->createQueryBuilder('a')
            ->where('a.store = :store')
            ->setParameter('store', $store)
            ->groupBy('a.abandonedAtStep')
            ->getQuery()
            ->getResult();
    }
}
