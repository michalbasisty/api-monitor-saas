<?php

namespace App\Modules\Ecommerce\Controller;

use App\Entity\User;
use App\Exception\StoreNotFoundException;
use App\Modules\Ecommerce\Entity\SalesMetric;
use App\Modules\Ecommerce\Entity\Store;
use App\Modules\Ecommerce\Service\SalesMetricsService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/ecommerce')]
#[IsGranted('ROLE_USER')]
class SalesController extends AbstractController
{
    public function __construct(
        private SalesMetricsService $salesMetricsService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get real-time sales metrics
     */
    #[Route('/stores/{storeId}/sales/realtime', name: 'ecommerce_sales_realtime', methods: ['GET'])]
    public function realtime(string $storeId): JsonResponse
    {
        try {
            $store = $this->getStoreForUser($storeId);
            $latestMetric = $this->salesMetricsService->getLatestMetric($store);

            if (!$latestMetric) {
                return $this->json([
                    'data' => [
                        'status' => 'no_data',
                        'message' => 'No sales data available yet',
                    ]
                ], 200);
            }

            return $this->json([
                'data' => $this->serializeMetric($latestMetric),
            ]);
        } catch (StoreNotFoundException $e) {
            return $this->json([
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ]
            ], 404);
        }
    }

    /**
     * Calculate lost revenue for a period
     */
    #[Route('/stores/{storeId}/sales/lost-revenue', name: 'ecommerce_sales_lost_revenue', methods: ['GET'])]
    public function lostRevenue(string $storeId, Request $request): JsonResponse
    {
        try {
            $store = $this->getStoreForUser($storeId);

            // Get and validate query parameters
            $from = $request->query->get('from');
            $to = $request->query->get('to');

            if (!$from || !$to) {
                return $this->json([
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Missing required parameters',
                        'details' => [
                            'from' => ['from parameter is required'],
                            'to' => ['to parameter is required'],
                        ]
                    ]
                ], 400);
            }

            try {
                $fromDate = new \DateTime($from);
                $toDate = new \DateTime($to);
            } catch (\Exception) {
                return $this->json([
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Invalid date format. Use ISO 8601 format',
                    ]
                ], 400);
            }

            $totalLostRevenue = $this->salesMetricsService->calculateLostRevenue($store, $fromDate, $toDate);

            $this->logger->info('Lost revenue calculated', [
                'store_id' => $store->getId(),
                'from' => $fromDate->format('c'),
                'to' => $toDate->format('c'),
                'lost_revenue' => $totalLostRevenue,
            ]);

            return $this->json([
                'data' => [
                    'store_id' => (string) $store->getId(),
                    'period' => [
                        'from' => $fromDate->format('c'),
                        'to' => $toDate->format('c'),
                    ],
                    'estimated_lost_revenue' => (float) $totalLostRevenue,
                    'currency' => $store->getCurrency(),
                ]
            ]);
        } catch (StoreNotFoundException $e) {
            return $this->json([
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ]
            ], 404);
        }
    }

    /**
     * Get sales trends for a period
     */
    #[Route('/stores/{storeId}/sales/trends', name: 'ecommerce_sales_trends', methods: ['GET'])]
    public function trends(string $storeId, Request $request): JsonResponse
    {
        try {
            $store = $this->getStoreForUser($storeId);

            $days = (int) $request->query->get('days', 7);
            $from = new \DateTime("-{$days} days");
            $to = new \DateTime();

            $metrics = $this->salesMetricsService->getMetricsForPeriod($store, $from, $to);

            return $this->json([
                'data' => [
                    'store_id' => (string) $store->getId(),
                    'period_days' => $days,
                    'data_points' => count($metrics),
                    'metrics' => array_map(fn(SalesMetric $m) => $this->serializeMetric($m), $metrics),
                ],
                'meta' => [
                    'pagination' => [
                        'total' => count($metrics),
                        'count' => count($metrics),
                    ]
                ]
            ]);
        } catch (StoreNotFoundException $e) {
            return $this->json([
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ]
            ], 404);
        }
    }

    /**
     * Get store for the current user
     */
    private function getStoreForUser(string $storeId): Store
    {
        /** @var User $user */
        $user = $this->getUser();

        $store = $this->salesMetricsService->getStoreById($storeId);
        if ($store->getUser() !== $user) {
            throw new StoreNotFoundException($storeId);
        }

        return $store;
    }

    /**
     * Serialize sales metric for API response
     */
    private function serializeMetric(SalesMetric $metric): array
    {
        return [
            'status' => $metric->getStatus(),
            'revenue_per_minute' => (float) ($metric->getRevenuePerMinute() ?? 0),
            'orders_per_minute' => $metric->getOrdersPerMinute() ?? 0,
            'checkout_success_rate' => (float) ($metric->getCheckoutSuccessRate() ?? 0),
            'avg_order_value' => (float) ($metric->getAvgOrderValue() ?? 0),
            'timestamp' => $metric->getTimestamp()?->format('c'),
        ];
    }
}
