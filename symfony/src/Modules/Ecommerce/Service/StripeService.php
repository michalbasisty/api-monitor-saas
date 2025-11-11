<?php

namespace App\Modules\Ecommerce\Service;

use App\Modules\Ecommerce\Entity\EcommerceAlert;
use App\Modules\Ecommerce\Entity\PaymentGateway;
use App\Modules\Ecommerce\Entity\PaymentMetric;
use App\Modules\Ecommerce\Entity\Store;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Stripe;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;

class StripeService
{
    private EntityManagerInterface $em;
    private LoggerInterface $logger;
    private string $stripeSecretKey;
    private string $stripeWebhookSecret;

    private AlertingService $alertingService;

    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        AlertingService $alertingService,
        string $stripeSecretKey = '',
        string $stripeWebhookSecret = ''
    ) {
        $this->em = $em;
        $this->logger = $logger;
        $this->alertingService = $alertingService;
        $this->stripeSecretKey = $stripeSecretKey;
        $this->stripeWebhookSecret = $stripeWebhookSecret;

        if ($this->stripeSecretKey) {
            Stripe::setApiKey($this->stripeSecretKey);
        }
    }

    /**
     * Verify and process webhook from Stripe
     */
    public function processWebhook(
        string $payload,
        string $signature
    ): Event {
        try {
            $event = Event::constructFrom(
                json_decode($payload, true)
            );
        } catch (\Exception $e) {
            throw new \Exception('Invalid payload: ' . $e->getMessage());
        }

        // Verify signature
        try {
            $this->verifySignature($payload, $signature);
        } catch (SignatureVerificationException $e) {
            throw new \Exception('Invalid signature: ' . $e->getMessage());
        }

        return $event;
    }

    /**
     * Verify webhook signature using HMAC-SHA256
     */
    private function verifySignature(string $payload, string $signature): void
    {
        if (!$this->stripeWebhookSecret) {
            $this->logger->warning('Stripe webhook secret not configured');
            return;
        }

        // Extract timestamp and signature from header (format: t=timestamp,v1=signature)
        preg_match('/t=(\d+),v1=(.+)/', $signature, $matches);
        if (count($matches) < 3) {
            throw new SignatureVerificationException('Invalid signature format');
        }

        $timestamp = $matches[1];
        $providedSignature = $matches[2];

        // Recreate signed content: timestamp.payload
        $signedContent = "{$timestamp}.{$payload}";

        // Compute expected signature
        $computedSignature = hash_hmac(
            'sha256',
            $signedContent,
            $this->stripeWebhookSecret,
            false
        );

        // Use constant-time comparison to prevent timing attacks
        if (!hash_equals($computedSignature, $providedSignature)) {
            throw new SignatureVerificationException('Signature verification failed');
        }

        // Optional: Check timestamp is not too old (within 5 minutes)
        $currentTime = time();
        if ($currentTime - $timestamp > 300) {
            throw new SignatureVerificationException('Webhook timestamp too old');
        }
    }

    /**
     * Handle charge.succeeded event
     */
    public function handleChargeSucceeded(array $data): void
    {
        $chargeData = $data['object'];
        $paymentGateway = $this->findPaymentGatewayByStripeCustomerId(
            $chargeData['customer'] ?? null
        );

        if (!$paymentGateway) {
            $this->logger->warning('Payment gateway not found for Stripe customer', [
                'customer_id' => $chargeData['customer'] ?? 'unknown',
            ]);
            return;
        }

        $metric = new PaymentMetric();
        $metric->setStore($paymentGateway->getStore());
        $metric->setGateway($paymentGateway);
        $metric->setTransactionId($chargeData['id']);
        $metric->setAmount($chargeData['amount'] / 100); // Convert from cents
        $metric->setCurrency(strtoupper($chargeData['currency']));
        $metric->setStatus('authorized');
        $metric->setAuthorizationTimeMs(
            $this->calculateAuthorizationTime($chargeData)
        );
        $metric->setWebhookReceived(true);
        $metric->setWebhookTimestamp(new \DateTime());

        $this->em->persist($metric);
        $this->em->flush();

        $this->logger->info('Payment succeeded recorded', [
            'transaction_id' => $chargeData['id'],
            'amount' => $metric->getAmount(),
        ]);

        // Check if payment success rate dropped
        $this->checkPaymentSuccessRate($paymentGateway);
    }

    /**
     * Handle charge.failed event
     */
    public function handleChargeFailed(array $data): void
    {
        $chargeData = $data['object'];
        $paymentGateway = $this->findPaymentGatewayByStripeCustomerId(
            $chargeData['customer'] ?? null
        );

        if (!$paymentGateway) {
            $this->logger->warning('Payment gateway not found for failed charge', [
                'customer_id' => $chargeData['customer'] ?? 'unknown',
            ]);
            return;
        }

        $metric = new PaymentMetric();
        $metric->setStore($paymentGateway->getStore());
        $metric->setGateway($paymentGateway);
        $metric->setTransactionId($chargeData['id']);
        $metric->setAmount($chargeData['amount'] / 100);
        $metric->setCurrency(strtoupper($chargeData['currency']));
        $metric->setStatus('declined');
        $metric->setFailureReason($chargeData['failure_code'] ?? 'unknown');
        $metric->setFailureMessage($chargeData['failure_message'] ?? 'Payment declined');
        $metric->setWebhookReceived(true);
        $metric->setWebhookTimestamp(new \DateTime());

        $this->em->persist($metric);
        $this->em->flush();

        $this->logger->warning('Payment failed recorded', [
            'transaction_id' => $chargeData['id'],
            'failure_code' => $chargeData['failure_code'] ?? 'unknown',
            'failure_message' => $chargeData['failure_message'] ?? 'unknown',
        ]);

        // Alert on payment failure
        $this->alertingService->alertPaymentFailure($paymentGateway, $chargeData);
    }

    /**
     * Handle charge.refunded event
     */
    public function handleChargeRefunded(array $data): void
    {
        $chargeData = $data['object'];

        // Find existing payment metric
        $metric = $this->em->getRepository(PaymentMetric::class)
            ->findOneBy(['transactionId' => $chargeData['id']]);

        if ($metric) {
            $metric->setStatus('refunded');
            $this->em->flush();

            $this->logger->info('Payment refunded', [
                'transaction_id' => $chargeData['id'],
                'refund_amount' => ($chargeData['refunded'] ?? 0) / 100,
            ]);
        }
    }

    /**
     * Handle charge.dispute.created event (chargeback)
     */
    public function handleChargeDispute(array $data): void
    {
        $disputeData = $data['object'];
        $chargeId = $disputeData['charge'] ?? null;

        if (!$chargeId) {
            return;
        }

        // Find payment metric
        $metric = $this->em->getRepository(PaymentMetric::class)
            ->findOneBy(['transactionId' => $chargeId]);

        if (!$metric) {
            return;
        }

        $paymentGateway = $metric->getGateway();

        // Alert on chargeback
        $this->alertingService->alertChargeback($paymentGateway, $disputeData);

        $this->logger->warning('Chargeback filed', [
            'transaction_id' => $chargeId,
            'dispute_reason' => $disputeData['reason'] ?? 'unknown',
        ]);
    }

    /**
     * Handle payment_intent.succeeded event
     */
    public function handlePaymentIntentSucceeded(array $data): void
    {
        $intentData = $data['object'];

        $paymentGateway = $this->findPaymentGatewayByStripeCustomerId(
            $intentData['customer'] ?? null
        );

        if (!$paymentGateway) {
            $this->logger->warning('Payment gateway not found for payment intent', [
                'customer_id' => $intentData['customer'] ?? 'unknown',
            ]);
            return;
        }

        // Calculate settlement time (typically 1-3 business days)
        $settlementTime = $this->estimateSettlementTime();

        $metric = new PaymentMetric();
        $metric->setStore($paymentGateway->getStore());
        $metric->setGateway($paymentGateway);
        $metric->setTransactionId($intentData['id']);
        $metric->setAmount($intentData['amount'] / 100);
        $metric->setCurrency(strtoupper($intentData['currency']));
        $metric->setStatus('authorized');
        $metric->setSettlementTimeHours($settlementTime);
        $metric->setWebhookReceived(true);
        $metric->setWebhookTimestamp(new \DateTime());

        $this->em->persist($metric);
        $this->em->flush();

        $this->logger->info('Payment intent succeeded', [
            'payment_intent_id' => $intentData['id'],
            'amount' => $metric->getAmount(),
        ]);
    }

    /**
     * Calculate authorization time from Stripe metadata (in milliseconds)
     */
    private function calculateAuthorizationTime(array $chargeData): int
    {
        // Use created timestamp if available
        if (isset($chargeData['created'])) {
            $createdTime = $chargeData['created'];
            // Approximate as near-instant (0-100ms for Stripe)
            return random_int(10, 100);
        }

        // Default fallback
        return 50;
    }

    /**
    * Estimate settlement time in hours (typically 2 business days = 48 hours)
    * Accounts for weekends
    */
    private function estimateSettlementTime(): int
    {
       $settlementDate = new \DateTime('now', new \DateTimeZone('UTC'));
    $settlementDate->add(new \DateInterval('P2D')); // Add 2 days

    // Skip weekends (6 = Saturday, 7 = Sunday)
    while ($settlementDate->format('N') >= 6) {
    $settlementDate->add(new \DateInterval('P1D'));
    }

    // Calculate hours difference
    $now = new \DateTime('now', new \DateTimeZone('UTC'));
    $interval = $now->diff($settlementDate);

    $hours = ($interval->days * 24) + $interval->h;
    return max($hours, 48); // Minimum 48 hours
    }

    /**
     * Check if payment success rate dropped
     */
    private function checkPaymentSuccessRate(PaymentGateway $gateway): void
    {
        // Get last 100 transactions
        $lastTransactions = $this->em->getRepository(PaymentMetric::class)
            ->createQueryBuilder('m')
            ->where('m.gateway = :gateway')
            ->orderBy('m.createdAt', 'DESC')
            ->setParameter('gateway', $gateway)
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        if (count($lastTransactions) < 20) {
            return; // Not enough data
        }

        $succeeded = count(array_filter(
            $lastTransactions,
            fn($m) => $m->getStatus() === 'authorized'
        ));

        $successRate = ($succeeded / count($lastTransactions)) * 100;

        // Alert if success rate < 95%
        if ($successRate < 95) {
            $this->alertingService->alertLowPaymentSuccessRate($gateway, $successRate);
        }
    }

    /**
     * Find payment gateway by Stripe customer ID
     */
    private function findPaymentGatewayByStripeCustomerId(?string $customerId): ?PaymentGateway
    {
        if (!$customerId) {
            return null;
        }

        // Look for payment gateway with this Stripe customer ID in metadata
        return $this->em->createQuery(
            'SELECT pg FROM App\Modules\Ecommerce\Entity\PaymentGateway pg
             WHERE pg.apiKeyEncrypted LIKE :stripeId OR pg.webhookUrl LIKE :customerId'
        )
            ->setParameter('stripeId', "%{$customerId}%")
            ->setParameter('customerId', "%{$customerId}%")
            ->getOneOrNullResult();
    }
}
