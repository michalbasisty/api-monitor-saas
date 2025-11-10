<?php

namespace App\Modules\Base;

use App\Entity\User;
use App\Kernel\ModuleInterface;

class BaseModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'base';
    }

    public function getDisplayName(): string
    {
        return 'Core Monitoring';
    }

    public function getDescription(): string
    {
        return 'Basic API endpoint monitoring';
    }

    public function getRequiredTiers(): array
    {
        // Available on all tiers
        return ['free', 'pro', 'business', 'enterprise'];
    }

    public function onEnable(User $user): void
    {
        // Nothing special needed
    }

    public function onDisable(User $user): void
    {
        // Cannot disable base module
    }

    public function getServices(): array
    {
        return [
            'base.endpoint_service' => 'App\Modules\Base\Service\EndpointService',
            'base.monitoring_service' => 'App\Modules\Base\Service\MonitoringService',
            'base.alert_service' => 'App\Modules\Base\Service\AlertService',
        ];
    }

    public function getEntities(): array
    {
        return [
            'App\Entity\Endpoint',
            'App\Entity\MonitoringResult',
            'App\Entity\Alert',
        ];
    }

    public function getMigrations(): array
    {
        return [];
    }

    public function getRoutesPath(): string
    {
        return __DIR__ . '/resources/config/routes.yaml';
    }
}
