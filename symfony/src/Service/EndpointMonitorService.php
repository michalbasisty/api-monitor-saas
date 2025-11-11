<?php

namespace App\Service;

use App\Entity\Endpoint;
use App\Entity\MonitoringResult;
use App\Repository\EndpointRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class EndpointMonitorService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private EndpointRepository $endpointRepository,
        private LoggerInterface $logger,
        private ?AlertEvaluationService $alertEvaluationService = null
    ) {}

    public function checkEndpoint(Endpoint $endpoint): MonitoringResult
    {
        $result = new MonitoringResult();
        $result->setEndpointId($endpoint->getId());
        $result->setCheckedAt(new \DateTimeImmutable());

        try {
            $result = $this->performHttpCheck($endpoint, $result);
            $this->logSuccess($endpoint, $result);
        } catch (ExceptionInterface $e) {
            $result = $this->handleHttpException($e, $endpoint, $result);
        } catch (\Throwable $e) {
            $result = $this->handleUnexpectedException($e, $endpoint, $result);
        }

        $this->persistResult($result);
        $this->evaluateAlerts($endpoint, $result);

        return $result;
    }

    private function performHttpCheck(Endpoint $endpoint, MonitoringResult $result): MonitoringResult
    {
        $startTime = microtime(true);
        $options = $this->buildHttpOptions($endpoint);

        $response = $this->httpClient->request('GET', $endpoint->getUrl(), $options);

        $statusCode = $response->getStatusCode();
        $responseTime = $this->calculateResponseTime($startTime);

        $result->setStatusCode($statusCode);
        $result->setResponseTime($responseTime);

        return $result;
    }

    private function buildHttpOptions(Endpoint $endpoint): array
    {
        $options = [
            'timeout' => $endpoint->getTimeout() / 1000,
            'max_redirects' => 5,
        ];

        if ($endpoint->getHeaders()) {
            $options['headers'] = $endpoint->getHeaders();
        }

        return $options;
    }

    private function calculateResponseTime(float $startTime): int
    {
        $endTime = microtime(true);
        return (int) (($endTime - $startTime) * 1000);
    }

    private function handleHttpException(ExceptionInterface $e, Endpoint $endpoint, MonitoringResult $result): MonitoringResult
    {
        $result->setErrorMessage($this->getErrorMessage($e));
        $this->logger->error('Endpoint check failed', [
            'endpoint_id' => $endpoint->getId(),
            'url' => $endpoint->getUrl(),
            'error' => $e->getMessage()
        ]);
        return $result;
    }

    private function handleUnexpectedException(\Throwable $e, Endpoint $endpoint, MonitoringResult $result): MonitoringResult
    {
        $result->setErrorMessage($e->getMessage());
        $this->logger->error('Unexpected error during endpoint check', [
            'endpoint_id' => $endpoint->getId(),
            'url' => $endpoint->getUrl(),
            'error' => $e->getMessage()
        ]);
        return $result;
    }

    private function logSuccess(Endpoint $endpoint, MonitoringResult $result): void
    {
        $this->logger->info('Endpoint check successful', [
            'endpoint_id' => $endpoint->getId(),
            'url' => $endpoint->getUrl(),
            'status_code' => $result->getStatusCode(),
            'response_time' => $result->getResponseTime()
        ]);
    }

    private function persistResult(MonitoringResult $result): void
    {
        $this->entityManager->persist($result);
        $this->entityManager->flush();
    }

    private function evaluateAlerts(Endpoint $endpoint, MonitoringResult $result): void
    {
        if ($this->alertEvaluationService) {
            $this->alertEvaluationService->evaluateAlertsForEndpoint($endpoint, $result);
        }
    }

    public function checkAllActiveEndpoints(): array
    {
        $endpoints = $this->endpointRepository->findActiveEndpoints();
        $results = [];

        foreach ($endpoints as $endpoint) {
            $results[] = $this->checkEndpoint($endpoint);
        }

        return $results;
    }

    public function checkEndpointsDueForCheck(): array
    {
        $endpoints = $this->endpointRepository->findActiveEndpoints();
        $results = [];
        $now = new \DateTimeImmutable();

        foreach ($endpoints as $endpoint) {
            if ($this->isDueForCheck($endpoint, $now)) {
                $results[] = $this->checkEndpoint($endpoint);
            }
        }

        return $results;
    }

    private function isDueForCheck(Endpoint $endpoint, \DateTimeImmutable $now): bool
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('mr')
            ->from(MonitoringResult::class, 'mr')
            ->where('mr.endpoint_id = :endpointId')
            ->setParameter('endpointId', $endpoint->getId())
            ->orderBy('mr.checked_at', 'DESC')
            ->setMaxResults(1);

        $lastResult = $qb->getQuery()->getOneOrNullResult();

        if (!$lastResult) {
            return true;
        }

        $nextCheckTime = $lastResult->getCheckedAt()->modify("+{$endpoint->getCheckInterval()} seconds");
        
        return $now >= $nextCheckTime;
    }

    private function getErrorMessage(ExceptionInterface $exception): string
    {
        $message = get_class($exception);
        
        if (str_contains($message, 'TransportException')) {
            return 'Connection failed - could not reach the endpoint';
        }
        
        if (str_contains($message, 'TimeoutException')) {
            return 'Request timeout - endpoint did not respond in time';
        }
        
        if (str_contains($message, 'RedirectionException')) {
            return 'Too many redirects';
        }

        return $exception->getMessage();
    }
}
