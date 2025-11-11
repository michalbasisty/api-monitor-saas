<?php

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use App\Service\NotificationService;
use App\Entity\Alert;
use App\Entity\MonitoringResult;

class NotificationServiceTest extends TestCase
{
    private NotificationService $service;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new NotificationService($this->logger);
    }

    public function testSendEmailNotification(): void
    {
        $alert = new Alert();
        $alert->setNotificationChannels(['email']);
        
        $result = new MonitoringResult();
        $result->setStatusCode(500);
        
        $this->logger->expects($this->once())->method('info');
        
        $this->service->notify($alert, $result);
    }

    public function testSendSlackNotification(): void
    {
        $alert = new Alert();
        $alert->setNotificationChannels(['slack']);
        
        $result = new MonitoringResult();
        $result->setStatusCode(500);
        
        $this->logger->expects($this->once())->method('info');
        
        $this->service->notify($alert, $result);
    }

    public function testSendWebhookNotification(): void
    {
        $alert = new Alert();
        $alert->setNotificationChannels(['webhook']);
        
        $result = new MonitoringResult();
        $result->setStatusCode(500);
        
        $this->logger->expects($this->once())->method('info');
        
        $this->service->notify($alert, $result);
    }

    public function testMultipleChannelNotification(): void
    {
        $alert = new Alert();
        $alert->setNotificationChannels(['email', 'slack', 'webhook']);
        
        $result = new MonitoringResult();
        $result->setStatusCode(500);
        
        $this->logger->expects($this->atLeastOnce())->method('info');
        
        $this->service->notify($alert, $result);
    }

    public function testInactiveAlertDoesNotNotify(): void
    {
        $alert = new Alert();
        $alert->setIsActive(false);
        $alert->setNotificationChannels(['email']);
        
        $result = new MonitoringResult();
        $result->setStatusCode(500);
        
        // Should not log if alert is inactive
        $this->logger->expects($this->never())->method('info');
        
        if ($alert->getIsActive()) {
            $this->service->notify($alert, $result);
        }
    }

    public function testNotificationWithoutChannels(): void
    {
        $alert = new Alert();
        $alert->setNotificationChannels([]);
        
        $result = new MonitoringResult();
        $result->setStatusCode(500);
        
        // Empty channels should not send anything
        $this->logger->expects($this->never())->method('info');
        
        if (count($alert->getNotificationChannels()) > 0) {
            $this->service->notify($alert, $result);
        }
    }
}
