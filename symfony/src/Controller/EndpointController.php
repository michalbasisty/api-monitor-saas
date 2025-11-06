<?php

namespace App\Controller;

use App\Entity\Endpoint;
use App\Entity\User;
use App\Exception\ApiException;
use App\Repository\EndpointRepository;
use App\Service\UsageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/endpoints')]
class EndpointController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EndpointRepository $endpointRepository,
        private ValidatorInterface $validator,
        private UsageService $usageService
    ) {}

    #[Route('', name: 'app_endpoint_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $endpoints = $this->endpointRepository->findByUser($user);

        return $this->json([
            'total' => count($endpoints),
            'endpoints' => array_map(fn($e) => $this->serializeEndpoint($e), $endpoints)
        ]);
    }

    #[Route('', name: 'app_endpoint_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        // Check usage limits
        if (!$this->usageService->checkEndpointLimit($user)) {
            $limits = $this->usageService->getLimitsForUser($user);
            throw new ApiException(
                'Endpoint limit exceeded. Your plan allows ' . $limits['endpoints'] . ' endpoints.',
                Response::HTTP_FORBIDDEN
            );
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Invalid request data'], Response::HTTP_BAD_REQUEST);
        }

        $endpoint = new Endpoint();
        $endpoint->setUserId($user->getId());
        
        if (isset($data['url'])) {
            $endpoint->setUrl($data['url']);
        }
        
        if (isset($data['check_interval'])) {
            $endpoint->setCheckInterval((int) $data['check_interval']);
        }
        
        if (isset($data['timeout'])) {
            $endpoint->setTimeout((int) $data['timeout']);
        }
        
        if (isset($data['headers']) && is_array($data['headers'])) {
            $endpoint->setHeaders($data['headers']);
        }
        
        if (isset($data['is_active'])) {
            $endpoint->setIsActive((bool) $data['is_active']);
        }

        $errors = $this->validator->validate($endpoint);
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

        $this->entityManager->persist($endpoint);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Endpoint created successfully',
            'endpoint' => $this->serializeEndpoint($endpoint)
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'app_endpoint_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $endpoint = $this->endpointRepository->findByUserAndId($user, $id);

        if (!$endpoint) {
            return $this->json(['message' => 'Endpoint not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeEndpoint($endpoint));
    }

    #[Route('/{id}', name: 'app_endpoint_update', methods: ['PUT', 'PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $endpoint = $this->endpointRepository->findByUserAndId($user, $id);

        if (!$endpoint) {
            return $this->json(['message' => 'Endpoint not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Invalid request data'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['url'])) {
            $endpoint->setUrl($data['url']);
        }
        
        if (isset($data['check_interval'])) {
            $endpoint->setCheckInterval((int) $data['check_interval']);
        }
        
        if (isset($data['timeout'])) {
            $endpoint->setTimeout((int) $data['timeout']);
        }
        
        if (isset($data['headers']) && is_array($data['headers'])) {
            $endpoint->setHeaders($data['headers']);
        }
        
        if (isset($data['is_active'])) {
            $endpoint->setIsActive((bool) $data['is_active']);
        }

        $endpoint->setUpdatedAt(new \DateTimeImmutable());

        $errors = $this->validator->validate($endpoint);
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
            'message' => 'Endpoint updated successfully',
            'endpoint' => $this->serializeEndpoint($endpoint)
        ]);
    }

    #[Route('/{id}', name: 'app_endpoint_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $endpoint = $this->endpointRepository->findByUserAndId($user, $id);

        if (!$endpoint) {
            return $this->json(['message' => 'Endpoint not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($endpoint);
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Endpoint deleted successfully'
        ]);
    }

    private function serializeEndpoint(Endpoint $endpoint): array
    {
        return [
            'id' => $endpoint->getId(),
            'url' => $endpoint->getUrl(),
            'check_interval' => $endpoint->getCheckInterval(),
            'timeout' => $endpoint->getTimeout(),
            'headers' => $endpoint->getHeaders(),
            'is_active' => $endpoint->isActive(),
            'created_at' => $endpoint->getCreatedAt()->format('c'),
            'updated_at' => $endpoint->getUpdatedAt()?->format('c')
        ];
    }
}
