<?php

namespace App\Modules\Ecommerce\Service;

use App\Modules\Ecommerce\Entity\Store;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class StoreService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function createStore(
        User $user,
        string $storeName,
        string $storeUrl,
        string $platform,
        string $currency = 'USD',
        ?string $timezone = null
    ): Store {
        $store = new Store();
        $store->setUser($user);
        $store->setStoreName($storeName);
        $store->setStoreUrl($storeUrl);
        $store->setPlatform($platform);
        $store->setCurrency($currency);
        if ($timezone) {
            $store->setTimezone($timezone);
        }

        $this->entityManager->persist($store);
        $this->entityManager->flush();

        return $store;
    }

    public function getStoreHealth(Store $store): array
    {
        $checkoutSteps = $store->getCheckoutSteps()->count();
        $paymentGateways = $store->getPaymentGateways()->count();
        $activeGateways = $store->getPaymentGateways()->filter(fn($g) => $g->isActive())->count();

        return [
            'storeId' => $store->getId(),
            'storeName' => $store->getStoreName(),
            'status' => $checkoutSteps > 0 && $activeGateways > 0 ? 'healthy' : 'warning',
            'checkoutStepsConfigured' => $checkoutSteps,
            'paymentGatewaysTotal' => $paymentGateways,
            'paymentGatewaysActive' => $activeGateways,
            'metrics' => [
                'avgConversionRate' => 0.0,
                'avgCheckoutTime' => 0,
                'lastUpdate' => (new \DateTime())->format('c'),
            ],
        ];
    }
}
