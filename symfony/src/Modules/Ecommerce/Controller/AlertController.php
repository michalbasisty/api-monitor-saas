<?php

namespace App\Modules\Ecommerce\Controller;

use App\Entity\User;
use App\Exception\StoreNotFoundException;
use App\Exception\ValidationException;
use App\Modules\Ecommerce\Entity\EcommerceAlert;
use App\Modules\Ecommerce\Entity\Store;
use App\Modules\Ecommerce\Service\AlertingService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/ecommerce')]
#[IsGranted('ROLE_USER')]
class AlertController extends AbstractController
{
    public function __construct(
        private AlertingService $alertingService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * List alerts for a store
     */
    #[Route('/stores/{storeId}/alerts', name: 'ecommerce_alerts', methods: ['GET'])]
    public function list(string $storeId, Request $request): JsonResponse
    {
        try {
            $store = $this->getStoreForUser($storeId);
            $resolved = $request->query->get('resolved', false);

            $alerts = match ($resolved) {
                'true' => $this->alertingService->getResolvedAlerts($store),
                'false' => $this->alertingService->getActiveAlerts($store),
                default => $this->alertingService->getAllAlerts($store),
            };

            return $this->json([
                'data' => array_map(fn(EcommerceAlert $a) => $this->serializeAlert($a), $alerts),
                'meta' => [
                    'pagination' => [
                        'total' => count($alerts),
                        'count' => count($alerts),
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
     * Create a new alert
     */
    #[Route('/stores/{storeId}/alerts', name: 'ecommerce_alert_create', methods: ['POST'])]
    public function create(string $storeId, Request $request): JsonResponse
    {
        try {
            $store = $this->getStoreForUser($storeId);
            $payload = json_decode($request->getContent(), true) ?? [];

            // Validate required fields
            $required = ['alert_type', 'severity'];
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

            // Validate alert type and severity
            $validAlertTypes = ['PAYMENT_FAILURE', 'LOW_SUCCESS_RATE', 'CHARGEBACK', 'HIGH_ABANDONMENT'];
            $validSeverities = ['INFO', 'WARNING', 'CRITICAL'];

            if (!in_array($payload['alert_type'], $validAlertTypes)) {
                return $this->json([
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Invalid alert_type',
                        'details' => ['alert_type' => ['Must be one of: ' . implode(', ', $validAlertTypes)]],
                    ]
                ], 400);
            }

            if (!in_array($payload['severity'], $validSeverities)) {
                return $this->json([
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Invalid severity',
                        'details' => ['severity' => ['Must be one of: ' . implode(', ', $validSeverities)]],
                    ]
                ], 400);
            }

            $alert = $this->alertingService->createManualAlert(
                $store,
                $payload['alert_type'],
                $payload['severity'],
                $payload['metric_value'] ?? null,
                $payload['threshold_value'] ?? null,
                $payload['description'] ?? null
            );

            $this->logger->info('Alert created', [
                'store_id' => $store->getId(),
                'alert_id' => $alert->getId(),
                'alert_type' => $alert->getAlertType(),
                'severity' => $alert->getSeverity(),
            ]);

            return $this->json(['data' => $this->serializeAlert($alert)], 201);
        } catch (StoreNotFoundException $e) {
            return $this->json([
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ]
            ], 404);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create alert', [
                'store_id' => $storeId,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'Failed to create alert',
                ]
            ], 500);
        }
    }

    /**
     * Resolve an alert
     */
    #[Route('/stores/{storeId}/alerts/{alertId}/resolve', name: 'ecommerce_alert_resolve', methods: ['POST'])]
    public function resolve(string $storeId, string $alertId): JsonResponse
    {
        try {
            $store = $this->getStoreForUser($storeId);
            $alert = $this->alertingService->resolveAlert($alertId, $store);

            $this->logger->info('Alert resolved', [
                'store_id' => $store->getId(),
                'alert_id' => $alert->getId(),
            ]);

            return $this->json(['data' => $this->serializeAlert($alert)]);
        } catch (StoreNotFoundException $e) {
            return $this->json([
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ]
            ], 404);
        } catch (\Exception $e) {
            $this->logger->error('Failed to resolve alert', [
                'store_id' => $storeId,
                'alert_id' => $alertId,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'Failed to resolve alert',
                ]
            ], 500);
        }
    }

    /**
     * Get store for the current user
     */
    private function getStoreForUser(string $storeId): Store
    {
        /** @var User $user */
        $user = $this->getUser();

        $store = $this->alertingService->getStoreById($storeId);
        if ($store->getUser() !== $user) {
            throw new StoreNotFoundException($storeId);
        }

        return $store;
    }

    /**
     * Serialize alert for API response
     */
    private function serializeAlert(EcommerceAlert $alert): array
    {
        return [
            'id' => (string) $alert->getId(),
            'alert_type' => $alert->getAlertType(),
            'severity' => $alert->getSeverity(),
            'triggered_at' => $alert->getTriggeredAt()?->format('c'),
            'metric_value' => $alert->getMetricValue(),
            'threshold_value' => $alert->getThresholdValue(),
            'description' => $alert->getDescription(),
            'resolved_at' => $alert->getResolvedAt()?->format('c'),
        ];
    }
}
