<?php

namespace App\Modules\Ecommerce\Controller;

use App\Modules\Ecommerce\Repository\StoreRepository;
use App\Modules\Ecommerce\Service\SalesMetricsService;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

class SalesController extends AbstractController
{
    public function __construct(
        private StoreRepository $storeRepository,
        private SalesMetricsService $salesMetricsService,
        private LoggerInterface $logger
    ) {}

    public function realtime(string $storeId, Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }

            $store = $this->storeRepository->find($storeId);
            if (!$store || $store->getUser() !== $user) {
                return new JsonResponse(['error' => 'Store not found'], Response::HTTP_NOT_FOUND);
            }

            $interval = $request->query->get('interval', '1h');
            $metrics = $this->salesMetricsService->getRealtimeSalesMetrics($store, $interval);

            return new JsonResponse($metrics);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get realtime sales metrics', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to get realtime sales metrics'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function lostRevenue(string $storeId, Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }

            $store = $this->storeRepository->find($storeId);
            if (!$store || $store->getUser() !== $user) {
                return new JsonResponse(['error' => 'Store not found'], Response::HTTP_NOT_FOUND);
            }

            $timeframe = $request->query->get('timeframe', '24h');
            $lostRevenue = $this->salesMetricsService->calculateLostRevenue($store, $timeframe);

            return new JsonResponse($lostRevenue);
        } catch (\Exception $e) {
            $this->logger->error('Failed to calculate lost revenue', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to calculate lost revenue'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
