<?php

namespace App\Modules\Ecommerce\Controller;

use App\Entity\User;
use App\Modules\Ecommerce\Entity\EcommerceAlert;
use App\Modules\Ecommerce\Entity\Store;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/ecommerce')]
#[IsGranted('ROLE_USER')]
class AlertController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    private function getStoreForUser(string $storeId): ?Store
    {
        /** @var User $user */
        $user = $this->getUser();
        $store = $this->em->getRepository(Store::class)->find($storeId);

        if (!$store || $store->getUser() !== $user) {
            return null;
        }

        return $store;
    }

    #[Route('/stores/{storeId}/alerts', name: 'ecommerce_alerts', methods: ['GET'])]
    public function list(string $storeId, Request $request): JsonResponse
    {
        $store = $this->getStoreForUser($storeId);
        if (!$store) {
            return $this->json(['error' => 'Store not found'], 404);
        }

        $resolved = $request->query->get('resolved', false);

        $query = $this->em->getRepository(EcommerceAlert::class)
            ->createQueryBuilder('a')
            ->where('a.store = :store')
            ->setParameter('store', $store);

        if ($resolved === 'true') {
            $query->andWhere('a.resolvedAt IS NOT NULL');
        } else {
            $query->andWhere('a.resolvedAt IS NULL');
        }

        $alerts = $query->orderBy('a.triggeredAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->json([
            'alerts' => array_map(fn(EcommerceAlert $a) => [
                'id' => (string) $a->getId(),
                'alert_type' => $a->getAlertType(),
                'severity' => $a->getSeverity(),
                'triggered_at' => $a->getTriggeredAt()?->format('c'),
                'metric_value' => $a->getMetricValue(),
                'threshold_value' => $a->getThresholdValue(),
                'description' => $a->getDescription(),
                'resolved_at' => $a->getResolvedAt()?->format('c'),
            ], $alerts),
        ]);
    }

    #[Route('/stores/{storeId}/alerts', name: 'ecommerce_alert_create', methods: ['POST'])]
    public function create(string $storeId, Request $request): JsonResponse
    {
        $store = $this->getStoreForUser($storeId);
        if (!$store) {
            return $this->json(['error' => 'Store not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['alert_type']) || !isset($data['severity'])) {
            return $this->json(['error' => 'Missing required fields'], 400);
        }

        try {
            $alert = new EcommerceAlert();
            $alert->setStore($store);
            $alert->setAlertType($data['alert_type']);
            $alert->setSeverity($data['severity']);
            $alert->setTriggeredAt(new \DateTime());
            $alert->setMetricValue($data['metric_value'] ?? null);
            $alert->setThresholdValue($data['threshold_value'] ?? null);
            $alert->setDescription($data['description'] ?? null);

            $this->em->persist($alert);
            $this->em->flush();

            return $this->json([
                'id' => (string) $alert->getId(),
                'alert_type' => $alert->getAlertType(),
                'severity' => $alert->getSeverity(),
                'triggered_at' => $alert->getTriggeredAt()?->format('c'),
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
