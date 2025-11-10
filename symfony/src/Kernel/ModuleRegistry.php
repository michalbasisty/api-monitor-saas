<?php

namespace App\Kernel;

use App\Entity\User;
use App\Entity\UserModuleSubscription;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ModuleRegistry
{
    private array $modules = [];
    private ContainerInterface $container;
    private EntityManagerInterface $em;

    public function __construct(
        ContainerInterface $container,
        EntityManagerInterface $em
    ) {
        $this->container = $container;
        $this->em = $em;
    }

    /**
     * Register a module
     */
    public function register(ModuleInterface $module): void
    {
        $this->modules[$module->getName()] = $module;
    }

    /**
     * Get all registered modules
     */
    public function getAllModules(): array
    {
        return $this->modules;
    }

    /**
     * Get enabled modules for a user
     */
    public function getEnabledModules(User $user): array
    {
        $subscription = $user->getSubscription();
        if (!$subscription) {
            return [];
        }

        $enabledModules = [];
        $userTier = $subscription->getTier(); // 'free', 'pro', 'business', 'enterprise'

        foreach ($this->modules as $name => $module) {
            // Check if module is enabled in DB for this user
            $isEnabled = $this->em->getRepository(UserModuleSubscription::class)
                ->isModuleEnabled($user->getId(), $name);

            if (!$isEnabled) {
                continue;
            }

            // Check if user's tier allows this module
            if (!in_array($userTier, $module->getRequiredTiers())) {
                continue;
            }

            $enabledModules[$name] = $module;
        }

        return $enabledModules;
    }

    /**
     * Get module by name
     */
    public function getModule(string $name): ?ModuleInterface
    {
        return $this->modules[$name] ?? null;
    }

    /**
     * Enable module for user
     */
    public function enableModule(User $user, string $moduleName): void
    {
        $module = $this->getModule($moduleName);
        if (!$module) {
            throw new \InvalidArgumentException("Module not found: {$moduleName}");
        }

        // Check subscription tier
        $subscription = $user->getSubscription();
        $tier = $subscription?->getTier() ?? 'free';

        if (!in_array($tier, $module->getRequiredTiers())) {
            throw new \Exception("Module {$moduleName} not available for tier {$tier}");
        }

        // Create DB record
        $userModule = new UserModuleSubscription();
        $userModule->setUser($user);
        $userModule->setModuleName($moduleName);
        $userModule->setEnabled(true);
        $userModule->setTier($tier);
        $userModule->setCreatedAt(new \DateTime());

        $this->em->persist($userModule);
        $this->em->flush();

        // Call module hook
        $module->onEnable($user);
    }

    /**
     * Disable module for user
     */
    public function disableModule(User $user, string $moduleName): void
    {
        $userModule = $this->em->getRepository(UserModuleSubscription::class)
            ->findOneBy(['user' => $user, 'moduleName' => $moduleName]);

        if ($userModule) {
            $userModule->setEnabled(false);
            $this->em->flush();
        }

        $module = $this->getModule($moduleName);
        if ($module) {
            $module->onDisable($user);
        }
    }
}
