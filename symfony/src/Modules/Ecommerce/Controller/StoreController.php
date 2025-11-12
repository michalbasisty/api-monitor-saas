<?php

namespace App\Modules\Ecommerce\Controller;

use App\Entity\User;
use App\Exception\StoreNotFoundException;
use App\Exception\ValidationException;
use App\Modules\Ecommerce\DTO\CreateStoreDto;
use App\Modules\Ecommerce\DTO\UpdateStoreDto;
use App\Modules\Ecommerce\Entity\Store;
use App\Modules\Ecommerce\Service\StoreService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/ecommerce')]
#[IsGranted('ROLE_USER')]
class StoreController extends AbstractController
{
    public function __construct(
        private StoreService $storeService,
        private ValidatorInterface $validator,
        private LoggerInterface $logger
    ) {
    }

    /**
     * List all stores for the current user
     */
    #[Route('/stores', name: 'ecommerce_stores', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $stores = $this->storeService->getUserStores($user);

        return $this->json([
            'data' => array_map(fn(Store $s) => $this->serializeStore($s), $stores),
            'meta' => [
                'pagination' => [
                    'total' => count($stores),
                    'count' => count($stores),
                ]
            ]
        ]);
    }

    /**
     * Create a new store
     */
    #[Route('/stores', name: 'ecommerce_store_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true) ?? [];

            $dto = new CreateStoreDto(
                storeName: $payload['store_name'] ?? '',
                storeUrl: $payload['store_url'] ?? '',
                platform: $payload['platform'] ?? '',
                currency: $payload['currency'] ?? 'USD',
                timezone: $payload['timezone'] ?? null
            );

            // Validate DTO
            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                $fieldErrors = [];
                foreach ($errors as $error) {
                    $fieldErrors[$error->getPropertyPath()][] = $error->getMessage();
                }
                throw new ValidationException($fieldErrors);
            }

            /** @var User $user */
            $user = $this->getUser();
            $store = $this->storeService->createStore($dto, $user);

            return $this->json(
                ['data' => $this->serializeStore($store)],
                201
            );
        } catch (ValidationException $e) {
            return $this->json([
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                    'details' => $e->getFieldErrors(),
                ]
            ], 400);
        } catch (\Exception $e) {
            $this->logger->error('Store creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->json([
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'Failed to create store',
                ]
            ], 500);
        }
    }

    /**
     * Get a single store
     */
    #[Route('/stores/{id}', name: 'ecommerce_store_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            $store = $this->storeService->getStore($id);

            // Verify ownership
            if ($store->getUser() !== $user) {
                throw new StoreNotFoundException($id);
            }

            return $this->json(['data' => $this->serializeStore($store)]);
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
     * Update a store
     */
    #[Route('/stores/{id}', name: 'ecommerce_store_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true) ?? [];

            $dto = new UpdateStoreDto(
                storeName: $payload['store_name'] ?? null,
                currency: $payload['currency'] ?? null,
                timezone: $payload['timezone'] ?? null
            );

            // Validate DTO
            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                $fieldErrors = [];
                foreach ($errors as $error) {
                    $fieldErrors[$error->getPropertyPath()][] = $error->getMessage();
                }
                throw new ValidationException($fieldErrors);
            }

            /** @var User $user */
            $user = $this->getUser();
            $store = $this->storeService->updateStore($id, $dto, $user);

            return $this->json(['data' => $this->serializeStore($store)]);
        } catch (StoreNotFoundException $e) {
            return $this->json([
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ]
            ], 404);
        } catch (ValidationException $e) {
            return $this->json([
                'error' => [
                    'code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                    'details' => $e->getFieldErrors(),
                ]
            ], 400);
        }
    }

    /**
     * Delete a store
     */
    #[Route('/stores/{id}', name: 'ecommerce_store_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            $this->storeService->deleteStore($id, $user);

            return $this->json(null, 204);
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
     * Get store health status
     */
    #[Route('/stores/{id}/health', name: 'ecommerce_store_health', methods: ['GET'])]
    public function health(string $id): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            $store = $this->storeService->getStore($id);

            // Verify ownership
            if ($store->getUser() !== $user) {
                throw new StoreNotFoundException($id);
            }

            // Calculate actual health metrics from real data
            $healthData = $this->storeService->calculateStoreHealth($store);

            return $this->json([
                'data' => [
                    'status' => $healthData['status'],
                    'uptime_percentage' => $healthData['uptime_percentage'],
                    'revenue_per_minute' => $healthData['revenue_per_minute'],
                    'error_rate' => $healthData['error_rate'],
                    'last_check' => (new \DateTime())->format('c'),
                    'metrics' => $healthData['metrics'],
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
     * Serialize a store entity for API responses
     */
    private function serializeStore(Store $store): array
    {
        return [
            'id' => (string) $store->getId(),
            'store_name' => $store->getStoreName(),
            'store_url' => $store->getStoreUrl(),
            'platform' => $store->getPlatform(),
            'currency' => $store->getCurrency(),
            'timezone' => $store->getTimezone(),
            'created_at' => $store->getCreatedAt()?->format('c'),
            'updated_at' => $store->getUpdatedAt()?->format('c'),
        ];
    }
}
