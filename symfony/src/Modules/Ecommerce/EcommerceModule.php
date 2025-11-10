<?php

namespace App\Modules\Ecommerce;

use App\Entity\User;
use App\Kernel\ModuleInterface;

class EcommerceModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'ecommerce';
    }

    public function getDisplayName(): string
    {
        return 'E-Commerce Monitoring';
    }

    public function getDescription(): string
    {
        return 'Advanced monitoring for online stores: checkout flow, payments, sales impact';
    }

    public function getRequiredTiers(): array
    {
        return ['pro', 'business', 'enterprise'];
    }

    public function onEnable(User $user): void
    {
        // Create default store config placeholder
        // Send welcome email
    }

    public function onDisable(User $user): void
    {
        // Archive store data
        // Stop monitoring
    }

    public function getServices(): array
    {
        return [
            'ecommerce.store_service' => 'App\Modules\Ecommerce\Service\StoreService',
            'ecommerce.checkout_service' => 'App\Modules\Ecommerce\Service\CheckoutService',
            'ecommerce.payment_service' => 'App\Modules\Ecommerce\Service\PaymentService',
            'ecommerce.sales_metrics_service' => 'App\Modules\Ecommerce\Service\SalesMetricsService',
            'ecommerce.abandonment_service' => 'App\Modules\Ecommerce\Service\AbandonmentService',
        ];
    }

    public function getEntities(): array
    {
        return [
            'App\Modules\Ecommerce\Entity\Store',
            'App\Modules\Ecommerce\Entity\CheckoutStep',
            'App\Modules\Ecommerce\Entity\CheckoutMetric',
            'App\Modules\Ecommerce\Entity\PaymentGateway',
            'App\Modules\Ecommerce\Entity\PaymentMetric',
            'App\Modules\Ecommerce\Entity\SalesMetric',
            'App\Modules\Ecommerce\Entity\Abandonment',
            'App\Modules\Ecommerce\Entity\TrafficSpike',
            'App\Modules\Ecommerce\Entity\EcommerceAlert',
        ];
    }

    public function getMigrations(): array
    {
        return [
            'DoctrineMigrations\Version20240101010000_CreateEcommerceTables',
        ];
    }

    public function getRoutesPath(): string
    {
        return __DIR__ . '/resources/config/routes.yaml';
    }
}
