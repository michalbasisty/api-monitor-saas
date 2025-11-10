<?php

namespace App\Modules\Ecommerce\Service;

use App\Modules\Ecommerce\Entity\EcommerceAlert;
use App\Modules\Ecommerce\Entity\PaymentGateway;
use App\Modules\Ecommerce\Entity\Store;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for creating and managing e-commerce alerts
 * 
 * Centralizes alert logic for payment failures, low success rates, chargebacks, etc.
 */
class AlertingService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger
    ) {}

    /**
     * Create payment failure alert
     */
    public function alertPaymentFailure(PaymentGateway $gateway, array $chargeData): EcommerceAlert
    {
        $alert = new EcommerceAlert();
        $alert->setStore($gateway->getStore());
        $alert->setAlertType('payment_failed');
        $alert->setSeverity('high');
        $alert->setTriggeredAt(new \DateTime());
        $alert->setDescription(
            "Payment declined: " . ($chargeData['failure_message'] ?? $chargeData['failure_code'] ?? 'unknown error')
        );

        $this->em->persist($alert);
        $this->em->flush();

        $this->logger->warning('Payment failure alert created', [
            'alert_id' => $alert->getId(),
            'store_id' => $gateway->getStore()->getId(),
            'gateway_id' => $gateway->getId(),
        ]);

        return $alert;
    }

    /**
     * Create low payment success rate alert (or update existing)
     */
    public function alertLowPaymentSuccessRate(PaymentGateway $gateway, float $successRate): EcommerceAlert
    {
        // Check if we already have an active alert for this
        $existingAlert = $this->em->getRepository(EcommerceAlert::class)
            ->findOneBy([
                'store' => $gateway->getStore(),
                'alertType' => 'low_payment_success_rate',
                'resolvedAt' => null,
            ]);

        if ($existingAlert) {
            // Update existing alert
            $existingAlert->setMetricValue($successRate);
            $this->em->flush();

            $this->logger->info('Payment success rate alert updated', [
                'alert_id' => $existingAlert->getId(),
                'success_rate' => $successRate,
            ]);

            return $existingAlert;
        }

        $alert = new EcommerceAlert();
        $alert->setStore($gateway->getStore());
        $alert->setAlertType('low_payment_success_rate');
        $alert->setSeverity('critical');
        $alert->setTriggeredAt(new \DateTime());
        $alert->setMetricValue($successRate);
        $alert->setThresholdValue(95);
        $alert->setDescription("Payment success rate below threshold: {$successRate}%");

        $this->em->persist($alert);
        $this->em->flush();

        $this->logger->critical('Payment success rate alert created', [
            'alert_id' => $alert->getId(),
            'store_id' => $gateway->getStore()->getId(),
            'success_rate' => $successRate,
        ]);

        return $alert;
    }

    /**
     * Create chargeback alert
     */
    public function alertChargeback(PaymentGateway $gateway, array $disputeData): EcommerceAlert
    {
        $alert = new EcommerceAlert();
        $alert->setStore($gateway->getStore());
        $alert->setAlertType('chargeback');
        $alert->setSeverity('critical');
        $alert->setTriggeredAt(new \DateTime());
        $alert->setDescription(
            "Chargeback filed: " . ($disputeData['reason'] ?? 'unknown reason')
        );

        $this->em->persist($alert);
        $this->em->flush();

        $this->logger->critical('Chargeback alert created', [
            'alert_id' => $alert->getId(),
            'store_id' => $gateway->getStore()->getId(),
            'dispute_id' => $disputeData['id'] ?? 'unknown',
        ]);

        return $alert;
    }

    /**
     * Create cart abandonment alert
     */
    public function alertHighAbandonmentRate(Store $store, float $abandonmentRate, float $threshold): EcommerceAlert
    {
        $existingAlert = $this->em->getRepository(EcommerceAlert::class)
            ->findOneBy([
                'store' => $store,
                'alertType' => 'high_abandonment_rate',
                'resolvedAt' => null,
            ]);

        if ($existingAlert) {
            $existingAlert->setMetricValue($abandonmentRate);
            $this->em->flush();

            $this->logger->info('Abandonment rate alert updated', [
                'alert_id' => $existingAlert->getId(),
                'abandonment_rate' => $abandonmentRate,
            ]);

            return $existingAlert;
        }

        $alert = new EcommerceAlert();
        $alert->setStore($store);
        $alert->setAlertType('high_abandonment_rate');
        $alert->setSeverity('high');
        $alert->setTriggeredAt(new \DateTime());
        $alert->setMetricValue($abandonmentRate);
        $alert->setThresholdValue($threshold);
        $alert->setDescription("Cart abandonment rate above threshold: {$abandonmentRate}%");

        $this->em->persist($alert);
        $this->em->flush();

        $this->logger->info('Abandonment rate alert created', [
            'alert_id' => $alert->getId(),
            'store_id' => $store->getId(),
            'abandonment_rate' => $abandonmentRate,
        ]);

        return $alert;
    }

    /**
     * Resolve an alert
     */
    public function resolveAlert(EcommerceAlert $alert): void
    {
        if ($alert->getResolvedAt() !== null) {
            return; // Already resolved
        }

        $alert->setResolvedAt(new \DateTime());
        $this->em->flush();

        $this->logger->info('Alert resolved', [
            'alert_id' => $alert->getId(),
            'alert_type' => $alert->getAlertType(),
        ]);
    }

    /**
     * Get active alerts for a store
     * 
     * @return EcommerceAlert[]
     */
    public function getActiveAlerts(Store $store): array
    {
        return $this->em->getRepository(EcommerceAlert::class)
            ->findBy(['store' => $store, 'resolvedAt' => null], ['triggeredAt' => 'DESC']);
    }
}
