<?php

namespace App\Modules\Ecommerce\Service;

use App\Modules\Ecommerce\Entity\Store;
use Doctrine\ORM\EntityManagerInterface;

class StoreService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function createStore(array $data): Store
    {
        $store = new Store();
        $store->setStoreName($data['store_name']);
        $store->setStoreUrl($data['store_url']);
        $store->setPlatform($data['platform']);
        $store->setCurrency($data['currency'] ?? 'USD');
        $store->setTimezone($data['timezone'] ?? null);
        $store->setUser($data['user']);

        $this->em->persist($store);
        $this->em->flush();

        return $store;
    }

    public function getStore(string $id): ?Store
    {
        return $this->em->getRepository(Store::class)->find($id);
    }

    public function updateStore(Store $store, array $data): Store
    {
        if (isset($data['store_name'])) {
            $store->setStoreName($data['store_name']);
        }
        if (isset($data['currency'])) {
            $store->setCurrency($data['currency']);
        }
        if (isset($data['timezone'])) {
            $store->setTimezone($data['timezone']);
        }

        $store->setUpdatedAt(new \DateTime());
        $this->em->flush();

        return $store;
    }

    public function deleteStore(Store $store): void
    {
        $this->em->remove($store);
        $this->em->flush();
    }
}
