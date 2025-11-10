<?php

namespace App\Modules\Ecommerce\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: 'App\Modules\Ecommerce\Repository\EcommerceAlertRepository')]
#[ORM\Table(name: 'ecommerce_alerts')]
#[ORM\Index(columns: ['store_id', 'created_at'], name: 'idx_store_created')]
#[ORM\Index(columns: ['store_id', 'severity'], name: 'idx_severity')]
#[ORM\Index(columns: ['resolved_at'], name: 'idx_resolved')]
class EcommerceAlert
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Store::class, inversedBy: 'alerts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Store $store = null;

    #[ORM\Column(length: 100)]
    private ?string $alertType = null;

    #[ORM\Column(length: 20)]
    private ?string $severity = null; // 'low', 'medium', 'high', 'critical'

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $triggeredAt = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $metricValue = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $thresholdValue = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $resolvedAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $createdAt = null;

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

    public function getAlertType(): ?string
    {
        return $this->alertType;
    }

    public function setAlertType(string $alertType): self
    {
        $this->alertType = $alertType;
        return $this;
    }

    public function getSeverity(): ?string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): self
    {
        $this->severity = $severity;
        return $this;
    }

    public function getTriggeredAt(): ?\DateTime
    {
        return $this->triggeredAt;
    }

    public function setTriggeredAt(\DateTime $triggeredAt): self
    {
        $this->triggeredAt = $triggeredAt;
        return $this;
    }

    public function getMetricValue(): ?string
    {
        return $this->metricValue;
    }

    public function setMetricValue(?string $metricValue): self
    {
        $this->metricValue = $metricValue;
        return $this;
    }

    public function getThresholdValue(): ?string
    {
        return $this->thresholdValue;
    }

    public function setThresholdValue(?string $thresholdValue): self
    {
        $this->thresholdValue = $thresholdValue;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getResolvedAt(): ?\DateTime
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTime $resolvedAt): self
    {
        $this->resolvedAt = $resolvedAt;
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

    /**
     * Check if alert is active (not resolved)
     */
    public function isActive(): bool
    {
        return $this->resolvedAt === null;
    }

    /**
     * Resolve the alert
     */
    public function resolve(): self
    {
        $this->resolvedAt = new \DateTime();
        return $this;
    }

    /**
     * Get duration from triggered to resolved (or now)
     */
    public function getDurationSeconds(): int
    {
        $end = $this->resolvedAt ?? new \DateTime();
        return $end->getTimestamp() - $this->triggeredAt->getTimestamp();
    }
}
