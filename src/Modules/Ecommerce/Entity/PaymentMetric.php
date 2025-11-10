<?php

namespace App\Modules\Ecommerce\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: 'App\Modules\Ecommerce\Repository\PaymentMetricRepository')]
#[ORM\Table(name: 'ecommerce_payment_metrics')]
#[ORM\Index(columns: ['store_id', 'created_at'], name: 'idx_store_timestamp')]
#[ORM\Index(columns: ['transaction_id'], name: 'idx_transaction_id')]
#[ORM\Index(columns: ['status'], name: 'idx_status')]
class PaymentMetric
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Store::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Store $store = null;

    #[ORM\ManyToOne(targetEntity: PaymentGateway::class, inversedBy: 'metrics')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PaymentGateway $gateway = null;

    #[ORM\Column(length: 255)]
    private ?string $transactionId = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null; // 'authorized', 'declined', 'settled', 'pending', 'refunded'

    #[ORM\Column(nullable: true)]
    private ?int $authorizationTimeMs = null;

    #[ORM\Column(nullable: true)]
    private ?int $settlementTimeHours = null;

    #[ORM\Column]
    private bool $webhookReceived = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $webhookTimestamp = null;

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

    public function getGateway(): ?PaymentGateway
    {
        return $this->gateway;
    }

    public function setGateway(?PaymentGateway $gateway): self
    {
        $this->gateway = $gateway;
        return $this;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(string $transactionId): self
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;
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

    public function getAuthorizationTimeMs(): ?int
    {
        return $this->authorizationTimeMs;
    }

    public function setAuthorizationTimeMs(?int $authorizationTimeMs): self
    {
        $this->authorizationTimeMs = $authorizationTimeMs;
        return $this;
    }

    public function getSettlementTimeHours(): ?int
    {
        return $this->settlementTimeHours;
    }

    public function setSettlementTimeHours(?int $settlementTimeHours): self
    {
        $this->settlementTimeHours = $settlementTimeHours;
        return $this;
    }

    public function isWebhookReceived(): bool
    {
        return $this->webhookReceived;
    }

    public function setWebhookReceived(bool $webhookReceived): self
    {
        $this->webhookReceived = $webhookReceived;
        return $this;
    }

    public function getWebhookTimestamp(): ?\DateTime
    {
        return $this->webhookTimestamp;
    }

    public function setWebhookTimestamp(?\DateTime $webhookTimestamp): self
    {
        $this->webhookTimestamp = $webhookTimestamp;
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
     * Check if payment was successful
     */
    public function isSuccessful(): bool
    {
        return in_array($this->status, ['authorized', 'settled']);
    }

    /**
     * Check if payment is failed/declined
     */
    public function isFailed(): bool
    {
        return in_array($this->status, ['declined', 'failed']);
    }
}
