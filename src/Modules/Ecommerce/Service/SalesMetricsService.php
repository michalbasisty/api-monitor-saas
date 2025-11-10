<?php

namespace App\Modules\Ecommerce\Service;

use App\Modules\Ecommerce\Entity\Store;
use App\Modules\Ecommerce\Repository\SalesMetricRepository;
use Doctrine\ORM\EntityManagerInterface;

class SalesMetricsService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function getRealtimeSalesMetrics(Store $store, string $interval = '1h'): array
    {
        // TODO: Query real sales metrics from database based on interval
        return [
            'storeId' => $store->getId(),
            'interval' => $interval,
            'data' => [
                [
                    'timestamp' => (new \DateTime())->format('c'),
                    'revenue' => 0,
                    'orders' => 0,
                    'conversionRate' => 0.0,
                    'avgOrderValue' => 0.0,
                ],
            ],
        ];
    }

    public function calculateLostRevenue(Store $store, string $timeframe = '24h'): array
    {
        // Calculate lost revenue based on abandonment and payment failures
        $checkoutSteps = $store->getCheckoutSteps();
        $paymentGateways = $store->getPaymentGateways();

        $totalAbandonmentRate = $checkoutSteps->count() > 0
            ? array_sum(array_map(fn($s) => $s->getAbandonmentRate(), $checkoutSteps->toArray())) / $checkoutSteps->count()
            : 0;

        $totalPaymentFailureRate = $paymentGateways->count() > 0
            ? array_sum(array_map(fn($g) => $g->getFailureRate(), $paymentGateways->toArray())) / $paymentGateways->count()
            : 0;

        return [
            'storeId' => $store->getId(),
            'timeframe' => $timeframe,
            'lostRevenueTotal' => 0,
            'estimatedLostOrders' => 0,
            'sources' => [
                'checkout_abandonment' => [
                    'rate' => round($totalAbandonmentRate, 2),
                    'lostRevenue' => 0,
                ],
                'payment_failures' => [
                    'rate' => round($totalPaymentFailureRate, 2),
                    'lostRevenue' => 0,
                ],
            ],
        ];
    }
}
