<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\EndpointRepository;
use App\Repository\MonitoringResultRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/monitoring')]
class MonitoringController extends AbstractController
{
    public function __construct(
        private MonitoringResultRepository $resultRepository,
        private EndpointRepository $endpointRepository
    ) {}

    #[Route('/endpoints/{endpointId}/results', name: 'app_monitoring_results', methods: ['GET'])]
    public function getResults(string $endpointId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $endpoint = $this->endpointRepository->findByUserAndId($user, $endpointId);

        if (!$endpoint) {
            return $this->json(['message' => 'Endpoint not found'], Response::HTTP_NOT_FOUND);
        }

        $limit = (int) $request->query->get('limit', 100);
        $limit = min($limit, 1000);

        $results = $this->resultRepository->findByEndpoint($endpoint, $limit);

        return $this->json([
            'endpoint_id' => $endpoint->getId(),
            'total' => count($results),
            'results' => array_map(fn($r) => $this->serializeResult($r), $results)
        ]);
    }

    #[Route('/endpoints/{endpointId}/stats', name: 'app_monitoring_stats', methods: ['GET'])]
    public function getStats(string $endpointId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $endpoint = $this->endpointRepository->findByUserAndId($user, $endpointId);

        if (!$endpoint) {
            return $this->json(['message' => 'Endpoint not found'], Response::HTTP_NOT_FOUND);
        }

        $hours = (int) $request->query->get('hours', 24);
        $hours = min($hours, 168);

        $latestResult = $this->resultRepository->getLatestResult($endpoint);
        $avgResponseTime = $this->resultRepository->getAverageResponseTime($endpoint, $hours);
        $uptime = $this->resultRepository->getUptime($endpoint, $hours);

        return $this->json([
            'endpoint_id' => $endpoint->getId(),
            'period_hours' => $hours,
            'latest_check' => $latestResult ? [
                'status_code' => $latestResult->getStatusCode(),
                'response_time' => $latestResult->getResponseTime(),
                'is_successful' => $latestResult->isSuccessful(),
                'error_message' => $latestResult->getErrorMessage(),
                'checked_at' => $latestResult->getCheckedAt()->format('c')
            ] : null,
            'average_response_time' => $avgResponseTime ? round($avgResponseTime, 2) : null,
            'uptime_percentage' => round($uptime, 2)
        ]);
    }

    #[Route('/endpoints/{endpointId}/timeline', name: 'app_monitoring_timeline', methods: ['GET'])]
    public function getTimeline(string $endpointId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $endpoint = $this->endpointRepository->findByUserAndId($user, $endpointId);

        if (!$endpoint) {
            return $this->json(['message' => 'Endpoint not found'], Response::HTTP_NOT_FOUND);
        }

        $hours = (int) $request->query->get('hours', 24);
        $hours = min($hours, 168);

        $from = new \DateTimeImmutable("-{$hours} hours");
        $to = new \DateTimeImmutable();

        $results = $this->resultRepository->findByEndpointInTimeRange($endpoint, $from, $to);

        return $this->json([
            'endpoint_id' => $endpoint->getId(),
            'from' => $from->format('c'),
            'to' => $to->format('c'),
            'total' => count($results),
            'timeline' => array_map(fn($r) => [
                'checked_at' => $r->getCheckedAt()->format('c'),
                'status_code' => $r->getStatusCode(),
                'response_time' => $r->getResponseTime(),
                'is_successful' => $r->isSuccessful()
            ], $results)
        ]);
        }

        #[Route('/endpoints/{endpointId}/export', name: 'app_monitoring_export', methods: ['GET'])]
    public function exportResults(string $endpointId, Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $endpoint = $this->endpointRepository->findByUserAndId($user, $endpointId);

        if (!$endpoint) {
            return $this->json(['message' => 'Endpoint not found'], Response::HTTP_NOT_FOUND);
        }

        $format = $request->query->get('format', 'json');
        $limit = (int) $request->query->get('limit', 1000);
        $limit = min($limit, 10000);

        $results = $this->resultRepository->findByEndpoint($endpoint, $limit);

        if ($format === 'csv') {
            return $this->exportAsCsv($results, $endpoint);
        }

        // Default to JSON
        $data = [
            'endpoint' => [
                'id' => $endpoint->getId(),
                'url' => $endpoint->getUrl(),
                'exported_at' => (new \DateTimeImmutable())->format('c')
            ],
            'total' => count($results),
            'results' => array_map(fn($r) => $this->serializeResult($r), $results)
        ];

        return $this->json($data);
    }

    private function exportAsCsv(array $results, $endpoint): Response
    {
        $filename = 'endpoint_' . $endpoint->getId() . '_results_' . date('Y-m-d_H-i-s') . '.csv';

        $csv = "ID,Response Time (ms),Status Code,Error Message,Is Successful,Checked At
";

        foreach ($results as $result) {
            $csv .= sprintf(
                "%s,%d,%s,%s,%s,%s
",
                $result->getId(),
                $result->getResponseTime(),
                $result->getStatusCode() ?? '',
                str_replace(',', ';', $result->getErrorMessage() ?? ''),
                $result->isSuccessful() ? 'true' : 'false',
                $result->getCheckedAt()->format('Y-m-d H:i:s')
            );
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    private function serializeResult($result): array
    {
        return [
            'id' => $result->getId(),
            'response_time' => $result->getResponseTime(),
            'status_code' => $result->getStatusCode(),
            'error_message' => $result->getErrorMessage(),
            'is_successful' => $result->isSuccessful(),
            'checked_at' => $result->getCheckedAt()->format('c'),
            'created_at' => $result->getCreatedAt()->format('c')
        ];
    }
}
