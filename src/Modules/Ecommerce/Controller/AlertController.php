<?php

namespace App\Modules\Ecommerce\Controller;

use App\Modules\Ecommerce\Entity\EcommerceAlert;
use App\Modules\Ecommerce\Repository\StoreRepository;
use App\Modules\Ecommerce\Repository\AlertRepository;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

class AlertController extends AbstractController
{
    public function __construct(
        private StoreRepository $storeRepository,
        private AlertRepository $alertRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function list(string $storeId, Request $request): JsonResponse
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

            $status = $request->query->get('status');
            $alerts = $status 
                ? $this->alertRepository->findByStoreAndStatus($store, $status)
                : $this->alertRepository->findByStore($store);

            $data = array_map(fn(EcommerceAlert $alert) => [
                'id' => $alert->getId(),
                'type' => $alert->getType(),
                'message' => $alert->getMessage(),
                'severity' => $alert->getSeverity(),
                'status' => $alert->getStatus(),
                'metrics' => $alert->getMetrics(),
                'createdAt' => $alert->getCreatedAt()?->format('c'),
                'resolvedAt' => $alert->getResolvedAt()?->format('c'),
            ], $alerts);

            return new JsonResponse(['data' => $data]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to list alerts', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to list alerts'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function create(string $storeId, Request $request): JsonResponse
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

            $data = json_decode($request->getContent(), true);

            if (empty($data['type']) || empty($data['message']) || empty($data['severity'])) {
                return new JsonResponse(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
            }

            $alert = new EcommerceAlert();
            $alert->setStore($store);
            $alert->setType($data['type']);
            $alert->setMessage($data['message']);
            $alert->setSeverity($data['severity']);
            $alert->setStatus($data['status'] ?? 'active');
            $alert->setMetrics($data['metrics'] ?? []);
            $alert->setCreatedAt(new \DateTime());

            $this->entityManager->persist($alert);
            $this->entityManager->flush();

            return new JsonResponse([
                'id' => $alert->getId(),
                'type' => $alert->getType(),
                'message' => $alert->getMessage(),
                'severity' => $alert->getSeverity(),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create alert', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to create alert'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
