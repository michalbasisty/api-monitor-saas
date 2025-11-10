<?php

namespace App\Modules\Ecommerce\Controller;

use App\Modules\Ecommerce\Entity\Store;
use App\Modules\Ecommerce\Entity\PaymentGateway;
use App\Modules\Ecommerce\Repository\StoreRepository;
use App\Modules\Ecommerce\Repository\PaymentGatewayRepository;
use App\Modules\Ecommerce\Service\PaymentService;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

class PaymentController extends AbstractController
{
    public function __construct(
        private StoreRepository $storeRepository,
        private PaymentGatewayRepository $paymentGatewayRepository,
        private PaymentService $paymentService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function listGateways(string $storeId): JsonResponse
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

            $gateways = $this->paymentGatewayRepository->findByStore($store);

            $data = array_map(fn(PaymentGateway $gateway) => [
                'id' => $gateway->getId(),
                'provider' => $gateway->getProvider(),
                'isActive' => $gateway->isActive(),
                'successRate' => $gateway->getSuccessRate(),
                'avgProcessingTimeMs' => $gateway->getAvgProcessingTimeMs(),
                'failureRate' => $gateway->getFailureRate(),
                'declineRate' => $gateway->getDeclineRate(),
            ], $gateways);

            return new JsonResponse(['data' => $data]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to list payment gateways', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to list payment gateways'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function addGateway(string $storeId, Request $request): JsonResponse
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

            if (empty($data['provider']) || !isset($data['config'])) {
                return new JsonResponse(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
            }

            $gateway = $this->paymentService->addPaymentGateway(
                $store,
                $data['provider'],
                $data['config']
            );

            return new JsonResponse([
                'id' => $gateway->getId(),
                'provider' => $gateway->getProvider(),
                'isActive' => $gateway->isActive(),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            $this->logger->error('Failed to add payment gateway', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to add payment gateway'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function metrics(string $storeId, Request $request): JsonResponse
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

            $metrics = $this->paymentService->getPaymentMetrics($store, $timeframe);

            return new JsonResponse($metrics);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get payment metrics', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to get payment metrics'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
