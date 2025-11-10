<?php

namespace App;

use App\Kernel\ModuleRegistry;
use App\Modules\Base\BaseModule;
use App\Modules\Ecommerce\EcommerceModule;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
    }

    public function boot(): void
    {
        parent::boot();

        // Register modules after kernel boot
        $this->registerModules();
    }

    private function registerModules(): void
    {
        if (!$this->container->has(ModuleRegistry::class)) {
            return;
        }

        $moduleRegistry = $this->container->get(ModuleRegistry::class);

        // Register base module (always)
        $moduleRegistry->register(new BaseModule());

        // Register optional modules
        $moduleRegistry->register(new EcommerceModule());
    }
}