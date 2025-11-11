<?php

namespace App\Tests\Unit\Repository;

use App\Entity\UserModuleSubscription;
use App\Repository\UserModuleSubscriptionRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class UserModuleSubscriptionRepositoryTest extends TestCase
{
    public function testRepositoryClassExtendsServiceEntityRepository(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $repo = new UserModuleSubscriptionRepository($registry);
        $this->assertInstanceOf(ServiceEntityRepository::class, $repo);
    }
}
