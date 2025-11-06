<?php

namespace App\Controller;

use App\Service\MetricsPublisher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    #[Route('/api/test/metrics', name: 'test_metrics', methods: ['GET'])]
    public function testMetrics(MetricsPublisher $metricsPublisher): JsonResponse
    {
        $startTime = microtime(true);
        
        // Simulate some work
        usleep(random_int(100000, 500000)); // Random delay between 100-500ms
        
        $response = new JsonResponse(['message' => 'Test metrics generated']);
        
        // Publish metric
        $metricsPublisher->publishMetric(
            $this->container->get('request_stack')->getCurrentRequest(),
            $response,
            $startTime
        );
        
        return $response;
    }
}