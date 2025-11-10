<?php

namespace App\Modules\Ecommerce\Service;

use App\Modules\Ecommerce\Entity\Store;
use App\Modules\Ecommerce\Entity\PaymentGateway;
use App\Modules\Ecommerce\Repository\PaymentGatewayRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class PaymentService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function addPaymentGateway(Store $store, string $provider, array $config): PaymentGateway
    {
        $gateway = new PaymentGateway();
        $gateway->setId(Uuid::v4());
        $gateway->setStore($store);
        $gateway->setProvider($provider);
        $gateway->setConfig($config);
        $gateway->setActive(true);
        $gateway->setSuccessRate(0.0);
        $gateway->setFailureRate(0.0);
        $gateway->setDeclineRate(0.0);
        $gateway->setAvgProcessingTimeMs(0);
        $gateway->setCreatedAt(new \DateTime());

        $this->entityManager->persist($gateway);
        $this->entityManager->flush();

        return $gateway;
    }

    public function getPaymentMetrics(Store $store, string $timeframe = '24h'): array
    {
        $gateways = $store->getPaymentGateways();

        $totalGateways = $gateways->count();
        $activeGateways = $gateways->filter(fn(PaymentGateway $g) => $g->isActive())->count();

        $avgSuccessRate = $totalGateways > 0
            ? array_sum(array_map(fn(PaymentGateway $g) => $g->getSuccessRate(), $gateways->toArray())) / $totalGateways
            : 0;

        $avgFailureRate = $totalGateways > 0
            ? array_sum(array_map(fn(PaymentGateway $g) => $g->getFailureRate(), $gateways->toArray())) / $totalGateways
            : 0;

        return [
            'storeId' => $store->getId(),
            'timeframe' => $timeframe,
            'totalGateways' => $totalGateways,
            'activeGateways' => $activeGateways,
            'avgSuccessRate' => round($avgSuccessRate, 2),
            'avgFailureRate' => round($avgFailureRate, 2),
            'totalTransactions' => 0,
            'successfulTransactions' => 0,
            'failedTransactions' => 0,
            'gateways' => array_map(fn(PaymentGateway $g) => [
                'id' => $g->getId(),
                'provider' => $g->getProvider(),
                'isActive' => $g->isActive(),
                'successRate' => $g->getSuccessRate(),
                'failureRate' => $g->getFailureRate(),
                'declineRate' => $g->getDeclineRate(),
                'avgProcessingTimeMs' => $g->getAvgProcessingTimeMs(),
            ], $gateways->toArray()),
            'timestamp' => (new \DateTime())->format('c'),
        ];
    }
}
