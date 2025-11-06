<?php

namespace App\Controller;

use App\Entity\Alert;
use App\Entity\User;
use App\Exception\ApiException;
use App\Repository\AlertRepository;
use App\Repository\EndpointRepository;
use App\Service\NotificationService;
use App\Service\UsageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/alerts')]
class AlertController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AlertRepository $alertRepository,
        private EndpointRepository $endpointRepository,
        private ValidatorInterface $validator,
        private UsageService $usageService,
        private NotificationService $notificationService
    ) {}

    #[Route('', name: 'app_alert_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $alerts = $this->alertRepository->findByUser($user);

        return $this->json([
            'total' => count($alerts),
            'alerts' => array_map(fn($a) => $this->serializeAlert($a), $alerts)
        ]);
    }

    #[Route('', name: 'app_alert_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        // Check usage limits
        if (!$this->usageService->checkAlertLimit($user)) {
            $limits = $this->usageService->getLimitsForUser($user);
            throw new ApiException(
                'Alert limit exceeded. Your plan allows ' . $limits['alerts'] . ' alerts.',
                Response::HTTP_FORBIDDEN
            );
        }

        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['endpoint_id'])) {
            return $this->json(['message' => 'Invalid request data'], Response::HTTP_BAD_REQUEST);
        }

        $endpoint = $this->endpointRepository->findByUserAndId($user, $data['endpoint_id']);
        if (!$endpoint) {
            return $this->json(['message' => 'Endpoint not found'], Response::HTTP_NOT_FOUND);
        }

        $alert = new Alert();
        $alert->setUserId($user->getId());
        $alert->setEndpointId($endpoint->getId());

        if (isset($data['alert_type'])) {
            $alert->setAlertType($data['alert_type']);
        }

        if (isset($data['threshold'])) {
            $alert->setThreshold($data['threshold']);
        }

        if (isset($data['is_active'])) {
            $alert->setIsActive((bool) $data['is_active']);
        }

        if (isset($data['notification_channels']) && is_array($data['notification_channels'])) {
            $alert->setNotificationChannels($data['notification_channels']);
        }

        $errors = $this->validator->validate($alert);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json([
                'message' => 'Validation failed',
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($alert);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Alert created successfully',
            'alert' => $this->serializeAlert($alert)
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'app_alert_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $alert = $this->alertRepository->findByUserAndId($user, $id);

        if (!$alert) {
            return $this->json(['message' => 'Alert not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeAlert($alert));
    }

    #[Route('/{id}', name: 'app_alert_update', methods: ['PUT', 'PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $alert = $this->alertRepository->findByUserAndId($user, $id);

        if (!$alert) {
            return $this->json(['message' => 'Alert not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Invalid request data'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['alert_type'])) {
            $alert->setAlertType($data['alert_type']);
        }

        if (isset($data['threshold'])) {
            $alert->setThreshold($data['threshold']);
        }

        if (isset($data['is_active'])) {
            $alert->setIsActive((bool) $data['is_active']);
        }

        if (isset($data['notification_channels']) && is_array($data['notification_channels'])) {
            $alert->setNotificationChannels($data['notification_channels']);
        }

        $alert->setUpdatedAt(new \DateTimeImmutable());

        $errors = $this->validator->validate($alert);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json([
                'message' => 'Validation failed',
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json([
            'message' => 'Alert updated successfully',
            'alert' => $this->serializeAlert($alert)
        ]);
    }

    #[Route('/{id}', name: 'app_alert_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $alert = $this->alertRepository->findByUserAndId($user, $id);

        if (!$alert) {
            return $this->json(['message' => 'Alert not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($alert);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Alert deleted successfully'
        ]);
    }

    #[Route('/endpoint/{endpointId}', name: 'app_alert_by_endpoint', methods: ['GET'])]
    public function byEndpoint(string $endpointId): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $endpoint = $this->endpointRepository->findByUserAndId($user, $endpointId);

        if (!$endpoint) {
            return $this->json(['message' => 'Endpoint not found'], Response::HTTP_NOT_FOUND);
        }

        $alerts = $this->alertRepository->findByEndpoint($endpoint);

        return $this->json([
            'endpoint_id' => $endpoint->getId(),
            'total' => count($alerts),
            'alerts' => array_map(fn($a) => $this->serializeAlert($a), $alerts)
        ]);
    }

    #[Route('/trigger', name: 'app_alert_trigger', methods: ['POST'])]
    public function trigger(Request $request): JsonResponse
    {
        // This endpoint is called by Go API when alert is triggered
        // No auth required, as it's internal

        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['alert_id']) || !isset($data['message'])) {
            return $this->json(['message' => 'Invalid request data'], Response::HTTP_BAD_REQUEST);
        }

        $alert = $this->alertRepository->find($data['alert_id']);

        if (!$alert) {
            return $this->json(['message' => 'Alert not found'], Response::HTTP_NOT_FOUND);
        }

        $this->notificationService->sendAlertNotification($alert, $data['message']);

        return $this->json(['message' => 'Notification sent']);
    }

    private function serializeAlert(Alert $alert): array
    {
        return [
            'id' => $alert->getId(),
            'endpoint_id' => $alert->getEndpointId(),
            'alert_type' => $alert->getAlertType(),
            'threshold' => $alert->getThreshold(),
            'is_active' => $alert->isActive(),
            'notification_channels' => $alert->getNotificationChannels(),
            'last_triggered_at' => $alert->getLastTriggeredAt()?->format('c'),
            'created_at' => $alert->getCreatedAt()->format('c'),
            'updated_at' => $alert->getUpdatedAt()?->format('c')
        ];
    }
}
