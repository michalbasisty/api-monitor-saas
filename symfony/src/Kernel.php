<?php

namespace App;

use App\Kernel\ModuleRegistry;
use App\Modules\Base\BaseModule;
use App\Modules\Ecommerce\EcommerceModule;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

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

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import('../config/{packages}/*.yaml');
        $container->import('../config/services.yaml');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // Load main API routes
        $routes->import('../config/routes.yaml');
        
        // Load module routes dynamically after boot
        if ($this->container->has(ModuleRegistry::class)) {
            $moduleRegistry = $this->container->get(ModuleRegistry::class);
            
            foreach ($moduleRegistry->getAllModules() as $module) {
                $routesPath = $module->getRoutesPath();
                if (file_exists($routesPath)) {
                    $routes->import($routesPath);
                }
            }
        }
    }
}