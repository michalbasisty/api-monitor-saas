<?php

namespace App\Tests\Unit\Service\Metrics;

use App\Service\Metrics\MonitoringMetricsCollector;
use DateTime;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class MonitoringMetricsCollectorTest extends TestCase
{
    private MonitoringMetricsCollector $collector;
    private MockObject $connection;
    private MockObject $logger;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->collector = new MonitoringMetricsCollector(
            $this->connection,
            $this->logger
        );
    }

    public function testGetMonitoringMetrics_ReturnsAllMetrics(): void
    {
        $startTime = new DateTime('-1 day');
        $endTime = new DateTime();

        // Mock all the database calls
        $this->connection
            ->expects($this->atLeast(8))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(10, 8, 100, 95, 5, 45.5, 3, 1);

        $metrics = $this->collector->getMonitoringMetrics($startTime, $endTime);

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('endpoints_total', $metrics);
        $this->assertArrayHasKey('endpoints_active', $metrics);
        $this->assertArrayHasKey('checks_24h', $metrics);
        $this->assertArrayHasKey('successful_checks', $metrics);
        $this->assertArrayHasKey('failed_checks', $metrics);
        $this->assertArrayHasKey('avg_response_time', $metrics);
        $this->assertArrayHasKey('alerts_configured', $metrics);
        $this->assertArrayHasKey('alerts_triggered_24h', $metrics);

        $this->assertEquals(10, $metrics['endpoints_total']);
        $this->assertEquals(8, $metrics['endpoints_active']);
    }

    public function testGetMonitoringMetrics_WithNoData_ReturnsZeros(): void
    {
        $startTime = new DateTime('-1 day');

        $this->connection
            ->expects($this->atLeast(8))
            ->method('fetchOne')
            ->willReturn(0);

        $metrics = $this->collector->getMonitoringMetrics($startTime, new DateTime());

        $this->assertEquals(0, $metrics['endpoints_total']);
        $this->assertEquals(0, $metrics['checks_24h']);
    }
}
