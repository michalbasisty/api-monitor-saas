<?php

namespace App\Service\Stripe;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class StripeWebhookProcessor
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function handleSubscriptionCreated($subscription): void
    {
        $user = $this->findUserByCustomerId($subscription->customer);
        if (!$user) {
            return;
        }

        $tier = $this->getTierFromPriceId($subscription->items->data[0]->price->id);
        $user->setSubscriptionTier($tier);
        $user->setStripeSubscriptionId($subscription->id);
        $user->setIsActiveSubscription(true);
        $user->setSubscriptionExpiresAt(
            \DateTimeImmutable::createFromFormat('U', $subscription->current_period_end)
        );

        $this->entityManager->flush();
    }

    public function handleSubscriptionUpdated($subscription): void
    {
        $this->handleSubscriptionCreated($subscription);
    }

    public function handleSubscriptionDeleted($subscription): void
    {
        $user = $this->findUserByCustomerId($subscription->customer);
        if (!$user) {
            return;
        }

        $user->setSubscriptionTier('free');
        $user->setStripeSubscriptionId(null);
        $user->setIsActiveSubscription(false);
        $user->setSubscriptionExpiresAt(null);

        $this->entityManager->flush();
    }

    public function handlePaymentSucceeded($invoice): void
    {
        // Mark payment as successful, update user status
    }

    public function handlePaymentFailed($invoice): void
    {
        // Handle failed payment, maybe suspend account
    }

    private function findUserByCustomerId(string $customerId): ?User
    {
        return $this->entityManager->getRepository(User::class)
            ->findOneBy(['stripe_customer_id' => $customerId]);
    }

    private function getTierFromPriceId(string $priceId): string
    {
        $plans = [
            'free' => null,
            'pro' => 'price_pro_monthly',
            'enterprise' => 'price_enterprise_monthly',
        ];

        foreach ($plans as $tier => $price) {
            if ($price === $priceId) {
                return $tier;
            }
        }

        return 'free';
    }
}
