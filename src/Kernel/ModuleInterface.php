<?php

namespace App\Kernel;

use App\Entity\User;

interface ModuleInterface
{
    /**
     * Get unique module identifier
     */
    public function getName(): string;

    /**
     * Get module display name
     */
    public function getDisplayName(): string;

    /**
     * Get module description
     */
    public function getDescription(): string;

    /**
     * Get which subscription tiers enable this module
     * @return string[] e.g., ['pro', 'business', 'enterprise']
     */
    public function getRequiredTiers(): array;

    /**
     * Called when module is enabled for a user
     */
    public function onEnable(User $user): void;

    /**
     * Called when module is disabled for a user
     */
    public function onDisable(User $user): void;

    /**
     * Get services to register
     * @return array service definitions
     */
    public function getServices(): array;

    /**
     * Get entities for Doctrine
     * @return string[] class names
     */
    public function getEntities(): array;

    /**
     * Get migration classes
     * @return string[] migration class names
     */
    public function getMigrations(): array;

    /**
     * Get routes to register
     * @return string path to routes file
     */
    public function getRoutesPath(): string;
}
