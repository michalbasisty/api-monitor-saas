<?php

namespace App\Modules\Ecommerce\Service;

use App\Modules\Ecommerce\Entity\Store;
use App\Modules\Ecommerce\Entity\CheckoutStep;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class CheckoutService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function createCheckoutStep(Store $store, string $name, int $position): CheckoutStep
    {
        $step = new CheckoutStep();
        $step->setId(Uuid::v4());
        $step->setStore($store);
        $step->setName($name);
        $step->setPosition($position);
        $step->setConversionRate(0.0);
        $step->setAvgTimeMs(0);
        $step->setAbandonmentRate(0.0);
        $step->setCreatedAt(new \DateTime());

        $this->entityManager->persist($step);
        $this->entityManager->flush();

        return $step;
    }

    public function getRealtimeCheckoutMetrics(Store $store): array
    {
        $steps = $store->getCheckoutSteps();

        return [
            'storeId' => $store->getId(),
            'activeUsers' => 0,
            'completions' => 0,
            'abandonment' => 0,
            'steps' => array_map(fn(CheckoutStep $step) => [
                'id' => $step->getId(),
                'name' => $step->getName(),
                'position' => $step->getPosition(),
                'conversionRate' => $step->getConversionRate(),
                'avgTimeMs' => $step->getAvgTimeMs(),
                'abandonmentRate' => $step->getAbandonmentRate(),
            ], $steps->toArray()),
            'timestamp' => (new \DateTime())->format('c'),
        ];
    }

    public function getCheckoutPerformance(Store $store): array
    {
        $steps = $store->getCheckoutSteps();

        $totalSteps = $steps->count();
        $avgConversion = $totalSteps > 0 
            ? array_sum(array_map(fn(CheckoutStep $s) => $s->getConversionRate(), $steps->toArray())) / $totalSteps
            : 0;
        $avgTime = $totalSteps > 0
            ? array_sum(array_map(fn(CheckoutStep $s) => $s->getAvgTimeMs(), $steps->toArray())) / $totalSteps
            : 0;

        return [
            'storeId' => $store->getId(),
            'totalSteps' => $totalSteps,
            'avgConversionRate' => round($avgConversion, 2),
            'avgCheckoutTime' => round($avgTime, 0),
            'overallAbandonmentRate' => 0.0,
            'bottleneck' => null,
        ];
    }
}
