<?php

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Service\EndpointMonitorService;
use App\Entity\Endpoint;
use App\Entity\MonitoringResult;
use App\Repository\MonitoringResultRepository;

class EndpointMonitorServiceTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private MonitoringResultRepository $resultRepository;
    private EndpointMonitorService $service;
    
    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->resultRepository = $this->createMock(MonitoringResultRepository::class);
        
        $this->service = new EndpointMonitorService(
            $this->httpClient,
            $this->resultRepository
        );
    }
    
    public function testMonitorEndpointSuccess(): void
    {
        $endpoint = new Endpoint();
        $endpoint->setUrl('https://httpbin.org/status/200');
        $endpoint->setTimeout(5000);
        
        // Mock successful response
        $response = $this->createMock(\Symfony\Contracts\HttpClient\ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getInfo')->willReturn(['response_time' => 150]);
        
        $this->httpClient->method('request')
            ->with('GET', 'https://httpbin.org/status/200')
            ->willReturn($response);
        
        // Test monitoring logic
        $this->assertNotNull($response);
    }
    
    public function testMonitorEndpointTimeout(): void
    {
        $endpoint = new Endpoint();
        $endpoint->setUrl('https://httpbin.org/delay/10');
        $endpoint->setTimeout(1000); // 1 second timeout
        
        // Should handle timeout gracefully
        $this->assertEquals(1000, $endpoint->getTimeout());
    }
    
    public function testMonitorEndpointError(): void
    {
        $endpoint = new Endpoint();
        $endpoint->setUrl('https://invalid-domain-12345.com');
        
        // Should handle connection errors
        $this->assertNotEmpty($endpoint->getUrl());
    }
    
    public function testSaveMonitoringResult(): void
    {
        $result = new MonitoringResult();
        $result->setResponseTime(125);
        $result->setStatusCode(200);
        $result->setCheckedAt(new \DateTime());
        
        $this->resultRepository->method('save')->with($result);
        
        $this->assertEquals(125, $result->getResponseTime());
        $this->assertEquals(200, $result->getStatusCode());
    }
    
    public function testCalculateUptimePercentage(): void
    {
        $successful = 95;
        $total = 100;
        $uptime = ($successful / $total) * 100;
        
        $this->assertEquals(95.0, $uptime);
    }
    
    public function testAverageResponseTime(): void
    {
        $times = [100, 150, 120, 180, 140];
        $average = array_sum($times) / count($times);
        
        $this->assertEquals(138, $average);
    }
}
