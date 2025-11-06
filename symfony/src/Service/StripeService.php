<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;

class StripeService
{
    private StripeClient $stripe;
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        string $stripeSecretKey = 'sk_test_placeholder'
    ) {
        $this->stripe = new StripeClient($stripeSecretKey);
        $this->entityManager = $entityManager;
    }

    public function createCustomer(User $user): array
    {
        try {
            $customer = $this->stripe->customers->create([
                'email' => $user->getEmail(),
                'name' => $user->getEmail(), // Could be enhanced with user name
                'metadata' => [
                    'user_id' => $user->getId(),
                ],
            ]);

            $user->setStripeCustomerId($customer->id);
            $this->entityManager->flush();

            return [
                'customer_id' => $customer->id,
                'success' => true,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

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

    public function updateUserSubscription(User $user, string $subscriptionId): void
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
        // Define the available subscription plans
        return [
            'free' => [
                'name' => 'Free',
                'price_id' => null, // No Stripe price for free tier
                'limits' => [
                    'endpoints' => 5,
                    'monitors_per_day' => 100,
                    'alerts' => 3,
                ],
            ],
            'pro' => [
                'name' => 'Pro',
                'price_id' => 'price_pro_monthly', // This would be created in Stripe dashboard
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

    private function getTierFromPriceId(string $priceId): string
    {
        $plans = $this->getSubscriptionPlans();
        foreach ($plans as $tier => $plan) {
            if ($plan['price_id'] === $priceId) {
                return $tier;
            }
        }
        return 'free';
    }

    public function handleWebhook(array $payload, string $signature): array
    {
        try {
            // Verify webhook signature
            $event = \Stripe\Webhook::constructEvent(
                json_encode($payload),
                $signature,
                $_ENV['STRIPE_WEBHOOK_SECRET'] ?? ''
            );

            // Handle different event types
            switch ($event->type) {
                case 'customer.subscription.created':
                    // Handle subscription creation
                    $subscription = $event->data->object;
                    $this->handleSubscriptionCreated($subscription);
                    break;

                case 'customer.subscription.updated':
                    // Handle subscription updates
                    $subscription = $event->data->object;
                    $this->handleSubscriptionUpdated($subscription);
                    break;

                case 'customer.subscription.deleted':
                    // Handle subscription cancellation
                    $subscription = $event->data->object;
                    $this->handleSubscriptionDeleted($subscription);
                    break;

                case 'invoice.payment_succeeded':
                    // Handle successful payment
                    $invoice = $event->data->object;
                    $this->handlePaymentSucceeded($invoice);
                    break;

                case 'invoice.payment_failed':
                    // Handle failed payment
                    $invoice = $event->data->object;
                    $this->handlePaymentFailed($invoice);
                    break;
            }

            return ['success' => true, 'event_type' => $event->type];
        } catch (\Exception $e) {
        return [
        'success' => false,
        'error' => $e->getMessage(),
        ];
        }
    }

    private function handleSubscriptionCreated($subscription): void
    {
        $customerId = $subscription->customer;
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['stripe_customer_id' => $customerId]);
        if (!$user) {
            return;
        }

        $priceId = $subscription->items->data[0]->price->id;
        $tier = $this->getTierFromPriceId($priceId);

        $user->setSubscriptionTier($tier);
        $user->setStripeSubscriptionId($subscription->id);
        $user->setIsActiveSubscription(true);
        $user->setSubscriptionExpiresAt(\DateTimeImmutable::createFromFormat('U', $subscription->current_period_end));

        $this->entityManager->flush();
    }

    private function handleSubscriptionUpdated($subscription): void
    {
        // Similar to created, update tier if changed
        $this->handleSubscriptionCreated($subscription);
    }

    private function handleSubscriptionDeleted($subscription): void
    {
        $customerId = $subscription->customer;
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['stripe_customer_id' => $customerId]);
        if (!$user) {
            return;
        }

        $user->setSubscriptionTier('free');
        $user->setStripeSubscriptionId(null);
        $user->setIsActiveSubscription(false);
        $user->setSubscriptionExpiresAt(null);

        $this->entityManager->flush();
    }

    private function handlePaymentSucceeded($invoice): void
    {
        // Mark payment as successful, update user status
    }

    private function handlePaymentFailed($invoice): void
    {
        // Handle failed payment, maybe suspend account
    }
}
