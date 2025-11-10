<?php

namespace App\Modules\Ecommerce\Controller;

use App\Modules\Ecommerce\Entity\PaymentMetric;
use App\Modules\Ecommerce\Entity\Store;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/ecommerce/webhooks')]
class WebhookController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route('/payment', name: 'ecommerce_webhook_payment', methods: ['POST'])]
    public function payment(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // TODO: Verify webhook signature (Stripe, PayPal, Square, etc.)
        // This is a placeholder implementation

        if (!isset($data['store_id']) || !isset($data['transaction_id'])) {
            return $this->json(['error' => 'Missing required fields'], 400);
        }

        try {
            $store = $this->em->getRepository(Store::class)->find($data['store_id']);
            if (!$store) {
                return $this->json(['error' => 'Store not found'], 404);
            }

            // Find or create payment metric
            $metric = $this->em->getRepository(PaymentMetric::class)
                ->createQueryBuilder('m')
                ->where('m.transactionId = :transactionId')
                ->setParameter('transactionId', $data['transaction_id'])
                ->getQuery()
                ->getOneOrNullResult();

            if ($metric) {
                $metric->setWebhookReceived(true);
                $metric->setWebhookTimestamp(new \DateTime());
                $metric->setStatus($data['status'] ?? $metric->getStatus());

                $this->em->flush();
            }

            return $this->json([
                'success' => true,
                'transaction_id' => $data['transaction_id'],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
