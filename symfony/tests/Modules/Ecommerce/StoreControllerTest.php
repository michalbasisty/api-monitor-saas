<?php

namespace App\Tests\Modules\Ecommerce;

use App\Entity\User;
use App\Modules\Ecommerce\Entity\Store;
use App\Modules\Ecommerce\Repository\StoreRepository;
use App\Modules\Ecommerce\Service\StoreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

class StoreControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private StoreRepository $storeRepository;
    private StoreService $storeService;
    private User $testUser;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->storeRepository = $this->em->getRepository(Store::class);
        $this->storeService = self::getContainer()->get(StoreService::class);

        // Create test user
        $this->testUser = new User();
        $this->testUser->setId(Uuid::v4());
        $this->testUser->setEmail('test@example.com');
        $this->testUser->setPassword('hashed_password');
        $this->testUser->setIsVerified(true);

        $this->em->persist($this->testUser);
        $this->em->flush();
    }

    public function testListStores(): void
    {
        $client = static::createClient();
        
        // Create test store
        $store = $this->storeService->createStore(
            $this->testUser,
            'Test Store',
            'https://test.com',
            'shopify',
            'USD',
            'America/New_York'
        );

        $client->request('GET', '/api/ecommerce/stores', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->getJwtToken($this->testUser),
        ]);

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('data', $response);
        $this->assertCount(1, $response['data']);
        $this->assertEquals('Test Store', $response['data'][0]['storeName']);
    }

    public function testCreateStore(): void
    {
        $client = static::createClient();
        
        $payload = [
            'storeName' => 'New Store',
            'storeUrl' => 'https://newstore.com',
            'platform' => 'woocommerce',
            'currency' => 'EUR',
            'timezone' => 'Europe/London',
        ];

        $client->request('POST', '/api/ecommerce/stores', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->getJwtToken($this->testUser),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertEquals(201, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('New Store', $response['storeName']);
        $this->assertEquals('woocommerce', $response['platform']);
    }

    public function testGetStore(): void
    {
        $client = static::createClient();
        
        $store = $this->storeService->createStore(
            $this->testUser,
            'Test Store',
            'https://test.com',
            'shopify'
        );

        $client->request('GET', '/api/ecommerce/stores/' . $store->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->getJwtToken($this->testUser),
        ]);

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Test Store', $response['storeName']);
        $this->assertEquals('https://test.com', $response['storeUrl']);
    }

    public function testUpdateStore(): void
    {
        $client = static::createClient();
        
        $store = $this->storeService->createStore(
            $this->testUser,
            'Test Store',
            'https://test.com',
            'shopify'
        );

        $payload = [
            'storeName' => 'Updated Store Name',
            'timezone' => 'America/Chicago',
        ];

        $client->request('PUT', '/api/ecommerce/stores/' . $store->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->getJwtToken($this->testUser),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // Verify update
        $updated = $this->storeRepository->find($store->getId());
        $this->assertEquals('Updated Store Name', $updated->getStoreName());
        $this->assertEquals('America/Chicago', $updated->getTimezone());
    }

    public function testDeleteStore(): void
    {
        $client = static::createClient();
        
        $store = $this->storeService->createStore(
            $this->testUser,
            'Test Store',
            'https://test.com',
            'shopify'
        );

        $storeId = $store->getId();

        $client->request('DELETE', '/api/ecommerce/stores/' . $storeId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->getJwtToken($this->testUser),
        ]);

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // Verify deletion
        $this->assertNull($this->storeRepository->find($storeId));
    }

    public function testGetStoreHealth(): void
    {
        $client = static::createClient();
        
        $store = $this->storeService->createStore(
            $this->testUser,
            'Test Store',
            'https://test.com',
            'shopify'
        );

        $client->request('GET', '/api/ecommerce/stores/' . $store->getId() . '/health', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->getJwtToken($this->testUser),
        ]);

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('checkoutStepsConfigured', $response);
        $this->assertArrayHasKey('paymentGatewaysTotal', $response);
    }

    public function testUnauthorizedAccess(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/ecommerce/stores');

        $this->assertEquals(401, $client->getResponse()->getStatusCode());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    private function getJwtToken(User $user): string
    {
        $container = self::getContainer();
        $jwtManager = $container->get('lexik_jwt_authentication.jwt_manager');

        return $jwtManager->create($user);
    }
}
