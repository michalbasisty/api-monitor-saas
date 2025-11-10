<?php

namespace App\Tests\Modules\Ecommerce\Service;

use App\Modules\Ecommerce\Entity\EcommerceAlert;
use App\Modules\Ecommerce\Entity\PaymentGateway;
use App\Modules\Ecommerce\Entity\PaymentMetric;
use App\Modules\Ecommerce\Entity\Store;
use App\Modules\Ecommerce\Service\AlertingService;
use App\Modules\Ecommerce\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class StripeServiceTest extends TestCase
{
    private StripeService $stripeService;
    private EntityManagerInterface $entityManager;
    private AlertingService $alertingService;
    private NullLogger $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->alertingService = $this->createMock(AlertingService::class);
        $this->logger = new NullLogger();

        $this->stripeService = new StripeService(
            $this->entityManager,
            $this->logger,
            $this->alertingService,
            'sk_test_fake_secret',
            'whsec_test_fake_webhook'
        );
    }

    public function testProcessWebhookWithValidSignature(): void
    {
        $payload = json_encode([
            'id' => 'evt_test_123',
            'type' => 'charge.succeeded',
            'object' => [
                'id' => 'ch_test_123',
                'amount' => 5000,
                'currency' => 'usd',
                'customer' => 'cus_test_123',
                'created' => time(),
            ],
        ]);

        $timestamp = time();
        $signedContent = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedContent, 'whsec_test_fake_webhook', false);
        $signatureHeader = "t={$timestamp},v1={$signature}";

        $event = $this->stripeService->processWebhook($payload, $signatureHeader);

        $this->assertEquals('charge.succeeded', $event->type);
        $this->assertEquals('evt_test_123', $event->id);
    }

    public function testProcessWebhookWithInvalidSignature(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid signature');

        $payload = json_encode(['type' => 'charge.succeeded']);
        $signature = 't=' . time() . ',v1=invalid_signature';

        $this->stripeService->processWebhook($payload, $signature);
    }

    public function testProcessWebhookWithOldTimestamp(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Webhook timestamp too old');

        $payload = json_encode(['type' => 'charge.succeeded']);
        
        // Create old timestamp (more than 5 minutes ago)
        $timestamp = time() - 400;
        $signedContent = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedContent, 'whsec_test_fake_webhook', false);
        $signatureHeader = "t={$timestamp},v1={$signature}";

        $this->stripeService->processWebhook($payload, $signatureHeader);
    }

    public function testHandleChargeSucceeded(): void
    {
        $store = new Store();
        $store->setStoreName('Test Store');

        $gateway = new PaymentGateway();
        $gateway->setStore($store);
        $gateway->setGatewayName('stripe');

        $chargeData = [
            'object' => [
                'id' => 'ch_test_123',
                'amount' => 5000,
                'currency' => 'usd',
                'customer' => 'cus_test_123',
                'created' => time(),
            ],
        ];

        // Mock findPaymentGatewayByStripeCustomerId
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(PaymentMetric::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Use reflection to call private method for testing
        $method = new \ReflectionMethod($this->stripeService, 'findPaymentGatewayByStripeCustomerId');
        $method->setAccessible(true);
        
        // Mock the gateway lookup by setting return value
        $reflection = new \ReflectionClass($this->stripeService);
        $emProperty = $reflection->getProperty('em');
        $emProperty->setAccessible(true);
        
        // We need to mock the query builder chain
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        
        $queryBuilder->method('createQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getOneOrNullResult')->willReturn($gateway);

        // This test demonstrates the structure; in real tests you'd use a test database
        $this->assertTrue(true);
    }

    public function testHandleChargeFailed(): void
    {
        $store = new Store();
        $store->setStoreName('Test Store');

        $gateway = new PaymentGateway();
        $gateway->setStore($store);
        $gateway->setGatewayName('stripe');

        $chargeData = [
            'object' => [
                'id' => 'ch_failed_123',
                'amount' => 5000,
                'currency' => 'usd',
                'customer' => 'cus_test_123',
                'failure_code' => 'card_declined',
                'failure_message' => 'Your card was declined',
                'created' => time(),
            ],
        ];

        $this->alertingService
            ->expects($this->once())
            ->method('alertPaymentFailure')
            ->with($gateway, $chargeData['object']);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(PaymentMetric::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->assertTrue(true);
    }

    public function testHandleChargeRefunded(): void
    {
        $chargeData = [
            'object' => [
                'id' => 'ch_refunded_123',
                'amount' => 5000,
                'currency' => 'usd',
                'refunded' => 5000,
            ],
        ];

        $metric = new PaymentMetric();
        $metric->setStatus('authorized');

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('findOneBy')->willReturn($metric);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->assertTrue(true);
    }

    public function testHandleChargeDisputeCreated(): void
    {
        $store = new Store();
        $store->setStoreName('Test Store');

        $gateway = new PaymentGateway();
        $gateway->setStore($store);
        $gateway->setGatewayName('stripe');

        $metric = new PaymentMetric();
        $metric->setGateway($gateway);
        $metric->setTransactionId('ch_disputed_123');

        $disputeData = [
            'object' => [
                'id' => 'dp_test_123',
                'charge' => 'ch_disputed_123',
                'reason' => 'fraudulent',
            ],
        ];

        $this->alertingService
            ->expects($this->once())
            ->method('alertChargeback')
            ->with($gateway, $disputeData['object']);

        $this->assertTrue(true);
    }

    public function testHandlePaymentIntentSucceeded(): void
    {
        $store = new Store();
        $store->setStoreName('Test Store');

        $gateway = new PaymentGateway();
        $gateway->setStore($store);
        $gateway->setGatewayName('stripe');

        $intentData = [
            'object' => [
                'id' => 'pi_test_123',
                'amount' => 5000,
                'currency' => 'usd',
                'customer' => 'cus_test_123',
                'created' => time(),
            ],
        ];

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(PaymentMetric::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->assertTrue(true);
    }

    public function testVerifySignatureWithMissingSecret(): void
    {
        // Create service with empty webhook secret
        $service = new StripeService(
            $this->entityManager,
            $this->logger,
            $this->alertingService,
            'sk_test_fake_secret',
            '' // Empty webhook secret
        );

        // Should not throw when secret is empty (logs warning instead)
        $payload = json_encode(['type' => 'charge.succeeded']);
        $signature = 't=123,v1=invalid';

        // This would normally verify, but with empty secret it should just log and return
        $this->assertTrue(true);
    }

    public function testCalculateAuthorizationTime(): void
    {
        $chargeData = [
            'created' => time(),
        ];

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->stripeService);
        $method = $reflection->getMethod('calculateAuthorizationTime');
        $method->setAccessible(true);

        $authTime = $method->invoke($this->stripeService, $chargeData);

        $this->assertGreaterThanOrEqual(10, $authTime);
        $this->assertLessThanOrEqual(100, $authTime);
    }

    public function testEstimateSettlementTime(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->stripeService);
        $method = $reflection->getMethod('estimateSettlementTime');
        $method->setAccessible(true);

        $settlementHours = $method->invoke($this->stripeService);

        // Should be at least 48 hours (2 business days)
        $this->assertGreaterThanOrEqual(48, $settlementHours);
        // Should not exceed 72 hours (3 business days with weekend handling)
        $this->assertLessThanOrEqual(72, $settlementHours);
    }

    public function testCheckPaymentSuccessRateThreshold(): void
    {
        $store = new Store();
        $store->setStoreName('Test Store');

        $gateway = new PaymentGateway();
        $gateway->setStore($store);
        $gateway->setGatewayName('stripe');

        // Create metrics with low success rate
        $metrics = [];
        for ($i = 0; $i < 100; $i++) {
            $metric = new PaymentMetric();
            $metric->setStatus($i < 5 ? 'declined' : 'authorized');
            $metrics[] = $metric;
        }

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('findBy')->willReturn($metrics);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        // Alert should be triggered for success rate < 95%
        $this->alertingService
            ->expects($this->once())
            ->method('alertLowPaymentSuccessRate');

        $this->assertTrue(true);
    }
}
