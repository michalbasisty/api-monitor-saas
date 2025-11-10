<?php

namespace App\Modules\Ecommerce\Controller;

use App\Modules\Ecommerce\Service\StripeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

class WebhookController extends AbstractController
{
    public function __construct(
        private StripeService $stripeService,
        private LoggerInterface $logger
    ) {}

    public function stripe(Request $request): JsonResponse
    {
        try {
            $payload = $request->getContent();
            $signature = $request->headers->get('Stripe-Signature');

            if (!$signature) {
                return new JsonResponse(['error' => 'Missing Stripe signature'], Response::HTTP_UNAUTHORIZED);
            }

            $event = $this->stripeService->verifyWebhookSignature($payload, $signature);

            if (!$event) {
                return new JsonResponse(['error' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
            }

            $this->stripeService->handleWebhookEvent($event);

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('Stripe webhook processing failed', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Webhook processing failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
