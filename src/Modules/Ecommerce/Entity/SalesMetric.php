<?php

namespace App\Modules\Ecommerce\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: 'App\Modules\Ecommerce\Repository\SalesMetricRepository')]
#[ORM\Table(name: 'ecommerce_sales_metrics')]
#[ORM\Index(columns: ['store_id', 'timestamp'], name: 'idx_store_timestamp')]
#[ORM\Index(columns: ['store_id', 'status'], name: 'idx_status')]
class SalesMetric
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Store::class, inversedBy: 'salesMetrics')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Store $store = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $timestamp = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $revenuePerMinute = null;

    #[ORM\Column(nullable: true)]
    private ?int $ordersPerMinute = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $checkoutSuccessRate = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $avgOrderValue = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $estimatedLostRevenue = null;

    #[ORM\Column(length: 50, options: ['default' => 'normal'])]
    private ?string $status = 'normal'; // 'normal', 'degraded', 'down'

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

    public function getTimestamp(): ?\DateTime
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTime $timestamp): self
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    public function getRevenuePerMinute(): ?string
    {
        return $this->revenuePerMinute;
    }

    public function setRevenuePerMinute(?string $revenuePerMinute): self
    {
        $this->revenuePerMinute = $revenuePerMinute;
        return $this;
    }

    public function getOrdersPerMinute(): ?int
    {
        return $this->ordersPerMinute;
    }

    public function setOrdersPerMinute(?int $ordersPerMinute): self
    {
        $this->ordersPerMinute = $ordersPerMinute;
        return $this;
    }

    public function getCheckoutSuccessRate(): ?string
    {
        return $this->checkoutSuccessRate;
    }

    public function setCheckoutSuccessRate(?string $checkoutSuccessRate): self
    {
        $this->checkoutSuccessRate = $checkoutSuccessRate;
        return $this;
    }

    public function getAvgOrderValue(): ?string
    {
        return $this->avgOrderValue;
    }

    public function setAvgOrderValue(?string $avgOrderValue): self
    {
        $this->avgOrderValue = $avgOrderValue;
        return $this;
    }

    public function getEstimatedLostRevenue(): ?string
    {
        return $this->estimatedLostRevenue;
    }

    public function setEstimatedLostRevenue(?string $estimatedLostRevenue): self
    {
        $this->estimatedLostRevenue = $estimatedLostRevenue;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
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
     * Check if store is experiencing downtime
     */
    public function isDown(): bool
    {
        return $this->status === 'down';
    }

    /**
     * Check if store is degraded
     */
    public function isDegraded(): bool
    {
        return $this->status === 'degraded';
    }

    /**
     * Check if store is normal
     */
    public function isNormal(): bool
    {
        return $this->status === 'normal';
    }
}
