<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class StripeService
{
    private StripeClient $stripe;
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        #[Autowire(env: 'STRIPE_SECRET_KEY')]
        string $stripeSecretKey
    ) {
        if (empty($stripeSecretKey)) {
            throw new \InvalidArgumentException('STRIPE_SECRET_KEY environment variable is not set');
        }
        
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
        try {
            $prices = $this->stripe->prices->all([
                'active' => true,
                'type' => 'recurring'
            ]);

            $plans = [];
            foreach ($prices->data as $price) {
                $plans[] = [
                    'id' => $price->id,
                    'name' => $price->nickname ?: $price->id,
                    'price' => $price->unit_amount / 100, // Convert cents to dollars
                    'currency' => strtoupper($price->currency),
                    'interval' => $price->recurring->interval,
                    'interval_count' => $price->recurring->interval_count,
                ];
            }

            return $plans;
        } catch (\Exception $e) {
            // Log error and return empty array for now
            error_log('Failed to fetch Stripe plans: ' . $e->getMessage());
            return [];
        }
    }

    public function handleWebhook(array $payload, string $signature): bool
    {
        $webhookSecret = getenv('STRIPE_WEBHOOK_SECRET');
        if (!$webhookSecret) {
            throw new \RuntimeException('STRIPE_WEBHOOK_SECRET not configured');
        }

        try {
            $event = \Stripe\Webhook::constructEvent(
                json_encode($payload),
                $signature,
                $webhookSecret
            );

            switch ($event->type) {
                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                case 'customer.subscription.deleted':
                    $this->handleSubscriptionEvent($event->data->object);
                    break;
                case 'invoice.payment_succeeded':
                    $this->handlePaymentSucceeded($event->data->object);
                    break;
                case 'invoice.payment_failed':
                    $this->handlePaymentFailed($event->data->object);
                    break;
                default:
                    // Log unhandled event types for debugging
                    error_log('Unhandled Stripe webhook event: ' . $event->type);
            }

            return true;
        } catch (\Exception $e) {
            error_log('Stripe webhook processing failed: ' . $e->getMessage());
            return false;
        }
    }

    private function handleSubscriptionEvent(\Stripe\Subscription $subscription): void
    {
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['stripeCustomerId' => $subscription->customer]);

        if ($user) {
            $user->setStripeSubscriptionId($subscription->id);
            if ($subscription->status === 'canceled') {
                $user->setStripeSubscriptionId(null);
            }
            $this->entityManager->flush();
        }
    }

    private function handlePaymentSucceeded(\Stripe\Invoice $invoice): void
    {
        // TODO: Update payment metrics for successful charge
        error_log('Payment succeeded for invoice: ' . $invoice->id);
    }

    private function handlePaymentFailed(\Stripe\Invoice $invoice): void
    {
        // TODO: Update payment metrics for failed charge
        error_log('Payment failed for invoice: ' . $invoice->id);
    }
}
