<?php

namespace App\Controller;

use App\Service\MetricsCollectionService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/metrics', name: 'app_metrics_')]
class MetricsController extends AbstractController
{
    public function __construct(
        private MetricsCollectionService $metricsService,
    ) {
    }

    /**
     * Get system metrics for a time range
     */
    #[Route('/system', name: 'system', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getSystemMetrics(Request $request): JsonResponse
    {
        try {
            $startTime = new DateTime($request->query->get('start', '-24 hours'));
            $endTime = new DateTime($request->query->get('end', 'now'));
            $metricName = $request->query->get('name');

            $metrics = $this->metricsService->getMetrics($startTime, $endTime, $metricName);

            return $this->json([
                'success' => true,
                'data' => $metrics,
                'count' => count($metrics),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get aggregated metric statistics
     */
    #[Route('/aggregate/{metricName}', name: 'aggregate', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getAggregates(string $metricName, Request $request): JsonResponse
    {
        try {
            $startTime = new DateTime($request->query->get('start', '-24 hours'));
            $endTime = new DateTime($request->query->get('end', 'now'));

            $aggregates = $this->metricsService->getMetricAggregates($metricName, $startTime, $endTime);

            return $this->json([
                'success' => true,
                'data' => $aggregates,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get system health overview
     */
    #[Route('/health', name: 'health', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getSystemHealth(): JsonResponse
    {
        try {
            $health = $this->metricsService->getSystemHealth();

            return $this->json([
                'success' => true,
                'data' => $health,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get monitoring performance metrics
     */
    #[Route('/monitoring', name: 'monitoring', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getMonitoringMetrics(Request $request): JsonResponse
    {
        try {
            $startTime = new DateTime($request->query->get('start', '-24 hours'));
            $endTime = new DateTime($request->query->get('end', 'now'));

            $metrics = $this->metricsService->getMonitoringMetrics($startTime, $endTime);

            return $this->json([
                'success' => true,
                'data' => $metrics,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get revenue metrics from Stripe
     */
    #[Route('/revenue', name: 'revenue', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getRevenueMetrics(Request $request): JsonResponse
    {
        try {
            $startTime = new DateTime($request->query->get('start', '-30 days'));
            $endTime = new DateTime($request->query->get('end', 'now'));

            $metrics = $this->metricsService->getRevenueMetrics($startTime, $endTime);

            return $this->json([
                'success' => true,
                'data' => $metrics,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get real-time metrics from Redis
     */
    #[Route('/realtime', name: 'realtime', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getRealtimeMetrics(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->query->get('limit', 100);
            $metrics = $this->metricsService->getRealtimeMetrics($limit);

            return $this->json([
                'success' => true,
                'data' => $metrics,
                'count' => count($metrics),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Clear old metrics
     */
    #[Route('/cleanup', name: 'cleanup', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function clearOldMetrics(Request $request): JsonResponse
    {
        try {
            $retentionDays = (int) $request->request->get('retention_days', 90);
            $deleted = $this->metricsService->clearOldMetrics($retentionDays);

            return $this->json([
                'success' => true,
                'message' => "Deleted $deleted old metrics",
                'deleted' => $deleted,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
