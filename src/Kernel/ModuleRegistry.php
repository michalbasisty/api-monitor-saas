<?php

namespace App\Kernel;

use App\Entity\User;
use App\Entity\UserModuleSubscription;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ModuleRegistry
{
    private array $modules = [];

    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger
    ) {}

    /**
     * Register a module
     */
    public function register(ModuleInterface $module): void
    {
        $this->modules[$module->getName()] = $module;
        $this->logger->debug('Module registered', ['module' => $module->getName()]);
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
            // Base module is always enabled
            if ($name === 'base') {
                $enabledModules[$name] = $module;
                continue;
            }

            // Check if module is enabled in DB for this user
            $isEnabled = $this->em->getRepository(UserModuleSubscription::class)
                ->isModuleEnabled($user->getId(), $name);

            if (!$isEnabled) {
                continue;
            }

            // Check if user's tier allows this module
            if (!in_array($userTier, $module->getRequiredTiers())) {
                $this->logger->warning('User tier does not support module', [
                    'user' => $user->getId(),
                    'module' => $name,
                    'tier' => $userTier,
                ]);
                continue;
            }

            $enabledModules[$name] = $module;
        }

        return $enabledModules;
    }

    /**
     * Get enabled module names for a user
     */
    public function getEnabledModuleNames(User $user): array
    {
        return array_keys($this->getEnabledModules($user));
    }

    /**
     * Get module by name
     */
    public function getModule(string $name): ?ModuleInterface
    {
        return $this->modules[$name] ?? null;
    }

    /**
     * Check if module exists
     */
    public function hasModule(string $name): bool
    {
        return isset($this->modules[$name]);
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

        // Cannot disable base module
        if ($moduleName === 'base') {
            throw new \Exception("Cannot manually enable/disable base module");
        }

        // Check subscription tier
        $subscription = $user->getSubscription();
        $tier = $subscription?->getTier() ?? 'free';

        if (!in_array($tier, $module->getRequiredTiers())) {
            throw new \Exception("Module {$moduleName} not available for tier {$tier}");
        }

        // Create DB record
        $userModule = $this->em->getRepository(UserModuleSubscription::class)
            ->findOneBy(['user' => $user, 'moduleName' => $moduleName]);

        if (!$userModule) {
            $userModule = new UserModuleSubscription();
            $userModule->setUser($user);
            $userModule->setModuleName($moduleName);
            $userModule->setTier($tier);
            $this->em->persist($userModule);
        }

        $userModule->setEnabled(true);
        $this->em->flush();

        // Call module hook
        $module->onEnable($user);

        $this->logger->info('Module enabled', [
            'user' => $user->getId(),
            'module' => $moduleName,
        ]);
    }

    /**
     * Disable module for user
     */
    public function disableModule(User $user, string $moduleName): void
    {
        // Cannot disable base module
        if ($moduleName === 'base') {
            throw new \Exception("Cannot manually enable/disable base module");
        }

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

        $this->logger->info('Module disabled', [
            'user' => $user->getId(),
            'module' => $moduleName,
        ]);
    }

    /**
     * Check if user has module enabled
     */
    public function isModuleEnabled(User $user, string $moduleName): bool
    {
        if ($moduleName === 'base') {
            return true;
        }

        $enabledModules = $this->getEnabledModules($user);
        return isset($enabledModules[$moduleName]);
    }

    /**
     * Get all available modules for a subscription tier
     */
    public function getModulesForTier(string $tier): array
    {
        $available = [];

        foreach ($this->modules as $name => $module) {
            if (in_array($tier, $module->getRequiredTiers())) {
                $available[$name] = $module;
            }
        }

        return $available;
    }
}
