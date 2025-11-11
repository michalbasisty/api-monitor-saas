<?php

namespace App\Service\Stripe;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;

class StripeSubscriptionManager
{
    public function __construct(
        private StripeClient $stripe,
        private EntityManagerInterface $entityManager
    ) {}

    public function createSubscription(string $customerId, string $priceId): array
    {
        try {
            $subscription = $this->stripe->subscriptions->create([
                'customer' => $customerId,
                'items' => [
                    [
                        'price' => $priceId,
                    ],
                ],
                'payment_behavior' => 'default_incomplete',
                'expand' => ['latest_invoice.payment_intent'],
            ]);

            return [
                'subscription_id' => $subscription->id,
                'client_secret' => $subscription->latest_invoice->payment_intent->client_secret,
                'success' => true,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function updateSubscription(User $user, string $subscriptionId): void
    {
        $user->setStripeSubscriptionId($subscriptionId);
        $this->entityManager->flush();
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        try {
            $subscription = $this->stripe->subscriptions->cancel($subscriptionId);

            return [
                'subscription_id' => $subscription->id,
                'status' => $subscription->status,
                'success' => true,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getSubscriptionPlans(): array
    {
        return [
            'free' => [
                'name' => 'Free',
                'price_id' => null,
                'limits' => [
                    'endpoints' => 5,
                    'monitors_per_day' => 100,
                    'alerts' => 3,
                ],
            ],
            'pro' => [
                'name' => 'Pro',
                'price_id' => 'price_pro_monthly',
                'limits' => [
                    'endpoints' => 50,
                    'monitors_per_day' => 1000,
                    'alerts' => 20,
                ],
            ],
            'enterprise' => [
                'name' => 'Enterprise',
                'price_id' => 'price_enterprise_monthly',
                'limits' => [
                    'endpoints' => 500,
                    'monitors_per_day' => 10000,
                    'alerts' => 100,
                ],
            ],
        ];
    }
}
