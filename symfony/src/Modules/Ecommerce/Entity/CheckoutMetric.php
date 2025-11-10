<?php

namespace App\Modules\Ecommerce\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'ecommerce_checkout_metrics')]
class CheckoutMetric
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Store $store = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CheckoutStep $step = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $timestamp = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $loadTimeMs = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $apiResponseTimeMs = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $errorOccurred = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $httpStatusCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sessionId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
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
}
