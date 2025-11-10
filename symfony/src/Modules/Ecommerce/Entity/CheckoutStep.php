<?php

namespace App\Modules\Ecommerce\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'ecommerce_checkout_steps')]
class CheckoutStep
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Store $store = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $stepNumber = null;

    #[ORM\Column(length: 100)]
    private ?string $stepName = null;

    #[ORM\Column(length: 255)]
    private ?string $endpointUrl = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $expectedLoadTimeMs = 1000;

    #[ORM\Column(type: Types::INTEGER)]
    private int $alertThresholdMs = 2000;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $enabled = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getStore(): ?Store
    {
        return $this->store;
    }

    public function setStore(?Store $store): self
    {
        $this->store = $store;
        return $this;
    }

    public function getStepNumber(): ?int
    {
        return $this->stepNumber;
    }

    public function setStepNumber(int $stepNumber): self
    {
        $this->stepNumber = $stepNumber;
        return $this;
    }

    public function getStepName(): ?string
    {
        return $this->stepName;
    }

    public function setStepName(string $stepName): self
    {
        $this->stepName = $stepName;
        return $this;
    }

    public function getEndpointUrl(): ?string
    {
        return $this->endpointUrl;
    }

    public function setEndpointUrl(string $endpointUrl): self
    {
        $this->endpointUrl = $endpointUrl;
        return $this;
    }

    public function getExpectedLoadTimeMs(): int
    {
        return $this->expectedLoadTimeMs;
    }

    public function setExpectedLoadTimeMs(int $expectedLoadTimeMs): self
    {
        $this->expectedLoadTimeMs = $expectedLoadTimeMs;
        return $this;
    }

    public function getAlertThresholdMs(): int
    {
        return $this->alertThresholdMs;
    }

    public function setAlertThresholdMs(int $alertThresholdMs): self
    {
        $this->alertThresholdMs = $alertThresholdMs;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
