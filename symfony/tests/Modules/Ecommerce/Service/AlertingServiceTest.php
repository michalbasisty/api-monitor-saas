<?php

namespace App\Tests\Modules\Ecommerce\Service;

use App\Modules\Ecommerce\Entity\EcommerceAlert;
use App\Modules\Ecommerce\Entity\PaymentGateway;
use App\Modules\Ecommerce\Entity\Store;
use App\Modules\Ecommerce\Service\AlertingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class AlertingServiceTest extends TestCase
{
    private AlertingService $alertingService;
    private EntityManagerInterface $entityManager;
    private NullLogger $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = new NullLogger();

        $this->alertingService = new AlertingService($this->entityManager, $this->logger);
    }

    public function testAlertPaymentFailure(): void
    {
        $store = new Store();
        $store->setStoreName('Test Store');

        $gateway = new PaymentGateway();
        $gateway->setStore($store);
        $gateway->setGatewayName('stripe');

        $chargeData = [
            'failure_code' => 'card_declined',
            'failure_message' => 'Your card was declined',
        ];

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(EcommerceAlert::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $alert = $this->alertingService->alertPaymentFailure($gateway, $chargeData);

        $this->assertEquals('payment_failed', $alert->getAlertType());
        $this->assertEquals('high', $alert->getSeverity());
        $this->assertStringContainsString('card_declined', $alert->getDescription());
    }

    public function testAlertLowPaymentSuccessRateCreatesNewAlert(): void
    {
        $store = new Store();
        $store->setStoreName('Test Store');

        $gateway = new PaymentGateway();
        $gateway->setStore($store);
        $gateway->setGatewayName('stripe');

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(EcommerceAlert::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $alert = $this->alertingService->alertLowPaymentSuccessRate($gateway, 85.5);

        $this->assertEquals('low_payment_success_rate', $alert->getAlertType());
        $this->assertEquals('critical', $alert->getSeverity());
        $this->assertEquals(85.5, $alert->getMetricValue());
        $this->assertEquals(95, $alert->getThresholdValue());
    }

    public function testAlertLowPaymentSuccessRateUpdatesExisting(): void
    {
        $store = new Store();
        $store->setStoreName('Test Store');

        $gateway = new PaymentGateway();
        $gateway->setStore($store);
        $gateway->setGatewayName('stripe');

        $existingAlert = new EcommerceAlert();
        $existingAlert->setAlertType('low_payment_success_rate');
        $existingAlert->setMetricValue(90.0);

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('findOneBy')->willReturn($existingAlert);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $alert = $this->alertingService->alertLowPaymentSuccessRate($gateway, 80.0);

        $this->assertEquals(80.0, $alert->getMetricValue());
    }

    public function testAlertChargeback(): void
    {
        $store = new Store();
        $store->setStoreName('Test Store');

        $gateway = new PaymentGateway();
        $gateway->setStore($store);
        $gateway->setGatewayName('stripe');

        $disputeData = [
            'id' => 'dp_123',
            'reason' => 'fraudulent',
        ];

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(EcommerceAlert::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $alert = $this->alertingService->alertChargeback($gateway, $disputeData);

        $this->assertEquals('chargeback', $alert->getAlertType());
        $this->assertEquals('critical', $alert->getSeverity());
        $this->assertStringContainsString('fraudulent', $alert->getDescription());
    }

    public function testAlertHighAbandonmentRateCreatesNewAlert(): void
    {
        $store = new Store();
        $store->setStoreName('Test Store');

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(EcommerceAlert::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $alert = $this->alertingService->alertHighAbandonmentRate($store, 72.5, 60);

        $this->assertEquals('high_abandonment_rate', $alert->getAlertType());
        $this->assertEquals('high', $alert->getSeverity());
        $this->assertEquals(72.5, $alert->getMetricValue());
        $this->assertEquals(60, $alert->getThresholdValue());
    }

    public function testAlertHighAbandonmentRateUpdatesExisting(): void
    {
        $store = new Store();
        $store->setStoreName('Test Store');

        $existingAlert = new EcommerceAlert();
        $existingAlert->setAlertType('high_abandonment_rate');
        $existingAlert->setMetricValue(70.0);

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('findOneBy')->willReturn($existingAlert);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $alert = $this->alertingService->alertHighAbandonmentRate($store, 75.0, 60);

        $this->assertEquals(75.0, $alert->getMetricValue());
    }

    public function testResolveAlert(): void
    {
        $alert = new EcommerceAlert();
        $alert->setAlertType('payment_failed');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->alertingService->resolveAlert($alert);

        $this->assertNotNull($alert->getResolvedAt());
    }

    public function testResolveAlertWhenAlreadyResolved(): void
    {
        $alert = new EcommerceAlert();
        $alert->setAlertType('payment_failed');
        $alert->setResolvedAt(new \DateTime());

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->alertingService->resolveAlert($alert);

        // Should not flush if already resolved
        $this->assertTrue(true);
    }

    public function testGetActiveAlerts(): void
    {
        $store = new Store();
        $store->setStoreName('Test Store');

        $alert1 = new EcommerceAlert();
        $alert1->setAlertType('payment_failed');

        $alert2 = new EcommerceAlert();
        $alert2->setAlertType('chargeback');

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('findBy')->willReturn([$alert1, $alert2]);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $alerts = $this->alertingService->getActiveAlerts($store);

        $this->assertCount(2, $alerts);
        $this->assertEquals('payment_failed', $alerts[0]->getAlertType());
        $this->assertEquals('chargeback', $alerts[1]->getAlertType());
    }
}
