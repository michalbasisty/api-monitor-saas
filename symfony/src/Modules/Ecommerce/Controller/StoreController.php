<?php

namespace App\Modules\Ecommerce\Controller;

use App\Entity\User;
use App\Modules\Ecommerce\Entity\Store;
use App\Modules\Ecommerce\Service\StoreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/ecommerce')]
#[IsGranted('ROLE_USER')]
class StoreController extends AbstractController
{
    public function __construct(
        private StoreService $storeService,
        private EntityManagerInterface $em
    ) {
    }

    #[Route('/stores', name: 'ecommerce_stores', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $stores = $this->em->getRepository(Store::class)
            ->findBy(['user' => $user]);

        return $this->json([
            'stores' => array_map(fn(Store $s) => [
                'id' => (string) $s->getId(),
                'store_name' => $s->getStoreName(),
                'store_url' => $s->getStoreUrl(),
                'platform' => $s->getPlatform(),
                'currency' => $s->getCurrency(),
                'timezone' => $s->getTimezone(),
                'created_at' => $s->getCreatedAt()?->format('c'),
            ], $stores),
        ]);
    }

    #[Route('/stores', name: 'ecommerce_store_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['store_name']) || !isset($data['store_url']) || !isset($data['platform'])) {
            return $this->json(['error' => 'Missing required fields'], 400);
        }

        try {
            /** @var User $user */
            $user = $this->getUser();
            $data['user'] = $user;

            $store = $this->storeService->createStore($data);

            return $this->json([
                'id' => (string) $store->getId(),
                'store_name' => $store->getStoreName(),
                'store_url' => $store->getStoreUrl(),
                'platform' => $store->getPlatform(),
                'created_at' => $store->getCreatedAt()?->format('c'),
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/stores/{id}', name: 'ecommerce_store_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $store = $this->em->getRepository(Store::class)->find($id);

        if (!$store || $store->getUser() !== $user) {
            return $this->json(['error' => 'Store not found'], 404);
        }

        return $this->json([
            'id' => (string) $store->getId(),
            'store_name' => $store->getStoreName(),
            'store_url' => $store->getStoreUrl(),
            'platform' => $store->getPlatform(),
            'currency' => $store->getCurrency(),
            'timezone' => $store->getTimezone(),
            'created_at' => $store->getCreatedAt()?->format('c'),
        ]);
    }

    #[Route('/stores/{id}', name: 'ecommerce_store_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $store = $this->em->getRepository(Store::class)->find($id);

        if (!$store || $store->getUser() !== $user) {
            return $this->json(['error' => 'Store not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        try {
            $store = $this->storeService->updateStore($store, $data);

            return $this->json([
                'id' => (string) $store->getId(),
                'store_name' => $store->getStoreName(),
                'currency' => $store->getCurrency(),
                'timezone' => $store->getTimezone(),
                'updated_at' => $store->getUpdatedAt()?->format('c'),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/stores/{id}/health', name: 'ecommerce_store_health', methods: ['GET'])]
    public function health(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $store = $this->em->getRepository(Store::class)->find($id);

        if (!$store || $store->getUser() !== $user) {
            return $this->json(['error' => 'Store not found'], 404);
        }

        // TODO: Calculate actual health metrics
        return $this->json([
            'status' => 'healthy',
            'uptime_percentage' => 99.9,
            'revenue_per_minute' => 125.50,
            'error_rate' => 0.1,
        ]);
    }
}
