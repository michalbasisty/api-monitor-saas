<?php

namespace App\Modules\Ecommerce\Controller;

use App\Entity\User;
use App\Modules\Ecommerce\Entity\PaymentGateway;
use App\Modules\Ecommerce\Entity\PaymentMetric;
use App\Modules\Ecommerce\Entity\Store;
use App\Modules\Ecommerce\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
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
        private EntityManagerInterface $em
    ) {
    }

    private function getStoreForUser(string $storeId): ?Store
    {
        /** @var User $user */
        $user = $this->getUser();
        $store = $this->em->getRepository(Store::class)->find($storeId);

        if (!$store || $store->getUser() !== $user) {
            return null;
        }

        return $store;
    }

    #[Route('/stores/{storeId}/payment-gateways', name: 'ecommerce_payment_gateways', methods: ['GET'])]
    public function listGateways(string $storeId): JsonResponse
    {
        $store = $this->getStoreForUser($storeId);
        if (!$store) {
            return $this->json(['error' => 'Store not found'], 404);
        }

        $gateways = $this->paymentService->getPaymentGateways($store);

        return $this->json([
            'gateways' => array_map(fn(PaymentGateway $g) => [
                'id' => (string) $g->getId(),
                'gateway_name' => $g->getGatewayName(),
                'is_primary' => $g->isPrimary(),
                'enabled' => $g->isEnabled(),
                'created_at' => $g->getCreatedAt()?->format('c'),
            ], $gateways),
        ]);
    }

    #[Route('/stores/{storeId}/payment-gateways', name: 'ecommerce_payment_add_gateway', methods: ['POST'])]
    public function addGateway(string $storeId, Request $request): JsonResponse
    {
        $store = $this->getStoreForUser($storeId);
        if (!$store) {
            return $this->json(['error' => 'Store not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['gateway_name']) || !isset($data['api_key'])) {
            return $this->json(['error' => 'Missing required fields'], 400);
        }

        try {
            $gateway = $this->paymentService->addPaymentGateway($store, $data);

            return $this->json([
                'id' => (string) $gateway->getId(),
                'gateway_name' => $gateway->getGatewayName(),
                'is_primary' => $gateway->isPrimary(),
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/stores/{storeId}/payment-metrics', name: 'ecommerce_payment_metrics', methods: ['GET'])]
    public function metrics(string $storeId): JsonResponse
    {
        $store = $this->getStoreForUser($storeId);
        if (!$store) {
            return $this->json(['error' => 'Store not found'], 404);
        }

        // TODO: Calculate payment success rate and metrics
        $metrics = $this->em->getRepository(PaymentMetric::class)
            ->createQueryBuilder('m')
            ->where('m.store = :store')
            ->setParameter('store', $store)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1000)
            ->getQuery()
            ->getResult();

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

        return $this->json([
            'store_id' => (string) $store->getId(),
            'authorization_success_rate' => round($successRate, 2),
            'declined_rate' => round(($declined / $total) * 100, 2),
            'total_transactions' => $total,
            'authorized_transactions' => $authorized,
        ]);
    }
}
