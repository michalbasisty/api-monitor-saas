<?php

namespace App\Tests\Unit\Service\Metrics;

use App\Service\Metrics\SystemMetricsCollector;
use DateTime;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class SystemMetricsCollectorTest extends TestCase
{
    private SystemMetricsCollector $collector;
    private MockObject $connection;
    private MockObject $logger;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->collector = new SystemMetricsCollector(
            $this->connection,
            $this->logger
        );
    }

    public function testGetMetrics_ReturnsMetricsArray(): void
    {
        $startTime = new DateTime('-1 hour');
        $endTime = new DateTime();

        $statement = $this->createMock(\Doctrine\DBAL\Statement::class);
        $statement->expects($this->once())
            ->method('executeQuery')
            ->willReturn($this->createMock(\Doctrine\DBAL\Result::class));

        $this->connection
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $result->expects($this->atLeastOnce())
            ->method('fetchAssociative')
            ->willReturn(null);

        $statement->method('executeQuery')->willReturn($result);

        $metrics = $this->collector->getMetrics($startTime, $endTime);

        $this->assertIsArray($metrics);
    }

    public function testGetAggregates_ReturnsAggregateData(): void
    {
        $startTime = new DateTime('-1 day');
        $endTime = new DateTime();

        $aggregates = ['count' => 100, 'average' => 45.5];

        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $result->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn($aggregates);

        $statement = $this->createMock(\Doctrine\DBAL\Statement::class);
        $statement->expects($this->once())
            ->method('executeQuery')
            ->willReturn($result);

        $this->connection
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        $agg = $this->collector->getAggregates('cpu_usage', $startTime, $endTime);

        $this->assertEquals($aggregates, $agg);
    }

    public function testGetSystemHealth_ReturnsHealthMetrics(): void
    {
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $result->expects($this->atLeastOnce())
            ->method('fetchAssociative')
            ->willReturn(null);

        $statement = $this->createMock(\Doctrine\DBAL\Statement::class);
        $statement->expects($this->once())
            ->method('executeQuery')
            ->willReturn($result);

        $this->connection
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        $health = $this->collector->getSystemHealth();

        $this->assertIsArray($health);
    }

    public function testClearOldMetrics_DeletesOldData(): void
    {
        $statement = $this->createMock(\Doctrine\DBAL\Statement::class);
        $statement->expects($this->once())
            ->method('executeStatement')
            ->willReturn(100);

        $this->connection
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Deleted 100 old metrics');

        $deleted = $this->collector->clearOldMetrics(90);

        $this->assertEquals(100, $deleted);
    }
}
