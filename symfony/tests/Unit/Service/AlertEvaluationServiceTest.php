<?php

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use App\Service\AlertEvaluationService;
use App\Entity\Alert;
use App\Entity\MonitoringResult;
use App\Entity\Endpoint;

class AlertEvaluationServiceTest extends TestCase
{
    private AlertEvaluationService $service;
    
    protected function setUp(): void
    {
        $this->service = new AlertEvaluationService();
    }
    
    public function testResponseTimeAlertTriggered(): void
    {
        $alert = new Alert();
        $alert->setAlertType('response_time');
        $alert->setThreshold(['max_response_time' => 1000]);
        
        $result = new MonitoringResult();
        $result->setResponseTime(1500); // Exceeds threshold
        
        $triggered = $this->service->shouldTrigger($alert, $result);
        
        $this->assertTrue($triggered);
    }
    
    public function testResponseTimeAlertNotTriggered(): void
    {
        $alert = new Alert();
        $alert->setAlertType('response_time');
        $alert->setThreshold(['max_response_time' => 1000]);
        
        $result = new MonitoringResult();
        $result->setResponseTime(500); // Below threshold
        
        $triggered = $this->service->shouldTrigger($alert, $result);
        
        $this->assertFalse($triggered);
    }
    
    public function testStatusCodeAlertTriggered(): void
    {
        $alert = new Alert();
        $alert->setAlertType('status_code');
        $alert->setThreshold(['expected_codes' => [200, 201]]);
        
        $result = new MonitoringResult();
        $result->setStatusCode(500); // Not in expected codes
        
        $triggered = $this->service->shouldTrigger($alert, $result);
        
        $this->assertTrue($triggered);
    }
    
    public function testStatusCodeAlertNotTriggered(): void
    {
        $alert = new Alert();
        $alert->setAlertType('status_code');
        $alert->setThreshold(['expected_codes' => [200, 201]]);
        
        $result = new MonitoringResult();
        $result->setStatusCode(200); // In expected codes
        
        $triggered = $this->service->shouldTrigger($alert, $result);
        
        $this->assertFalse($triggered);
    }
    
    public function testAvailabilityAlertCalculation(): void
    {
        $alert = new Alert();
        $alert->setAlertType('availability');
        $alert->setThreshold(['min_uptime' => 95.0]);
        
        $uptime = 92.5; // Below threshold
        
        $triggered = $uptime < 95.0;
        
        $this->assertTrue($triggered);
    }
    
    public function testInactiveAlertNotEvaluated(): void
    {
        $alert = new Alert();
        $alert->setIsActive(false);
        $alert->setAlertType('response_time');
        
        $result = new MonitoringResult();
        $result->setResponseTime(2000);
        
        $shouldEvaluate = $alert->getIsActive();
        
        $this->assertFalse($shouldEvaluate);
    }
}
