<?php

namespace App\Controller;

use App\Entity\User;
use App\Exception\ApiException;
use App\Service\StripeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/billing')]
class BillingController extends AbstractController
{
    public function __construct(
        private StripeService $stripeService
    ) {}

    #[Route('/plans', name: 'app_billing_plans', methods: ['GET'])]
    public function getPlans(): JsonResponse
    {
        $plans = $this->stripeService->getSubscriptionPlans();
        return $this->json(['plans' => $plans]);
    }

    #[Route('/subscription', name: 'app_billing_get_subscription', methods: ['GET'])]
    public function getSubscription(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'tier' => $user->getSubscriptionTier(),
            'is_active' => $user->isActiveSubscription(),
            'expires_at' => $user->getSubscriptionExpiresAt()?->format('c'),
            'stripe_customer_id' => $user->getStripeCustomerId(),
            'stripe_subscription_id' => $user->getStripeSubscriptionId(),
        ]);
    }

    #[Route('/create-customer', name: 'app_billing_create_customer', methods: ['POST'])]
    public function createCustomer(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $result = $this->stripeService->createCustomer($user);
        if (!$result['success']) {
            throw new ApiException('Failed to create customer: ' . $result['error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($result);
    }

    #[Route('/create-subscription', name: 'app_billing_create_subscription', methods: ['POST'])]
    public function createSubscription(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['price_id'])) {
            throw new ApiException('Price ID is required', Response::HTTP_BAD_REQUEST);
        }

        $customerId = $user->getStripeCustomerId();
        if (!$customerId) {
            throw new ApiException('Customer not found. Please create customer first.', Response::HTTP_BAD_REQUEST);
        }

        $result = $this->stripeService->createSubscription($customerId, $data['price_id']);

        if (!$result['success']) {
            throw new ApiException('Failed to create subscription: ' . $result['error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Update user with subscription ID
        $user->setStripeSubscriptionId($result['subscription_id']);
        $user->setIsActiveSubscription(true);
        $this->stripeService->updateUserSubscription($user, $result['subscription_id']);

        return $this->json($result);
    }

    #[Route('/cancel-subscription', name: 'app_billing_cancel_subscription', methods: ['POST'])]
    public function cancelSubscription(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $subscriptionId = $user->getStripeSubscriptionId();
        if (!$subscriptionId) {
            throw new ApiException('No active subscription found', Response::HTTP_BAD_REQUEST);
        }

        $result = $this->stripeService->cancelSubscription($subscriptionId);

        if (!$result['success']) {
            throw new ApiException('Failed to cancel subscription: ' . $result['error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($result);
    }

    #[Route('/webhook', name: 'app_billing_webhook', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $signature = $request->headers->get('stripe-signature');

        if (!$payload || !$signature) {
            return $this->json(['error' => 'Invalid webhook'], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->stripeService->handleWebhook($payload, $signature);

        if (!$result['success']) {
            return $this->json(['error' => $result['error']], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['status' => 'ok']);
    }
}
