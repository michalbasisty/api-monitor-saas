<?php

namespace App\Modules\Ecommerce\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class StripeService
{
    private string $webhookSecret;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        ?string $stripeWebhookSecret = null
    ) {
        $this->webhookSecret = $stripeWebhookSecret ?? $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';
    }

    public function verifyWebhookSignature(string $payload, string $signature): ?array
    {
        if (empty($this->webhookSecret)) {
            $this->logger->error('Stripe webhook secret not configured');
            return null;
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $this->webhookSecret);
            return $event->toArray();
        } catch (\Exception $e) {
            $this->logger->error('Failed to verify webhook signature', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function handleWebhookEvent(array $event): void
    {
        try {
            $type = $event['type'] ?? null;

            match ($type) {
                'charge.succeeded' => $this->handleChargeSucceeded($event),
                'charge.failed' => $this->handleChargeFailed($event),
                'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($event),
                'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($event),
                default => $this->logger->debug('Unhandled webhook event type', ['type' => $type]),
            };

            $this->logger->info('Webhook event processed', ['type' => $type]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle webhook event', ['error' => $e->getMessage()]);
        }
    }

    private function handleChargeSucceeded(array $event): void
    {
        $charge = $event['data']['object'] ?? [];
        $amount = $charge['amount'] ?? 0;
        $currency = $charge['currency'] ?? 'usd';

        // Update payment metrics for successful charge
        $this->logger->info('Charge succeeded', [
            'charge_id' => $charge['id'] ?? 'unknown',
            'amount' => $amount / 100, // Convert cents to dollars
            'currency' => $currency
        ]);
        // TODO: Persist to database for metrics
    }

    private function handleChargeFailed(array $event): void
    {
        $charge = $event['data']['object'] ?? [];

        // Update payment metrics for failed charge
        $this->logger->info('Charge failed', [
            'charge_id' => $charge['id'] ?? 'unknown',
            'failure_code' => $charge['failure_code'] ?? 'unknown'
        ]);
        // TODO: Persist to database for metrics
    }

    private function handlePaymentIntentSucceeded(array $event): void
    {
        $paymentIntent = $event['data']['object'] ?? [];
        $amount = $paymentIntent['amount'] ?? 0;

        // Update payment intent succeeded metrics
        $this->logger->info('Payment intent succeeded', [
            'payment_intent_id' => $paymentIntent['id'] ?? 'unknown',
            'amount' => $amount / 100
        ]);
        // TODO: Persist to database for metrics
    }

    private function handlePaymentIntentFailed(array $event): void
    {
        $paymentIntent = $event['data']['object'] ?? [];

        // Update payment intent failed metrics
        $this->logger->info('Payment intent failed', [
            'payment_intent_id' => $paymentIntent['id'] ?? 'unknown',
            'last_payment_error' => $paymentIntent['last_payment_error'] ?? []
        ]);
        // TODO: Persist to database for metrics
    }
}
