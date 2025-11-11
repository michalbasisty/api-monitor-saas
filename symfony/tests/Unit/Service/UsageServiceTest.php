<?php

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use App\Service\UsageService;
use App\Entity\User;
use App\Entity\Endpoint;
use App\Entity\Alert;
use App\Entity\MonitoringResult;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;

class UsageServiceTest extends TestCase
{
    private UsageService $service;
    private MockObject $entityManager;
    private MockObject $endpointRepository;
    private MockObject $alertRepository;
    private MockObject $monitoringResultRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->endpointRepository = $this->createMock(EntityRepository::class);
        $this->alertRepository = $this->createMock(EntityRepository::class);
        $this->monitoringResultRepository = $this->createMock(EntityRepository::class);

        $this->service = new UsageService($this->entityManager);
    }

    // ============================================
    // getLimitsForUser Tests
    // ============================================

    public function testGetLimitsForFreeTier(): void
    {
        $user = $this->createMockUser('free');

        $limits = $this->service->getLimitsForUser($user);

        $this->assertEquals(5, $limits['endpoints']);
        $this->assertEquals(100, $limits['monitors_per_day']);
        $this->assertEquals(3, $limits['alerts']);
    }

    public function testGetLimitsForProTier(): void
    {
        $user = $this->createMockUser('pro');

        $limits = $this->service->getLimitsForUser($user);

        $this->assertEquals(50, $limits['endpoints']);
        $this->assertEquals(1000, $limits['monitors_per_day']);
        $this->assertEquals(20, $limits['alerts']);
    }

    public function testGetLimitsForEnterpriseTier(): void
    {
        $user = $this->createMockUser('enterprise');

        $limits = $this->service->getLimitsForUser($user);

        $this->assertEquals(500, $limits['endpoints']);
        $this->assertEquals(10000, $limits['monitors_per_day']);
        $this->assertEquals(100, $limits['alerts']);
    }

    public function testGetLimitsForUnknownTierReturnsFreeTier(): void
    {
        $user = $this->createMockUser('unknown_tier');

        $limits = $this->service->getLimitsForUser($user);

        // Should default to free tier
        $this->assertEquals(5, $limits['endpoints']);
        $this->assertEquals(100, $limits['monitors_per_day']);
        $this->assertEquals(3, $limits['alerts']);
    }

    // ============================================
    // checkEndpointLimit Tests
    // ============================================

    public function testCheckEndpointLimitReturnsTrueWhenBelowLimit(): void
    {
        $user = $this->createMockUser('free');

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Endpoint::class)
            ->willReturn($this->endpointRepository);

        $this->endpointRepository
            ->expects($this->once())
            ->method('count')
            ->with(['user' => $user])
            ->willReturn(3); // 3 < 5

        $result = $this->service->checkEndpointLimit($user);

        $this->assertTrue($result);
    }

    public function testCheckEndpointLimitReturnsFalseWhenAtLimit(): void
    {
        $user = $this->createMockUser('free');

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Endpoint::class)
            ->willReturn($this->endpointRepository);

        $this->endpointRepository
            ->expects($this->once())
            ->method('count')
            ->with(['user' => $user])
            ->willReturn(5); // 5 is not < 5

        $result = $this->service->checkEndpointLimit($user);

        $this->assertFalse($result);
    }

    public function testCheckEndpointLimitReturnsFalseWhenOverLimit(): void
    {
        $user = $this->createMockUser('free');

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Endpoint::class)
            ->willReturn($this->endpointRepository);

        $this->endpointRepository
            ->expects($this->once())
            ->method('count')
            ->with(['user' => $user])
            ->willReturn(6); // 6 > 5

        $result = $this->service->checkEndpointLimit($user);

        $this->assertFalse($result);
    }

    public function testCheckEndpointLimitProTier(): void
    {
        $user = $this->createMockUser('pro');

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Endpoint::class)
            ->willReturn($this->endpointRepository);

        $this->endpointRepository
            ->expects($this->once())
            ->method('count')
            ->with(['user' => $user])
            ->willReturn(40); // 40 < 50

        $result = $this->service->checkEndpointLimit($user);

        $this->assertTrue($result);
    }

    // ============================================
    // checkAlertLimit Tests
    // ============================================

    public function testCheckAlertLimitReturnsTrueWhenBelowLimit(): void
    {
        $user = $this->createMockUser('free');

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Alert::class)
            ->willReturn($this->alertRepository);

        $this->alertRepository
            ->expects($this->once())
            ->method('count')
            ->with(['user' => $user])
            ->willReturn(2); // 2 < 3

        $result = $this->service->checkAlertLimit($user);

        $this->assertTrue($result);
    }

    public function testCheckAlertLimitReturnsFalseWhenAtLimit(): void
    {
        $user = $this->createMockUser('free');

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Alert::class)
            ->willReturn($this->alertRepository);

        $this->alertRepository
            ->expects($this->once())
            ->method('count')
            ->with(['user' => $user])
            ->willReturn(3); // 3 is not < 3

        $result = $this->service->checkAlertLimit($user);

        $this->assertFalse($result);
    }

    // ============================================
    // checkMonitorLimit Tests
    // ============================================

    public function testCheckMonitorLimitReturnsTrueWhenBelowLimit(): void
    {
        $user = $this->createMockUser('free');
        $queryBuilder = $this->createMockQueryBuilder();

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(MonitoringResult::class)
            ->willReturn($this->monitoringResultRepository);

        $this->monitoringResultRepository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        // Mock the query chain
        $queryBuilder->method('join')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('select')->willReturnSelf();

        // Final result
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn(50); // 50 < 100

        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $result = $this->service->checkMonitorLimit($user);

        $this->assertTrue($result);
    }

    public function testCheckMonitorLimitReturnsFalseWhenAtLimit(): void
    {
        $user = $this->createMockUser('free');
        $queryBuilder = $this->createMockQueryBuilder();

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(MonitoringResult::class)
            ->willReturn($this->monitoringResultRepository);

        $this->monitoringResultRepository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        // Mock the query chain
        $queryBuilder->method('join')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('select')->willReturnSelf();

        // Final result
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn(100); // 100 is not < 100

        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $result = $this->service->checkMonitorLimit($user);

        $this->assertFalse($result);
    }

    // ============================================
    // getUsageStats Tests
    // ============================================

    public function testGetUsageStatsReturnsCombinedMetrics(): void
    {
        $user = $this->createMockUser('pro');

        // Setup endpoint repository mock
        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturnMap([
                [Endpoint::class, $this->endpointRepository],
                [Alert::class, $this->alertRepository],
                [MonitoringResult::class, $this->monitoringResultRepository],
            ]);

        $this->endpointRepository
            ->expects($this->once())
            ->method('count')
            ->willReturn(15);

        $this->alertRepository
            ->expects($this->once())
            ->method('count')
            ->willReturn(8);

        // Setup monitoring result query builder
        $queryBuilder = $this->createMockQueryBuilder();
        $this->monitoringResultRepository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->method('join')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('select')->willReturnSelf();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn(250);

        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $stats = $this->service->getUsageStats($user);

        $this->assertEquals('pro', $stats['tier']);
        $this->assertEquals(50, $stats['limits']['endpoints']);
        $this->assertEquals(15, $stats['current']['endpoints']);
        $this->assertEquals(8, $stats['current']['alerts']);
        $this->assertEquals(250, $stats['current']['monitors_today']);
    }

    public function testGetUsageStatsForFreeTier(): void
    {
        $user = $this->createMockUser('free');

        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('getRepository')
            ->willReturnMap([
                [Endpoint::class, $this->endpointRepository],
                [Alert::class, $this->alertRepository],
                [MonitoringResult::class, $this->monitoringResultRepository],
            ]);

        $this->endpointRepository->method('count')->willReturn(3);
        $this->alertRepository->method('count')->willReturn(1);

        $queryBuilder = $this->createMockQueryBuilder();
        $this->monitoringResultRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $queryBuilder->method('join')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('select')->willReturnSelf();

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getSingleScalarResult')->willReturn(75);
        $queryBuilder->method('getQuery')->willReturn($query);

        $stats = $this->service->getUsageStats($user);

        $this->assertEquals('free', $stats['tier']);
        $this->assertEquals(5, $stats['limits']['endpoints']);
        $this->assertEquals(3, $stats['current']['endpoints']);
        $this->assertEquals(1, $stats['current']['alerts']);
        $this->assertEquals(75, $stats['current']['monitors_today']);
    }

    // ============================================
    // Helper Methods
    // ============================================

    private function createMockUser(string $tier): MockObject
    {
        $user = $this->createMock(User::class);
        $user->method('getSubscriptionTier')->willReturn($tier);
        return $user;
    }

    private function createMockQueryBuilder(): MockObject
    {
        return $this->createMock(QueryBuilder::class);
    }
}
