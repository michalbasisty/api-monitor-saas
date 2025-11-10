<?php

namespace App\Modules\Ecommerce\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: 'App\Modules\Ecommerce\Repository\CheckoutMetricRepository')]
#[ORM\Table(name: 'ecommerce_checkout_metrics')]
#[ORM\Index(columns: ['store_id', 'timestamp'], name: 'idx_store_timestamp')]
#[ORM\Index(columns: ['step_id', 'timestamp'], name: 'idx_step_timestamp')]
#[ORM\Index(columns: ['session_id'], name: 'idx_session_id')]
class CheckoutMetric
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Store::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Store $store = null;

    #[ORM\ManyToOne(targetEntity: CheckoutStep::class, inversedBy: 'metrics')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CheckoutStep $step = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $timestamp = null;

    #[ORM\Column]
    private ?int $loadTimeMs = null;

    #[ORM\Column(nullable: true)]
    private ?int $apiResponseTimeMs = null;

    #[ORM\Column]
    private bool $errorOccurred = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(nullable: true)]
    private ?int $httpStatusCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sessionId = null;

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

    public function getStep(): ?CheckoutStep
    {
        return $this->step;
    }

    public function setStep(?CheckoutStep $step): self
    {
        $this->step = $step;
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

    public function getLoadTimeMs(): ?int
    {
        return $this->loadTimeMs;
    }

    public function setLoadTimeMs(int $loadTimeMs): self
    {
        $this->loadTimeMs = $loadTimeMs;
        return $this;
    }

    public function getApiResponseTimeMs(): ?int
    {
        return $this->apiResponseTimeMs;
    }

    public function setApiResponseTimeMs(?int $apiResponseTimeMs): self
    {
        $this->apiResponseTimeMs = $apiResponseTimeMs;
        return $this;
    }

    public function isErrorOccurred(): bool
    {
        return $this->errorOccurred;
    }

    public function setErrorOccurred(bool $errorOccurred): self
    {
        $this->errorOccurred = $errorOccurred;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    public function setHttpStatusCode(?int $httpStatusCode): self
    {
        $this->httpStatusCode = $httpStatusCode;
        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): self
    {
        $this->sessionId = $sessionId;
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
     * Check if this metric should trigger an alert
     */
    public function shouldTriggerAlert(): bool
    {
        if (!$this->step) {
            return false;
        }

        return $this->loadTimeMs > $this->step->getAlertThresholdMs() || $this->errorOccurred;
    }
}
