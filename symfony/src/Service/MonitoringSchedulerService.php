<?php

namespace App\Service;

use App\Entity\Endpoint;
use App\Repository\EndpointRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MonitoringSchedulerService
{
    public function __construct(
        private EndpointRepository $endpointRepository,
        private HttpClientInterface $httpClient,
        private string $goApiUrl = 'http://go-api:8080'
    ) {}

    public function triggerMonitoringForEndpoint(Endpoint $endpoint): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->goApiUrl . '/monitor', [
                'query' => ['endpoint_id' => $endpoint->getId()]
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function triggerMonitoringForAllActiveEndpoints(): int
    {
        $activeEndpoints = $this->endpointRepository->findBy(['is_active' => true]);
        $successCount = 0;

        foreach ($activeEndpoints as $endpoint) {
            if ($this->triggerMonitoringForEndpoint($endpoint)) {
                $successCount++;
            }
        }

        return $successCount;
    }

    public function getEndpointsDueForMonitoring(): array
    {
        // Get endpoints that haven't been checked in their check_interval
        return $this->endpointRepository->createQueryBuilder('e')
            ->where('e.is_active = :active')
            ->andWhere('e.last_checked_at IS NULL OR e.last_checked_at < :threshold')
            ->setParameter('active', true)
            ->setParameter('threshold', new \DateTimeImmutable('-1 minute')) // Simplified for demo
            ->getQuery()
            ->getResult();
    }
}
