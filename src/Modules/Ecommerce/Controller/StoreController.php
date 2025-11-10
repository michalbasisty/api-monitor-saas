<?php

namespace App\Modules\Ecommerce\Controller;

use App\Modules\Ecommerce\Entity\Store;
use App\Modules\Ecommerce\Repository\StoreRepository;
use App\Modules\Ecommerce\Service\StoreService;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

class StoreController extends AbstractController
{
    public function __construct(
        private StoreRepository $storeRepository,
        private StoreService $storeService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function list(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }

            $stores = $this->storeRepository->findByUser($user);
            
            $data = array_map(fn(Store $store) => [
                'id' => $store->getId(),
                'storeName' => $store->getStoreName(),
                'storeUrl' => $store->getStoreUrl(),
                'platform' => $store->getPlatform(),
                'currency' => $store->getCurrency(),
                'timezone' => $store->getTimezone(),
                'createdAt' => $store->getCreatedAt()?->format('c'),
                'updatedAt' => $store->getUpdatedAt()?->format('c'),
            ], $stores);

            return new JsonResponse(['data' => $data]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to list stores', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to list stores'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function create(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }

            $data = json_decode($request->getContent(), true);
            
            if (empty($data['storeName']) || empty($data['storeUrl']) || empty($data['platform'])) {
                return new JsonResponse(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
            }

            $store = $this->storeService->createStore(
                $user,
                $data['storeName'],
                $data['storeUrl'],
                $data['platform'],
                $data['currency'] ?? 'USD',
                $data['timezone'] ?? null
            );

            return new JsonResponse([
                'id' => $store->getId(),
                'storeName' => $store->getStoreName(),
                'storeUrl' => $store->getStoreUrl(),
                'platform' => $store->getPlatform(),
                'currency' => $store->getCurrency(),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create store', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to create store'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function get(string $id): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }

            $store = $this->storeRepository->find($id);
            if (!$store || $store->getUser() !== $user) {
                return new JsonResponse(['error' => 'Store not found'], Response::HTTP_NOT_FOUND);
            }

            return new JsonResponse([
                'id' => $store->getId(),
                'storeName' => $store->getStoreName(),
                'storeUrl' => $store->getStoreUrl(),
                'platform' => $store->getPlatform(),
                'currency' => $store->getCurrency(),
                'timezone' => $store->getTimezone(),
                'metadata' => $store->getMetadata(),
                'createdAt' => $store->getCreatedAt()?->format('c'),
                'updatedAt' => $store->getUpdatedAt()?->format('c'),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get store', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to get store'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(string $id, Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }

            $store = $this->storeRepository->find($id);
            if (!$store || $store->getUser() !== $user) {
                return new JsonResponse(['error' => 'Store not found'], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);

            if (isset($data['storeName'])) {
                $store->setStoreName($data['storeName']);
            }
            if (isset($data['timezone'])) {
                $store->setTimezone($data['timezone']);
            }
            if (isset($data['metadata'])) {
                $store->setMetadata($data['metadata']);
            }

            $store->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            return new JsonResponse(['message' => 'Store updated']);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update store', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to update store'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function delete(string $id): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }

            $store = $this->storeRepository->find($id);
            if (!$store || $store->getUser() !== $user) {
                return new JsonResponse(['error' => 'Store not found'], Response::HTTP_NOT_FOUND);
            }

            $this->entityManager->remove($store);
            $this->entityManager->flush();

            return new JsonResponse(['message' => 'Store deleted']);
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete store', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to delete store'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function health(string $id): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }

            $store = $this->storeRepository->find($id);
            if (!$store || $store->getUser() !== $user) {
                return new JsonResponse(['error' => 'Store not found'], Response::HTTP_NOT_FOUND);
            }

            $health = $this->storeService->getStoreHealth($store);

            return new JsonResponse($health);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get store health', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to get store health'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
