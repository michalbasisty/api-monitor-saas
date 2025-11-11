<?php

namespace App\Tests\Unit\Service\Stripe;

use App\Entity\User;
use App\Service\Stripe\StripeWebhookProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class StripeWebhookProcessorTest extends TestCase
{
    private StripeWebhookProcessor $processor;
    private MockObject $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->processor = new StripeWebhookProcessor($this->entityManager);
    }

    public function testHandleSubscriptionCreated_UpdatesUserSubscription(): void
    {
        $user = new User();
        $user->setId('user-uuid');

        $subscription = $this->createSubscriptionMock(
            customerId: 'cus_12345',
            subscriptionId: 'sub_12345',
            priceId: 'price_pro_monthly'
        );

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['stripe_customer_id' => 'cus_12345'])
            ->willReturn($user);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->processor->handleSubscriptionCreated($subscription);

        $this->assertEquals('sub_12345', $user->getStripeSubscriptionId());
        $this->assertTrue($user->isActiveSubscription());
    }

    public function testHandleSubscriptionCreated_WithNonexistentUser_DoesNothing(): void
    {
        $subscription = $this->createSubscriptionMock(customerId: 'cus_nonexistent');

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->processor->handleSubscriptionCreated($subscription);
    }

    public function testHandleSubscriptionDeleted_DowngradesUserToFree(): void
    {
        $user = new User();
        $user->setStripeSubscriptionId('sub_12345');
        $user->setSubscriptionTier('pro');
        $user->setIsActiveSubscription(true);

        $subscription = $this->createSubscriptionMock(customerId: 'cus_12345');

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($user);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->processor->handleSubscriptionDeleted($subscription);

        $this->assertEquals('free', $user->getSubscriptionTier());
        $this->assertNull($user->getStripeSubscriptionId());
        $this->assertFalse($user->isActiveSubscription());
    }

    public function testHandlePaymentSucceeded_IsCallable(): void
    {
        $invoice = new \stdClass();

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->processor->handlePaymentSucceeded($invoice);

        $this->assertTrue(true);
    }

    public function testHandlePaymentFailed_IsCallable(): void
    {
        $invoice = new \stdClass();

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->processor->handlePaymentFailed($invoice);

        $this->assertTrue(true);
    }

    private function createSubscriptionMock(
        string $customerId = 'cus_12345',
        string $subscriptionId = 'sub_12345',
        string $priceId = 'price_pro_monthly',
        int $currentPeriodEnd = null
    ): \stdClass {
        $subscription = new \stdClass();
        $subscription->customer = $customerId;
        $subscription->id = $subscriptionId;
        $subscription->current_period_end = $currentPeriodEnd ?? time() + 2592000;

        $item = new \stdClass();
        $item->price = new \stdClass();
        $item->price->id = $priceId;

        $subscription->items = new \stdClass();
        $subscription->items->data = [$item];

        return $subscription;
    }
}
