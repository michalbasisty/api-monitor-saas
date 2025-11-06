<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\EndpointRepository;
use App\Repository\MonitoringResultRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private EndpointRepository $endpointRepository,
        private MonitoringResultRepository $resultRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/stats', name: 'app_admin_stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        $totalUsers = $this->userRepository->count([]);
        $totalEndpoints = $this->endpointRepository->count([]);
        $activeEndpoints = $this->endpointRepository->count(['is_active' => true]);

        $totalResults = $this->resultRepository->count([]);
        $recentResults = $this->resultRepository->count(['created_at' => new \DateTimeImmutable('-24 hours')]);

        $subscriptionStats = $this->userRepository->createQueryBuilder('u')
            ->select('u.subscription_tier, COUNT(u.id) as count')
            ->groupBy('u.subscription_tier')
            ->getQuery()
            ->getResult();

        return $this->json([
            'users' => [
                'total' => $totalUsers,
                'by_subscription' => array_column($subscriptionStats, 'count', 'subscription_tier')
            ],
            'endpoints' => [
                'total' => $totalEndpoints,
                'active' => $activeEndpoints
            ],
            'monitoring' => [
                'total_results' => $totalResults,
                'results_last_24h' => $recentResults
            ]
        ]);
    }

    #[Route('/users', name: 'app_admin_users', methods: ['GET'])]
    public function listUsers(): JsonResponse
    {
        $users = $this->userRepository->findAll();

        return $this->json([
            'total' => count($users),
            'users' => array_map(fn($u) => [
                'id' => $u->getId(),
                'email' => $u->getEmail(),
                'subscription_tier' => $u->getSubscriptionTier(),
                'is_verified' => $u->isVerified(),
                'is_active_subscription' => $u->isActiveSubscription(),
                'created_at' => $u->getCreatedAt()->format('c'),
                'last_login_at' => $u->getLastLoginAt()?->format('c'),
            ], $users)
        ]);
    }

    #[Route('/users/{id}', name: 'app_admin_user_detail', methods: ['GET'])]
    public function userDetail(string $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['message' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $endpoints = $this->endpointRepository->findByUser($user);
        $totalResults = 0;

        foreach ($endpoints as $endpoint) {
            $totalResults += $this->resultRepository->count(['endpoint' => $endpoint]);
        }

        return $this->json([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'subscription_tier' => $user->getSubscriptionTier(),
                'is_verified' => $user->isVerified(),
                'is_active_subscription' => $user->isActiveSubscription(),
                'stripe_customer_id' => $user->getStripeCustomerId(),
                'company_id' => $user->getCompanyId(),
                'created_at' => $user->getCreatedAt()->format('c'),
                'last_login_at' => $user->getLastLoginAt()?->format('c'),
            ],
            'stats' => [
                'endpoints_count' => count($endpoints),
                'total_monitoring_results' => $totalResults
            ],
            'endpoints' => array_map(fn($e) => [
                'id' => $e->getId(),
                'url' => $e->getUrl(),
                'is_active' => $e->isActive(),
                'check_interval' => $e->getCheckInterval(),
                'created_at' => $e->getCreatedAt()->format('c'),
            ], $endpoints)
        ]);
    }
}
