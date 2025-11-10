<?php

namespace App\Modules\Ecommerce\Controller;

use App\Entity\User;
use App\Exception\StoreNotFoundException;
use App\Modules\Ecommerce\Entity\CheckoutMetric;
use App\Modules\Ecommerce\Entity\CheckoutStep;
use App\Modules\Ecommerce\Entity\Store;
use App\Modules\Ecommerce\Service\CheckoutService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/ecommerce')]
#[IsGranted('ROLE_USER')]
class CheckoutController extends AbstractController
{
    public function __construct(
        private CheckoutService $checkoutService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * List checkout steps for a store
     */
    #[Route('/stores/{storeId}/checkout-steps', name: 'ecommerce_checkout_steps', methods: ['GET'])]
    public function listSteps(string $storeId): JsonResponse
    {
        try {
            $store = $this->getStoreForUser($storeId);
            $steps = $this->checkoutService->getCheckoutSteps($store);

            return $this->json([
                'data' => array_map(fn(CheckoutStep $s) => $this->serializeStep($s), $steps),
                'meta' => [
                    'pagination' => [
                        'total' => count($steps),
                        'count' => count($steps),
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
     * Add a checkout step
     */
    #[Route('/stores/{storeId}/checkout-steps', name: 'ecommerce_checkout_add_step', methods: ['POST'])]
    public function addStep(string $storeId, Request $request): JsonResponse
    {
        try {
            $store = $this->getStoreForUser($storeId);
            $payload = json_decode($request->getContent(), true) ?? [];

            // Validate required fields
            $required = ['step_name', 'endpoint_url', 'step_number'];
            $missing = array_filter($required, fn($field) => !isset($payload[$field]));

            if (!empty($missing)) {
                return $this->json([
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Missing required fields',
                        'details' => array_fill_keys($missing, ['This field is required']),
                    ]
                ], 400);
            }

            $step = $this->checkoutService->addCheckoutStep($store, $payload);

            $this->logger->info('Checkout step added', [
                'store_id' => $store->getId(),
                'step_id' => $step->getId(),
                'step_number' => $step->getStepNumber(),
            ]);

            return $this->json(['data' => $this->serializeStep($step)], 201);
        } catch (StoreNotFoundException $e) {
            return $this->json([
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ]
            ], 404);
        } catch (\Exception $e) {
            $this->logger->error('Failed to add checkout step', [
                'store_id' => $storeId,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'Failed to add checkout step',
                ]
            ], 500);
        }
    }

    /**
     * Get real-time checkout metrics
     */
    #[Route('/stores/{storeId}/checkout/realtime', name: 'ecommerce_checkout_realtime', methods: ['GET'])]
    public function realtime(string $storeId): JsonResponse
    {
        try {
            $store = $this->getStoreForUser($storeId);
            $steps = $this->checkoutService->getCheckoutSteps($store);
            
            // Get real-time metrics from last 1 minute
            $metrics = $this->checkoutService->getRealtimeMetrics($store);

            return $this->json([
                'data' => [
                    'store_id' => (string) $store->getId(),
                    'steps_count' => count($steps),
                    'metrics_count' => count($metrics),
                    'metrics' => array_map(fn(CheckoutMetric $m) => $this->serializeMetric($m), $metrics),
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
     * Get aggregated checkout performance metrics
     */
    #[Route('/stores/{storeId}/checkout/performance', name: 'ecommerce_checkout_performance', methods: ['GET'])]
    public function performance(string $storeId): JsonResponse
    {
        try {
            $store = $this->getStoreForUser($storeId);
            $steps = $this->checkoutService->getCheckoutSteps($store);

            return $this->json([
                'data' => [
                    'store_id' => (string) $store->getId(),
                    'steps' => array_map(fn(CheckoutStep $s) => [
                        'id' => (string) $s->getId(),
                        'name' => $s->getStepName(),
                        'avg_load_time_ms' => 0, // TODO: Calculate
                        'error_rate_percentage' => 0, // TODO: Calculate
                        'completion_rate_percentage' => 0, // TODO: Calculate
                    ], $steps),
                    'overall_completion_rate' => 0, // TODO: Calculate
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

        $store = $this->checkoutService->getStoreById($storeId);
        if ($store->getUser() !== $user) {
            throw new StoreNotFoundException($storeId);
        }

        return $store;
    }

    /**
     * Serialize checkout step for API response
     */
    private function serializeStep(CheckoutStep $step): array
    {
        return [
            'id' => (string) $step->getId(),
            'step_number' => $step->getStepNumber(),
            'step_name' => $step->getStepName(),
            'endpoint_url' => $step->getEndpointUrl(),
            'expected_load_time_ms' => $step->getExpectedLoadTimeMs(),
            'alert_threshold_ms' => $step->getAlertThresholdMs(),
            'enabled' => $step->isEnabled(),
        ];
    }

    /**
     * Serialize checkout metric for API response
     */
    private function serializeMetric(CheckoutMetric $metric): array
    {
        return [
            'step_id' => (string) $metric->getStep()?->getId(),
            'load_time_ms' => $metric->getLoadTimeMs(),
            'error_occurred' => $metric->isErrorOccurred(),
            'http_status_code' => $metric->getHttpStatusCode(),
            'timestamp' => $metric->getTimestamp()?->format('c'),
        ];
    }
}
