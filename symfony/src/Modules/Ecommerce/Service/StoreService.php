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
}
