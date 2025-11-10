<?php

namespace App\Modules\Ecommerce\Controller;

use App\Entity\User;
use App\Modules\Ecommerce\Entity\SalesMetric;
use App\Modules\Ecommerce\Entity\Store;
use App\Modules\Ecommerce\Service\SalesMetricsService;
use Doctrine\ORM\EntityManagerInterface;
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
        private EntityManagerInterface $em
    ) {
    }

    private function getStoreForUser(string $storeId): ?Store
    {
        /** @var User $user */
        $user = $this->getUser();
        $store = $this->em->getRepository(Store::class)->find($storeId);

        if (!$store || $store->getUser() !== $user) {
            return null;
        }

        return $store;
    }

    #[Route('/stores/{storeId}/sales/realtime', name: 'ecommerce_sales_realtime', methods: ['GET'])]
    public function realtime(string $storeId): JsonResponse
    {
        $store = $this->getStoreForUser($storeId);
        if (!$store) {
            return $this->json(['error' => 'Store not found'], 404);
        }

        $latestMetric = $this->salesMetricsService->getLatestMetric($store);

        if (!$latestMetric) {
            return $this->json([
                'status' => 'no_data',
                'message' => 'No sales data available yet',
            ]);
        }

        return $this->json([
            'status' => $latestMetric->getStatus(),
            'revenue_per_minute' => (float) $latestMetric->getRevenuePerMinute(),
            'orders_per_minute' => $latestMetric->getOrdersPerMinute(),
            'checkout_success_rate' => (float) $latestMetric->getCheckoutSuccessRate(),
            'avg_order_value' => (float) $latestMetric->getAvgOrderValue(),
            'timestamp' => $latestMetric->getTimestamp()?->format('c'),
        ]);
    }

    #[Route('/stores/{storeId}/sales/lost-revenue', name: 'ecommerce_sales_lost_revenue', methods: ['GET'])]
    public function lostRevenue(string $storeId, Request $request): JsonResponse
    {
        $store = $this->getStoreForUser($storeId);
        if (!$store) {
            return $this->json(['error' => 'Store not found'], 404);
        }

        $from = $request->query->get('from');
        $to = $request->query->get('to');

        if (!$from || !$to) {
            return $this->json(['error' => 'Missing from/to parameters'], 400);
        }

        try {
            $fromDate = new \DateTime($from);
            $toDate = new \DateTime($to);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid date format'], 400);
        }

        $totalLostRevenue = $this->salesMetricsService->calculateLostRevenue($store, $fromDate, $toDate);

        return $this->json([
            'store_id' => (string) $store->getId(),
            'period' => [
                'from' => $fromDate->format('c'),
                'to' => $toDate->format('c'),
            ],
            'estimated_lost_revenue' => (float) $totalLostRevenue,
            'currency' => $store->getCurrency(),
        ]);
    }

    #[Route('/stores/{storeId}/sales/trends', name: 'ecommerce_sales_trends', methods: ['GET'])]
    public function trends(string $storeId, Request $request): JsonResponse
    {
        $store = $this->getStoreForUser($storeId);
        if (!$store) {
            return $this->json(['error' => 'Store not found'], 404);
        }

        $days = (int) $request->query->get('days', 7);
        $from = new \DateTime("-{$days} days");
        $to = new \DateTime();

        $metrics = $this->salesMetricsService->getMetricsForPeriod($store, $from, $to);

        return $this->json([
            'store_id' => (string) $store->getId(),
            'period_days' => $days,
            'data_points' => count($metrics),
            'metrics' => array_map(fn(SalesMetric $m) => [
                'timestamp' => $m->getTimestamp()?->format('c'),
                'revenue_per_minute' => (float) ($m->getRevenuePerMinute() ?? 0),
                'orders_per_minute' => $m->getOrdersPerMinute() ?? 0,
                'checkout_success_rate' => (float) ($m->getCheckoutSuccessRate() ?? 0),
                'status' => $m->getStatus(),
            ], $metrics),
        ]);
    }
}
