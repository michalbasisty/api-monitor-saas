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
        // TODO: Implement fetching subscription plans from Stripe
        return [];
    }

    public function handleWebhook(array $payload): void
    {
        // TODO: Implement webhook handling
    }
}
