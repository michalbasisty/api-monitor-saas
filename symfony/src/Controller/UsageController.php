<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\UsageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
#[Route('/api/usage')]
class UsageController extends AbstractController
{
    public function __construct(
        private UsageService $usageService
    ) {}

    
    #[Route('/stats', name: 'app_usage_stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $stats = $this->usageService->getUsageStats($user);

        return $this->json($stats);
    }

    
    #[Route('/limits', name: 'app_usage_limits', methods: ['GET'])]
    public function getLimits(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $limits = $this->usageService->getLimitsForUser($user);

        return $this->json($limits);
    }
}
