<?php

namespace App\Modules\Ecommerce\Controller;

use App\Entity\User;
use App\Exception\StoreNotFoundException;
use App\Modules\Ecommerce\Entity\PaymentGateway;
use App\Modules\Ecommerce\Entity\PaymentMetric;
use App\Modules\Ecommerce\Entity\Store;
use App\Modules\Ecommerce\Repository\PaymentGatewayRepository;
use App\Modules\Ecommerce\Repository\PaymentMetricRepository;
use App\Modules\Ecommerce\Repository\StoreRepository;
use App\Modules\Ecommerce\Service\PaymentService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/ecommerce')]
#[IsGranted('ROLE_USER')]
class PaymentController extends AbstractController
{
    public function __construct(
        private PaymentService $paymentService,
        private StoreRepository $storeRepository,
        private PaymentGatewayRepository $gatewayRepository,
        private PaymentMetricRepository $metricRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Verify ownership of store and return it, or throw exception
     */
    private function getStoreForUser(string $storeId): Store
    {
        /** @var User $user */
        $user = $this->getUser();
        $store = $this->storeRepository->find($storeId);

        if (!$store || $store->getUser() !== $user) {
            throw new StoreNotFoundException($storeId);
        }

        return $store;
    }

    /**
     * List all payment gateways for a store
     */
    #[Route('/stores/{storeId}/payment-gateways', name: 'ecommerce_payment_gateways', methods: ['GET'])]
    public function listGateways(string $storeId): JsonResponse
    {
        try {
            $store = $this->getStoreForUser($storeId);
            $gateways = $this->paymentService->getPaymentGateways($store);

            return $this->json([
                'data' => array_map(fn(PaymentGateway $g) => $this->serializeGateway($g), $gateways),
                'meta' => [
                    'pagination' => [
                        'total' => count($gateways),
                        'count' => count($gateways),
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
     * Add a payment gateway to a store
     */
    #[Route('/stores/{storeId}/payment-gateways', name: 'ecommerce_payment_add_gateway', methods: ['POST'])]
    public function addGateway(string $storeId, Request $request): JsonResponse
    {
        try {
            $store = $this->getStoreForUser($storeId);
            $data = json_decode($request->getContent(), true) ?? [];

            // Validate required fields
            if (!isset($data['gateway_name']) || !isset($data['api_key'])) {
                return $this->json([
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Missing required fields: gateway_name, api_key',
                        'details' => [
                            'gateway_name' => !isset($data['gateway_name']) ? ['This field is required'] : [],
                            'api_key' => !isset($data['api_key']) ? ['This field is required'] : [],
                        ]
                    ]
                ], 400);
            }

            $gateway = $this->paymentService->addPaymentGateway($store, $data);

            $this->logger->info('Payment gateway added', [
                'store_id' => $store->getId(),
                'gateway_id' => $gateway->getId(),
                'gateway_name' => $gateway->getGatewayName(),
            ]);

            return $this->json(
                ['data' => $this->serializeGateway($gateway)],
                201
            );
        } catch (StoreNotFoundException $e) {
            return $this->json([
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ]
            ], 404);
        } catch (\Exception $e) {
            $this->logger->error('Failed to add payment gateway', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->json([
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'Failed to add payment gateway',
                ]
            ], 500);
        }
    }

    /**
     * Get payment metrics for a store
     */
    #[Route('/stores/{storeId}/payment-metrics', name: 'ecommerce_payment_metrics', methods: ['GET'])]
    public function metrics(string $storeId): JsonResponse
    {
        try {
            $store = $this->getStoreForUser($storeId);
            $metrics = $this->metricRepository->findBy(
                ['store' => $store],
                ['createdAt' => 'DESC'],
                1000
            );

            $metricsData = $this->calculatePaymentMetrics($metrics);

            $this->logger->info('Payment metrics retrieved', [
                'store_id' => $store->getId(),
                'total_transactions' => $metricsData['total_transactions'],
                'success_rate' => $metricsData['authorization_success_rate'],
            ]);

            return $this->json([
                'data' => [
                    'store_id' => (string) $store->getId(),
                    'authorization_success_rate' => $metricsData['authorization_success_rate'],
                    'declined_rate' => $metricsData['declined_rate'],
                    'total_transactions' => $metricsData['total_transactions'],
                    'authorized_transactions' => $metricsData['authorized_transactions'],
                    'declined_transactions' => $metricsData['declined_transactions'],
                ],
                'meta' => [
                    'calculated_at' => (new \DateTime())->format('c'),
                ]
            ]);
        } catch (StoreNotFoundException $e) {
            return $this->json([
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ]
            ], 404);
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve payment metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->json([
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'Failed to retrieve payment metrics',
                ]
            ], 500);
        }
    }

    /**
     * Calculate payment metrics from PaymentMetric entities
     */
    private function calculatePaymentMetrics(array $metrics): array
    {
        $authorized = 0;
        $declined = 0;
        $total = count($metrics);

        foreach ($metrics as $metric) {
            if ($metric->getStatus() === 'authorized') {
                $authorized++;
            } elseif ($metric->getStatus() === 'declined') {
                $declined++;
            }
        }

        $successRate = $total > 0 ? ($authorized / $total) * 100 : 0;
        $declineRate = $total > 0 ? ($declined / $total) * 100 : 0;

        return [
            'total_transactions' => $total,
            'authorized_transactions' => $authorized,
            'declined_transactions' => $declined,
            'authorization_success_rate' => round($successRate, 2),
            'declined_rate' => round($declineRate, 2),
        ];
    }

    /**
     * Serialize a payment gateway for API responses
     */
    private function serializeGateway(PaymentGateway $gateway): array
    {
        return [
            'id' => (string) $gateway->getId(),
            'gateway_name' => $gateway->getGatewayName(),
            'is_primary' => $gateway->isPrimary(),
            'enabled' => $gateway->isEnabled(),
            'created_at' => $gateway->getCreatedAt()?->format('c'),
            'updated_at' => $gateway->getUpdatedAt()?->format('c'),
        ];
    }
}
