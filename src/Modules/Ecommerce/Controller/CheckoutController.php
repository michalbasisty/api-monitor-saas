<?php

namespace App\Modules\Ecommerce\Controller;

use App\Modules\Ecommerce\Entity\Store;
use App\Modules\Ecommerce\Entity\CheckoutStep;
use App\Modules\Ecommerce\Repository\StoreRepository;
use App\Modules\Ecommerce\Repository\CheckoutStepRepository;
use App\Modules\Ecommerce\Service\CheckoutService;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

class CheckoutController extends AbstractController
{
    public function __construct(
        private StoreRepository $storeRepository,
        private CheckoutStepRepository $checkoutStepRepository,
        private CheckoutService $checkoutService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function listSteps(string $storeId): JsonResponse
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

            $steps = $this->checkoutStepRepository->findByStore($store);

            $data = array_map(fn(CheckoutStep $step) => [
                'id' => $step->getId(),
                'name' => $step->getName(),
                'position' => $step->getPosition(),
                'conversionRate' => $step->getConversionRate(),
                'avgTimeMs' => $step->getAvgTimeMs(),
                'abandonmentRate' => $step->getAbandonmentRate(),
            ], $steps);

            return new JsonResponse(['data' => $data]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to list checkout steps', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to list checkout steps'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function addStep(string $storeId, Request $request): JsonResponse
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

            if (empty($data['name']) || !isset($data['position'])) {
                return new JsonResponse(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
            }

            $step = $this->checkoutService->createCheckoutStep(
                $store,
                $data['name'],
                $data['position']
            );

            return new JsonResponse([
                'id' => $step->getId(),
                'name' => $step->getName(),
                'position' => $step->getPosition(),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create checkout step', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to create checkout step'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateStep(string $storeId, string $stepId, Request $request): JsonResponse
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

            $step = $this->checkoutStepRepository->find($stepId);
            if (!$step || $step->getStore() !== $store) {
                return new JsonResponse(['error' => 'Checkout step not found'], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);

            if (isset($data['name'])) {
                $step->setName($data['name']);
            }
            if (isset($data['position'])) {
                $step->setPosition($data['position']);
            }

            $this->entityManager->flush();

            return new JsonResponse(['message' => 'Checkout step updated']);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update checkout step', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to update checkout step'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteStep(string $storeId, string $stepId): JsonResponse
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

            $step = $this->checkoutStepRepository->find($stepId);
            if (!$step || $step->getStore() !== $store) {
                return new JsonResponse(['error' => 'Checkout step not found'], Response::HTTP_NOT_FOUND);
            }

            $this->entityManager->remove($step);
            $this->entityManager->flush();

            return new JsonResponse(['message' => 'Checkout step deleted']);
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete checkout step', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to delete checkout step'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function realtime(string $storeId): JsonResponse
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

            $realtimeData = $this->checkoutService->getRealtimeCheckoutMetrics($store);

            return new JsonResponse($realtimeData);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get realtime checkout metrics', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to get realtime checkout metrics'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function performance(string $storeId): JsonResponse
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

            $performance = $this->checkoutService->getCheckoutPerformance($store);

            return new JsonResponse($performance);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get checkout performance', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to get checkout performance'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
