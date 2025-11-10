<?php

namespace App\Modules\Ecommerce\Controller;

use App\Entity\User;
use App\Modules\Ecommerce\Entity\CheckoutMetric;
use App\Modules\Ecommerce\Entity\CheckoutStep;
use App\Modules\Ecommerce\Entity\Store;
use App\Modules\Ecommerce\Service\CheckoutService;
use Doctrine\ORM\EntityManagerInterface;
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

    #[Route('/stores/{storeId}/checkout-steps', name: 'ecommerce_checkout_steps', methods: ['GET'])]
    public function listSteps(string $storeId): JsonResponse
    {
        $store = $this->getStoreForUser($storeId);
        if (!$store) {
            return $this->json(['error' => 'Store not found'], 404);
        }

        $steps = $this->checkoutService->getCheckoutSteps($store);

        return $this->json([
            'steps' => array_map(fn(CheckoutStep $s) => [
                'id' => (string) $s->getId(),
                'step_number' => $s->getStepNumber(),
                'step_name' => $s->getStepName(),
                'endpoint_url' => $s->getEndpointUrl(),
                'expected_load_time_ms' => $s->getExpectedLoadTimeMs(),
                'alert_threshold_ms' => $s->getAlertThresholdMs(),
                'enabled' => $s->isEnabled(),
            ], $steps),
        ]);
    }

    #[Route('/stores/{storeId}/checkout-steps', name: 'ecommerce_checkout_add_step', methods: ['POST'])]
    public function addStep(string $storeId, Request $request): JsonResponse
    {
        $store = $this->getStoreForUser($storeId);
        if (!$store) {
            return $this->json(['error' => 'Store not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['step_name']) || !isset($data['endpoint_url']) || !isset($data['step_number'])) {
            return $this->json(['error' => 'Missing required fields'], 400);
        }

        try {
            $step = $this->checkoutService->addCheckoutStep($store, $data);

            return $this->json([
                'id' => (string) $step->getId(),
                'step_number' => $step->getStepNumber(),
                'step_name' => $step->getStepName(),
                'endpoint_url' => $step->getEndpointUrl(),
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/stores/{storeId}/checkout/realtime', name: 'ecommerce_checkout_realtime', methods: ['GET'])]
    public function realtime(string $storeId): JsonResponse
    {
        $store = $this->getStoreForUser($storeId);
        if (!$store) {
            return $this->json(['error' => 'Store not found'], 404);
        }

        // TODO: Get real-time metrics from last 1 minute
        $steps = $this->checkoutService->getCheckoutSteps($store);
        $metrics = $this->em->getRepository(CheckoutMetric::class)
            ->createQueryBuilder('m')
            ->where('m.store = :store')
            ->setParameter('store', $store)
            ->orderBy('m.timestamp', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        return $this->json([
            'store_id' => (string) $store->getId(),
            'steps_count' => count($steps),
            'metrics_count' => count($metrics),
            'metrics' => array_map(fn(CheckoutMetric $m) => [
                'step_id' => (string) $m->getStep()?->getId(),
                'load_time_ms' => $m->getLoadTimeMs(),
                'error_occurred' => $m->isErrorOccurred(),
                'http_status_code' => $m->getHttpStatusCode(),
                'timestamp' => $m->getTimestamp()?->format('c'),
            ], $metrics),
        ]);
    }

    #[Route('/stores/{storeId}/checkout/performance', name: 'ecommerce_checkout_performance', methods: ['GET'])]
    public function performance(string $storeId): JsonResponse
    {
        $store = $this->getStoreForUser($storeId);
        if (!$store) {
            return $this->json(['error' => 'Store not found'], 404);
        }

        // TODO: Calculate aggregated performance metrics
        $steps = $this->checkoutService->getCheckoutSteps($store);

        return $this->json([
            'store_id' => (string) $store->getId(),
            'steps' => array_map(fn(CheckoutStep $s) => [
                'id' => (string) $s->getId(),
                'name' => $s->getStepName(),
                'avg_load_time_ms' => 0, // TODO: Calculate
                'error_rate_percentage' => 0, // TODO: Calculate
                'completion_rate_percentage' => 0, // TODO: Calculate
            ], $steps),
            'overall_completion_rate' => 0, // TODO: Calculate
        ]);
    }
}
