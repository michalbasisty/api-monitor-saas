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
        $salesMetricsRepo = $this->entityManager->getRepository(\App\Modules\Ecommerce\Entity\SalesMetric::class);

        // Parse interval (e.g., '1h', '24h', '7d')
        $hours = $this->parseIntervalToHours($interval);
        $since = new \DateTime("-{$hours} hours");

        $metrics = $salesMetricsRepo->findMetricsSince($store, $since);

        $data = array_map(function ($metric) {
            return [
                'timestamp' => $metric->getTimestamp()->format('c'),
                'revenue' => (float) ($metric->getRevenuePerMinute() ?? 0),
                'orders' => (float) ($metric->getOrdersPerMinute() ?? 0),
                'conversionRate' => (float) ($metric->getCheckoutSuccessRate() ?? 0),
                'avgOrderValue' => (float) ($metric->getAvgOrderValue() ?? 0),
            ];
        }, $metrics);

        return [
            'storeId' => $store->getId(),
            'interval' => $interval,
            'data' => $data,
        ];
    }

    private function parseIntervalToHours(string $interval): int
    {
        if (str_ends_with($interval, 'h')) {
            return (int) str_replace('h', '', $interval);
        } elseif (str_ends_with($interval, 'd')) {
            return (int) str_replace('d', '', $interval) * 24;
        }
        return 1; // Default to 1 hour
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
