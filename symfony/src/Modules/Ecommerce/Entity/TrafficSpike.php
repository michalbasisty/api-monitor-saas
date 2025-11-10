<?php

namespace App\Modules\Ecommerce\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'ecommerce_traffic_spikes')]
class TrafficSpike
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Store $store = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $spikeDetectedAt = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $baselineRpm = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $peakRpm = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $durationMinutes = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $eventType = null;

    #[ORM\Column(length: 50)]
    private string $performanceImpact = 'normal';

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

    public function getSpikeDetectedAt(): ?\DateTime
    {
        return $this->spikeDetectedAt;
    }

    public function setSpikeDetectedAt(\DateTime $spikeDetectedAt): self
    {
        $this->spikeDetectedAt = $spikeDetectedAt;
        return $this;
    }

    public function getBaselineRpm(): ?int
    {
        return $this->baselineRpm;
    }

    public function setBaselineRpm(int $baselineRpm): self
    {
        $this->baselineRpm = $baselineRpm;
        return $this;
    }

    public function getPeakRpm(): ?int
    {
        return $this->peakRpm;
    }

    public function setPeakRpm(int $peakRpm): self
    {
        $this->peakRpm = $peakRpm;
        return $this;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(int $durationMinutes): self
    {
        $this->durationMinutes = $durationMinutes;
        return $this;
    }

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(?string $eventType): self
    {
        $this->eventType = $eventType;
        return $this;
    }

    public function getPerformanceImpact(): string
    {
        return $this->performanceImpact;
    }

    public function setPerformanceImpact(string $performanceImpact): self
    {
        $this->performanceImpact = $performanceImpact;
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
