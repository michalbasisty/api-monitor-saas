<?php

namespace App\Modules\Ecommerce\Service;

use App\Modules\Ecommerce\Entity\CheckoutStep;
use App\Modules\Ecommerce\Entity\Store;
use Doctrine\ORM\EntityManagerInterface;

class CheckoutService
{
    public function __construct(private EntityManagerInterface $em)
    {
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
}
