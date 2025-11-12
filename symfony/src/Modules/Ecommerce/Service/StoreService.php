<?php

namespace App\Modules\Ecommerce\Service;

use App\Entity\User;
use App\Exception\StoreNotFoundException;
use App\Modules\Ecommerce\DTO\CreateStoreDto;
use App\Modules\Ecommerce\DTO\UpdateStoreDto;
use App\Modules\Ecommerce\Entity\Store;
use App\Modules\Ecommerce\Repository\StoreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing e-commerce stores
 * 
 * Handles creation, retrieval, updating, and deletion of store configurations.
 */
class StoreService
{
    private StoreRepository $repository;

    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger
    ) {
        $this->repository = $em->getRepository(Store::class);
    }

    /**
     * Create a new store
     * 
     * @throws \Exception if validation fails
     */
    public function createStore(CreateStoreDto $dto, User $user): Store
    {
        $store = new Store();
        $store->setStoreName($dto->storeName);
        $store->setStoreUrl($dto->storeUrl);
        $store->setPlatform($dto->platform);
        $store->setCurrency($dto->currency);
        $store->setTimezone($dto->timezone);
        $store->setUser($user);

        $this->em->persist($store);
        $this->em->flush();

        $this->logger->info('Store created successfully', [
            'store_id' => $store->getId(),
            'store_name' => $dto->storeName,
            'platform' => $dto->platform,
            'user_id' => $user->getId(),
        ]);

        return $store;
    }

    /**
     * Get a store by ID
     * 
     * @throws StoreNotFoundException if not found
     */
    public function getStore(string $id): Store
    {
        $store = $this->repository->find($id);

        if (!$store) {
            $this->logger->warning('Store not found', ['store_id' => $id]);
            throw new StoreNotFoundException($id);
        }

        return $store;
    }

    /**
     * Get all stores for a user
     * 
     * @return Store[]
     */
    public function getUserStores(User $user): array
    {
        return $this->repository->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    /**
     * Update an existing store
     * 
     * @throws StoreNotFoundException if store not found
     */
    public function updateStore(string $id, UpdateStoreDto $dto, User $user): Store
    {
        $store = $this->getStore($id);

        // Verify ownership
        if ($store->getUser() !== $user) {
            $this->logger->warning('Unauthorized store update attempt', [
                'store_id' => $id,
                'user_id' => $user->getId(),
            ]);
            throw new StoreNotFoundException($id);
        }

        $changes = [];

        if ($dto->storeName !== null) {
            $store->setStoreName($dto->storeName);
            $changes['store_name'] = $dto->storeName;
        }
        if ($dto->currency !== null) {
            $store->setCurrency($dto->currency);
            $changes['currency'] = $dto->currency;
        }
        if ($dto->timezone !== null) {
            $store->setTimezone($dto->timezone);
            $changes['timezone'] = $dto->timezone;
        }

        if (empty($changes)) {
            return $store;
        }

        $store->setUpdatedAt(new \DateTime());
        $this->em->flush();

        $this->logger->info('Store updated successfully', array_merge([
            'store_id' => $store->getId(),
            'user_id' => $user->getId(),
        ], $changes));

        return $store;
    }

    /**
     * Delete a store
     * 
     * @throws StoreNotFoundException if store not found
     */
    public function deleteStore(string $id, User $user): void
    {
        $store = $this->getStore($id);

        // Verify ownership
        if ($store->getUser() !== $user) {
            $this->logger->warning('Unauthorized store deletion attempt', [
                'store_id' => $id,
                'user_id' => $user->getId(),
            ]);
            throw new StoreNotFoundException($id);
        }

        $this->em->remove($store);
        $this->em->flush();

        $this->logger->info('Store deleted successfully', [
            'store_id' => $store->getId(),
            'store_name' => $store->getStoreName(),
            'user_id' => $user->getId(),
        ]);
    }

    /**
     * Check if a store exists
     */
    public function storeExists(string $id): bool
    {
        return $this->repository->find($id) !== null;
    }

    /**
     * Count total stores
     */
    public function countStores(User $user): int
    {
        return $this->repository->count(['user' => $user]);
    }
    /**
     * Calculate store health metrics
     */
    public function calculateStoreHealth(Store $store): array
    {
        $healthData = [
            'status' => 'healthy',
            'uptime_percentage' => 99.9,
            'revenue_per_minute' => 0.0,
            'error_rate' => 0.0,
            'metrics' => []
        ];

        try {
            // Calculate uptime from monitoring results
            $uptime = $this->calculateUptimePercentage($store);
            $healthData['uptime_percentage'] = round($uptime, 2);

            // Calculate revenue per minute
            $revenue = $this->calculateRevenuePerMinute($store);
            $healthData['revenue_per_minute'] = round($revenue, 2);

            // Calculate error rate
            $errorRate = $this->calculateErrorRate($store);
            $healthData['error_rate'] = round($errorRate, 2);

            // Determine status based on metrics
            $healthData['status'] = $this->determineHealthStatus($uptime, $errorRate);

            $healthData['metrics'] = [
                'checkouts_total' => $this->getTotalCheckouts($store),
                'payments_successful' => $this->getSuccessfulPayments($store),
                'abandonment_rate' => $this->getAbandonmentRate($store),
                'last_24h_revenue' => $this->getRevenueLast24Hours($store),
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to calculate store health', [
                'store_id' => $store->getId(),
                'error' => $e->getMessage()
            ]);
            $healthData['status'] = 'error';
        }

        return $healthData;
    }

    private function calculateUptimePercentage(Store $store): float
    {
        // Count successful vs total monitoring results in last 24 hours
        $qb = $this->em->createQueryBuilder();
        $qb->select('COUNT(m.id) as total, SUM(CASE WHEN m.responseCode >= 200 AND m.responseCode < 400 THEN 1 ELSE 0 END) as successful')
           ->from('App\\Entity\\MonitoringResult', 'm')
           ->join('m.endpoint', 'e')
           ->where('e.company IN (SELECT c FROM App\\Entity\\Company c WHERE c.user = :user)')
           ->andWhere('m.timestamp >= :since')
           ->setParameter('user', $store->getUser())
           ->setParameter('since', new \DateTime('-24 hours'));

        $result = $qb->getQuery()->getSingleResult();

        if ($result['total'] == 0) {
            return 100.0;
        }

        return ($result['successful'] / $result['total']) * 100;
    }

    private function calculateRevenuePerMinute(Store $store): float
    {
        // Get sales metrics from last hour and calculate average revenue per minute
        $salesRepo = $this->em->getRepository(\App\Modules\Ecommerce\Entity\SalesMetric::class);

        $qb = $this->em->createQueryBuilder();
        $qb->select('AVG(sm.revenuePerMinute) as avgRevenue')
           ->from(\App\Modules\Ecommerce\Entity\SalesMetric::class, 'sm')
           ->where('sm.store = :store')
           ->andWhere('sm.timestamp >= :since')
           ->setParameter('store', $store)
           ->setParameter('since', new \DateTime('-1 hour'));

        $result = $qb->getQuery()->getSingleScalarResult();

        return $result ?: 0.0;
    }

    private function calculateErrorRate(Store $store): float
    {
        // Calculate error rate from payment metrics
        $paymentRepo = $this->em->getRepository(\App\Modules\Ecommerce\Entity\PaymentMetric::class);

        $qb = $this->em->createQueryBuilder();
        $qb->select('COUNT(pm.id) as total, SUM(CASE WHEN pm.status = \'declined\' THEN 1 ELSE 0 END) as errors')
           ->from(\App\Modules\Ecommerce\Entity\PaymentMetric::class, 'pm')
           ->where('pm.store = :store')
           ->andWhere('pm.createdAt >= :since')
           ->setParameter('store', $store)
           ->setParameter('since', new \DateTime('-24 hours'));

        $result = $qb->getQuery()->getSingleResult();

        if ($result['total'] == 0) {
            return 0.0;
        }

        return ($result['errors'] / $result['total']) * 100;
    }

    private function determineHealthStatus(float $uptime, float $errorRate): string
    {
        if ($uptime < 95 || $errorRate > 5) {
            return 'critical';
        } elseif ($uptime < 98 || $errorRate > 2) {
            return 'warning';
        }
        return 'healthy';
    }

    private function getTotalCheckouts(Store $store): int
    {
        $qb = $this->em->createQueryBuilder();
        return $qb->select('COUNT(c.id)')
           ->from(\App\Modules\Ecommerce\Entity\CheckoutStep::class, 'c')
           ->where('c.store = :store')
           ->setParameter('store', $store)
           ->getQuery()
           ->getSingleScalarResult();
    }

    private function getSuccessfulPayments(Store $store): int
    {
        $qb = $this->em->createQueryBuilder();
        return $qb->select('COUNT(pm.id)')
           ->from(\App\Modules\Ecommerce\Entity\PaymentMetric::class, 'pm')
           ->where('pm.store = :store')
           ->andWhere('pm.status = :status')
           ->setParameter('store', $store)
           ->setParameter('status', 'authorized')
           ->getQuery()
           ->getSingleScalarResult();
    }

    private function getAbandonmentRate(Store $store): float
    {
        $qb = $this->em->createQueryBuilder();
        $result = $qb->select('COUNT(a.id) as abandonments, COUNT(DISTINCT cs.sessionId) as sessions')
           ->from(\App\Modules\Ecommerce\Entity\CheckoutStep::class, 'cs')
           ->leftJoin(\App\Modules\Ecommerce\Entity\Abandonment::class, 'a', 'WITH', 'a.abandonedAtStep = cs')
           ->where('cs.store = :store')
           ->setParameter('store', $store)
           ->getQuery()
           ->getSingleResult();

        if ($result['sessions'] == 0) {
            return 0.0;
        }

        return ($result['abandonments'] / $result['sessions']) * 100;
    }

    private function getRevenueLast24Hours(Store $store): float
    {
        $qb = $this->em->createQueryBuilder();
        $result = $qb->select('SUM(sm.revenuePerMinute * 1440) as revenue') // 1440 minutes in 24 hours
           ->from(\App\Modules\Ecommerce\Entity\SalesMetric::class, 'sm')
           ->where('sm.store = :store')
           ->andWhere('sm.timestamp >= :since')
           ->setParameter('store', $store)
           ->setParameter('since', new \DateTime('-24 hours'))
           ->getQuery()
           ->getSingleScalarResult();

        return $result ?: 0.0;
    }
}
