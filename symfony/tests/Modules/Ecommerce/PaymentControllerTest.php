<?php

namespace App\Tests\Modules\Ecommerce;

use App\Entity\User;
use App\Exception\StoreNotFoundException;
use App\Modules\Ecommerce\Entity\PaymentGateway;
use App\Modules\Ecommerce\Entity\PaymentMetric;
use App\Modules\Ecommerce\Entity\Store;
use App\Modules\Ecommerce\Repository\PaymentGatewayRepository;
use App\Modules\Ecommerce\Repository\PaymentMetricRepository;
use App\Modules\Ecommerce\Repository\StoreRepository;
use App\Modules\Ecommerce\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

class PaymentControllerTest extends WebTestCase
{
    private StoreRepository $storeRepository;
    private PaymentMetricRepository $metricRepository;
    private PaymentGatewayRepository $gatewayRepository;
    private PaymentService $paymentService;
    private User $testUser;
    private Store $testStore;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->storeRepository = $container->get(StoreRepository::class);
        $this->metricRepository = $container->get(PaymentMetricRepository::class);
        $this->gatewayRepository = $container->get(PaymentGatewayRepository::class);
        $this->paymentService = $container->get(PaymentService::class);

        // Create test user
        $this->testUser = new User();
        $this->testUser->setId(Uuid::v4());
        $this->testUser->setEmail('payment-test@example.com');
        $this->testUser->setPassword('hashed_password');
        $this->testUser->setIsVerified(true);

        // Create test store
        $this->testStore = new Store();
        $this->testStore->setId(Uuid::v4());
        $this->testStore->setUser($this->testUser);
        $this->testStore->setStoreName('Payment Test Store');
        $this->testStore->setStoreUrl('https://paymenttest.com');
        $this->testStore->setPlatform('shopify');
    }

    public function testListPaymentGateways(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/ecommerce/stores/' . $this->testStore->getId() . '/payment-gateways', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->getJwtToken($this->testUser),
        ]);

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('meta', $response);
        $this->assertIsArray($response['data']);
    }

    public function testListPaymentGatewaysUnauthorizedStore(): void
    {
        $client = static::createClient();
        
        // Create another user's store
        $otherUser = new User();
        $otherUser->setId(Uuid::v4());
        $otherUser->setEmail('other@example.com');
        $otherUser->setPassword('hashed');
        
        $otherStore = new Store();
        $otherStore->setId(Uuid::v4());
        $otherStore->setUser($otherUser);
        $otherStore->setStoreName('Other Store');
        $otherStore->setStoreUrl('https://other.com');
        $otherStore->setPlatform('woocommerce');

        $client->request('GET', '/api/ecommerce/stores/' . $otherStore->getId() . '/payment-gateways', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->getJwtToken($this->testUser),
        ]);

        $this->assertEquals(404, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
    }

    public function testAddPaymentGateway(): void
    {
        $client = static::createClient();

        $payload = [
            'gateway_name' => 'stripe',
            'api_key' => 'sk_test_123456',
            'webhook_secret' => 'whsec_test_123456',
        ];

        $client->request('POST', '/api/ecommerce/stores/' . $this->testStore->getId() . '/payment-gateways', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->getJwtToken($this->testUser),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertEquals(201, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('stripe', $response['data']['gateway_name']);
    }

    public function testAddPaymentGatewayMissingFields(): void
    {
        $client = static::createClient();

        $payload = [
            'gateway_name' => 'stripe',
            // Missing api_key
        ];

        $client->request('POST', '/api/ecommerce/stores/' . $this->testStore->getId() . '/payment-gateways', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->getJwtToken($this->testUser),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertEquals(400, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('VALIDATION_ERROR', $response['error']['code']);
        $this->assertArrayHasKey('api_key', $response['error']['details']);
    }

    public function testGetPaymentMetrics(): void
    {
        $client = static::createClient();

        // Create test metrics
        $metric1 = new PaymentMetric();
        $metric1->setStore($this->testStore);
        $metric1->setTransactionId('txn_1');
        $metric1->setAmount(100);
        $metric1->setCurrency('USD');
        $metric1->setStatus('authorized');

        $metric2 = new PaymentMetric();
        $metric2->setStore($this->testStore);
        $metric2->setTransactionId('txn_2');
        $metric2->setAmount(50);
        $metric2->setCurrency('USD');
        $metric2->setStatus('declined');

        $client->request('GET', '/api/ecommerce/stores/' . $this->testStore->getId() . '/payment-metrics', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->getJwtToken($this->testUser),
        ]);

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('authorization_success_rate', $response['data']);
        $this->assertArrayHasKey('declined_rate', $response['data']);
        $this->assertArrayHasKey('total_transactions', $response['data']);
    }

    public function testGetPaymentMetricsForNonexistentStore(): void
    {
        $client = static::createClient();

        $fakeStoreId = Uuid::v4();

        $client->request('GET', '/api/ecommerce/stores/' . $fakeStoreId . '/payment-metrics', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->getJwtToken($this->testUser),
        ]);

        $this->assertEquals(404, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('error', $response);
    }

    public function testCalculatePaymentMetricsWithMixedStatuses(): void
    {
        $client = static::createClient();

        // Create 10 authorized and 5 declined metrics
        for ($i = 0; $i < 10; $i++) {
            $metric = new PaymentMetric();
            $metric->setStore($this->testStore);
            $metric->setTransactionId('txn_auth_' . $i);
            $metric->setAmount(100 + $i);
            $metric->setCurrency('USD');
            $metric->setStatus('authorized');
        }

        for ($i = 0; $i < 5; $i++) {
            $metric = new PaymentMetric();
            $metric->setStore($this->testStore);
            $metric->setTransactionId('txn_declined_' . $i);
            $metric->setAmount(50 + $i);
            $metric->setCurrency('USD');
            $metric->setStatus('declined');
        }

        $client->request('GET', '/api/ecommerce/stores/' . $this->testStore->getId() . '/payment-metrics', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->getJwtToken($this->testUser),
        ]);

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        
        // 10 authorized out of 15 total = 66.67%
        $this->assertEquals(15, $response['data']['total_transactions']);
        $this->assertEquals(10, $response['data']['authorized_transactions']);
        $this->assertEquals(5, $response['data']['declined_transactions']);
        $this->assertGreaterThan(66, $response['data']['authorization_success_rate']);
    }

    public function testUnauthorizedAccessRequiresBearer(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/ecommerce/stores/' . $this->testStore->getId() . '/payment-gateways');

        $this->assertEquals(401, $client->getResponse()->getStatusCode());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    private function getJwtToken(User $user): string
    {
        $container = self::getContainer();
        $jwtManager = $container->get('lexik_jwt_authentication.jwt_manager');

        return $jwtManager->create($user);
    }
}
