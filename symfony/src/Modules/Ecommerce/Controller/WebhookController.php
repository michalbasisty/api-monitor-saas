<?php

namespace App\Modules\Ecommerce\Controller;

use App\Modules\Ecommerce\Service\StripeService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/ecommerce/webhooks')]
class WebhookController extends AbstractController
{
    public function __construct(
        private StripeService $stripeService,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/stripe', name: 'ecommerce_webhook_stripe', methods: ['POST'])]
    public function stripe(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->headers->get('Stripe-Signature');

        if (!$signature) {
            $this->logger->error('Stripe webhook received without signature');
            return new Response('Unauthorized', Response::HTTP_401_UNAUTHORIZED);
        }

        try {
            // Verify and parse webhook
            $event = $this->stripeService->processWebhook($payload, $signature);

            // Handle different event types
            match ($event->type) {
                'charge.succeeded' => $this->stripeService->handleChargeSucceeded($event->data->toArray()),
                'charge.failed' => $this->stripeService->handleChargeFailed($event->data->toArray()),
                'charge.refunded' => $this->stripeService->handleChargeRefunded($event->data->toArray()),
                'charge.dispute.created' => $this->stripeService->handleChargeDispute($event->data->toArray()),
                'payment_intent.succeeded' => $this->stripeService->handlePaymentIntentSucceeded($event->data->toArray()),
                default => null,
            };

            $this->logger->info('Stripe webhook processed', [
                'event_type' => $event->type,
                'event_id' => $event->id,
            ]);

            return new Response('Webhook processed', Response::HTTP_200_OK);

        } catch (\Exception $e) {
            $this->logger->error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'event_type' => $event->type ?? 'unknown',
            ]);

            // Always return 200 to prevent Stripe from retrying
            // But log the error for investigation
            return new Response('Error logged', Response::HTTP_200_OK);
        }
    }

    #[Route('/paypal', name: 'ecommerce_webhook_paypal', methods: ['POST'])]
    public function paypal(Request $request): Response
    {
        // TODO: Implement PayPal webhook handling
        $this->logger->info('PayPal webhook received (not yet implemented)');
        return new Response('OK', Response::HTTP_200_OK);
    }

    #[Route('/square', name: 'ecommerce_webhook_square', methods: ['POST'])]
    public function square(Request $request): Response
    {
        // TODO: Implement Square webhook handling
        $this->logger->info('Square webhook received (not yet implemented)');
        return new Response('OK', Response::HTTP_200_OK);
    }
}
