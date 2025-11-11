<?php

namespace App\Service;

use App\Service\Stripe\StripeCustomerManager;
use App\Service\Stripe\StripeSubscriptionManager;
use App\Service\Stripe\StripeWebhookProcessor;
use App\Entity\User;

class StripeServiceRefactored
{
    public function __construct(
        private StripeCustomerManager $customerManager,
        private StripeSubscriptionManager $subscriptionManager,
        private StripeWebhookProcessor $webhookProcessor
    ) {}

    public function createCustomer(User $user): array
    {
        return $this->customerManager->createCustomer($user);
    }

    public function createSubscription(string $customerId, string $priceId): array
    {
        return $this->subscriptionManager->createSubscription($customerId, $priceId);
    }

    public function updateUserSubscription(User $user, string $subscriptionId): void
    {
        $this->subscriptionManager->updateSubscription($user, $subscriptionId);
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->subscriptionManager->cancelSubscription($subscriptionId);
    }

    public function getSubscriptionPlans(): array
    {
        return $this->subscriptionManager->getSubscriptionPlans();
    }

    public function handleWebhook(array $payload, string $signature): array
    {
        try {
            $event = \Stripe\Webhook::constructEvent(
                json_encode($payload),
                $signature,
                $_ENV['STRIPE_WEBHOOK_SECRET'] ?? ''
            );

            switch ($event->type) {
                case 'customer.subscription.created':
                    $this->webhookProcessor->handleSubscriptionCreated($event->data->object);
                    break;

                case 'customer.subscription.updated':
                    $this->webhookProcessor->handleSubscriptionUpdated($event->data->object);
                    break;

                case 'customer.subscription.deleted':
                    $this->webhookProcessor->handleSubscriptionDeleted($event->data->object);
                    break;

                case 'invoice.payment_succeeded':
                    $this->webhookProcessor->handlePaymentSucceeded($event->data->object);
                    break;

                case 'invoice.payment_failed':
                    $this->webhookProcessor->handlePaymentFailed($event->data->object);
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
}
