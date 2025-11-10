<?php

namespace App\Modules\Ecommerce\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: 'App\Modules\Ecommerce\Repository\PaymentGatewayRepository')]
#[ORM\Table(name: 'ecommerce_payment_gateways')]
#[ORM\UniqueConstraint(name: 'unique_store_gateway', columns: ['store_id', 'gateway_name'])]
class PaymentGateway
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Store::class, inversedBy: 'paymentGateways')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Store $store = null;

    #[ORM\Column(length: 100)]
    private ?string $gatewayName = null; // 'stripe', 'paypal', 'square', etc.

    #[ORM\Column(length: 500)]
    private ?string $apiKeyEncrypted = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $webhookUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $webhookSecretEncrypted = null;

    #[ORM\Column]
    private bool $isPrimary = false;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column(type: 'json', nullable: true)]
    private array $metadata = [];

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\OneToMany(targetEntity: PaymentMetric::class, mappedBy: 'gateway', cascade: ['all'])]
    private Collection $metrics;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTime();
        $this->metrics = new ArrayCollection();
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

    public function getGatewayName(): ?string
    {
        return $this->gatewayName;
    }

    public function setGatewayName(string $gatewayName): self
    {
        $this->gatewayName = $gatewayName;
        return $this;
    }

    public function getApiKeyEncrypted(): ?string
    {
        return $this->apiKeyEncrypted;
    }

    public function setApiKeyEncrypted(string $apiKeyEncrypted): self
    {
        $this->apiKeyEncrypted = $apiKeyEncrypted;
        return $this;
    }

    public function getWebhookUrl(): ?string
    {
        return $this->webhookUrl;
    }

    public function setWebhookUrl(?string $webhookUrl): self
    {
        $this->webhookUrl = $webhookUrl;
        return $this;
    }

    public function getWebhookSecretEncrypted(): ?string
    {
        return $this->webhookSecretEncrypted;
    }

    public function setWebhookSecretEncrypted(?string $webhookSecretEncrypted): self
    {
        $this->webhookSecretEncrypted = $webhookSecretEncrypted;
        return $this;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): self
    {
        $this->isPrimary = $isPrimary;
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

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
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

    /**
     * @return Collection<int, PaymentMetric>
     */
    public function getMetrics(): Collection
    {
        return $this->metrics;
    }

    public function addMetric(PaymentMetric $metric): self
    {
        if (!$this->metrics->contains($metric)) {
            $this->metrics->add($metric);
            $metric->setGateway($this);
        }
        return $this;
    }
}
