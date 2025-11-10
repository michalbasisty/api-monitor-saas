<?php

namespace App\Modules\Ecommerce\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: 'App\Modules\Ecommerce\Repository\AbandonmentRepository')]
#[ORM\Table(name: 'ecommerce_abandonment')]
#[ORM\Index(columns: ['store_id', 'created_at'], name: 'idx_store_abandoned')]
#[ORM\Index(columns: ['session_id'], name: 'idx_session_id')]
class Abandonment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Store::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Store $store = null;

    #[ORM\Column(length: 255)]
    private ?string $sessionId = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $startedAt = null;

    #[ORM\ManyToOne(targetEntity: CheckoutStep::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CheckoutStep $abandonedAtStep = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $lastSeen = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason = null; // 'timeout', 'error', 'user_exit', 'payment_failed'

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

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function getStartedAt(): ?\DateTime
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTime $startedAt): self
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getAbandonedAtStep(): ?CheckoutStep
    {
        return $this->abandonedAtStep;
    }

    public function setAbandonedAtStep(?CheckoutStep $abandonedAtStep): self
    {
        $this->abandonedAtStep = $abandonedAtStep;
        return $this;
    }

    public function getLastSeen(): ?\DateTime
    {
        return $this->lastSeen;
    }

    public function setLastSeen(\DateTime $lastSeen): self
    {
        $this->lastSeen = $lastSeen;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason;
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
     * Get duration from start to abandonment (in seconds)
     */
    public function getDurationSeconds(): int
    {
        return $this->lastSeen->getTimestamp() - $this->startedAt->getTimestamp();
    }

    /**
     * Get the step number where abandoned
     */
    public function getAbandonmentStepNumber(): ?int
    {
        return $this->abandonedAtStep?->getStepNumber();
    }
}
