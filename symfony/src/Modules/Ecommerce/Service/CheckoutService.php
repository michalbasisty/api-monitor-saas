<?php

namespace App\Modules\Ecommerce\Service;

use App\Exception\StoreNotFoundException;
use App\Modules\Ecommerce\Entity\CheckoutMetric;
use App\Modules\Ecommerce\Entity\CheckoutStep;
use App\Modules\Ecommerce\Entity\Store;
use Doctrine\ORM\EntityManagerInterface;

class CheckoutService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function getStoreById(string $storeId): Store
    {
        $store = $this->em->getRepository(Store::class)->find($storeId);
        if (!$store) {
            throw new StoreNotFoundException($storeId);
        }
        return $store;
    }

    public function addCheckoutStep(Store $store, array $data): CheckoutStep
    {
        $step = new CheckoutStep();
        $step->setStore($store);
        $step->setStepName($data['step_name']);
        $step->setEndpointUrl($data['endpoint_url']);
        $step->setStepNumber($data['step_number']);
        $step->setExpectedLoadTimeMs($data['expected_load_time_ms'] ?? 1000);
        $step->setAlertThresholdMs($data['alert_threshold_ms'] ?? 2000);

        $this->em->persist($step);
        $this->em->flush();

        return $step;
    }

    public function getCheckoutSteps(Store $store): array
    {
        return $this->em->getRepository(CheckoutStep::class)
            ->findBy(['store' => $store], ['stepNumber' => 'ASC']);
    }

    public function getRealtimeMetrics(Store $store, int $limit = 100): array
    {
        return $this->em->getRepository(CheckoutMetric::class)
            ->createQueryBuilder('m')
            ->where('m.store = :store')
            ->setParameter('store', $store)
            ->orderBy('m.timestamp', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function updateCheckoutStep(CheckoutStep $step, array $data): CheckoutStep
    {
        if (isset($data['step_name'])) {
            $step->setStepName($data['step_name']);
        }
        if (isset($data['endpoint_url'])) {
            $step->setEndpointUrl($data['endpoint_url']);
        }
        if (isset($data['alert_threshold_ms'])) {
            $step->setAlertThresholdMs($data['alert_threshold_ms']);
        }

        $step->setUpdatedAt(new \DateTime());
        $this->em->flush();

        return $step;
    }

    public function deleteCheckoutStep(CheckoutStep $step): void
    {
        $this->em->remove($step);
        $this->em->flush();
    }

    /**
     * Get real-time checkout metrics for funnel analysis
     */
    public function getRealtimeCheckoutMetrics(Store $store): array
    {
        $steps = $this->getCheckoutSteps($store);
        $metrics = [];

        foreach ($steps as $step) {
            $stepMetrics = $this->em->getRepository(CheckoutMetric::class)
                ->createQueryBuilder('m')
                ->where('m.checkoutStep = :step')
                ->andWhere('m.timestamp >= :since')
                ->setParameter('step', $step)
                ->setParameter('since', new \DateTime('-1 hour'))
                ->orderBy('m.timestamp', 'DESC')
                ->setMaxResults(50)
                ->getQuery()
                ->getResult();

            $metrics[$step->getId()] = [
                'step_id' => $step->getId(),
                'step_name' => $step->getStepName(),
                'step_number' => $step->getStepNumber(),
                'metrics_count' => count($stepMetrics),
                'average_load_time' => $this->calculateAverageLoadTime($stepMetrics),
                'abandonment_rate' => $this->calculateAbandonmentRate($step),
                'conversion_rate' => $step->getConversionRate(),
            ];
        }

        return $metrics;
    }

    /**
     * Get checkout performance metrics and trends
     */
    public function getCheckoutPerformance(Store $store): array
    {
        $steps = $this->getCheckoutSteps($store);
        $performance = [];

        foreach ($steps as $step) {
            $performance[] = [
                'step_id' => $step->getId(),
                'step_name' => $step->getStepName(),
                'step_number' => $step->getStepNumber(),
                'expected_load_time_ms' => $step->getExpectedLoadTimeMs(),
                'alert_threshold_ms' => $step->getAlertThresholdMs(),
                'conversion_rate' => $step->getConversionRate(),
                'abandonment_rate' => $step->getAbandonmentRate(),
                'average_timing' => $this->calculateAverageLoadTime(
                    $this->getRealtimeMetrics($store, 100)
                ),
            ];
        }

        return $performance;
    }

    /**
     * Calculate average load time from metrics
     */
    private function calculateAverageLoadTime(array $metrics): float
    {
        if (empty($metrics)) {
            return 0;
        }

        $total = 0;
        foreach ($metrics as $metric) {
            $total += $metric->getLoadTimeMs() ?? 0;
        }

        return $total / count($metrics);
    }

    /**
     * Calculate abandonment rate for a checkout step
     */
    private function calculateAbandonmentRate(CheckoutStep $step): float
    {
        // Get metrics from last 24 hours
        $metrics = $this->em->getRepository(CheckoutMetric::class)
            ->createQueryBuilder('m')
            ->where('m.checkoutStep = :step')
            ->andWhere('m.timestamp >= :since')
            ->setParameter('step', $step)
            ->setParameter('since', new \DateTime('-24 hours'))
            ->getQuery()
            ->getResult();

        if (empty($metrics)) {
            return 0;
        }

        $abandoned = count(array_filter(
            $metrics,
            fn($m) => $m->getSessionAbandoned()
        ));

        return ($abandoned / count($metrics)) * 100;
    }
}
