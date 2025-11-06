<?php

namespace App\Entity;

use App\Repository\MonitoringResultRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MonitoringResultRepository::class)]
#[ORM\Table(name: 'monitoring_results')]
#[ORM\Index(columns: ['endpoint_id'], name: 'idx_monitoring_endpoint')]
#[ORM\Index(columns: ['checked_at'], name: 'idx_monitoring_checked_at')]
class MonitoringResult
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?string $id = null;

    #[ORM\Column(type: 'uuid')]
    private string $endpoint_id;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $response_time = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $status_code = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $error_message = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $checked_at;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $created_at;

    public function __construct()
    {
        $this->checked_at = new \DateTimeImmutable();
        $this->created_at = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getEndpointId(): string
    {
        return $this->endpoint_id;
    }

    public function setEndpointId(string $endpoint_id): self
    {
        $this->endpoint_id = $endpoint_id;
        return $this;
    }

    public function getResponseTime(): ?int
    {
        return $this->response_time;
    }

    public function setResponseTime(?int $response_time): self
    {
        $this->response_time = $response_time;
        return $this;
    }

    public function getStatusCode(): ?int
    {
        return $this->status_code;
    }

    public function setStatusCode(?int $status_code): self
    {
        $this->status_code = $status_code;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->error_message;
    }

    public function setErrorMessage(?string $error_message): self
    {
        $this->error_message = $error_message;
        return $this;
    }

    public function getCheckedAt(): \DateTimeImmutable
    {
        return $this->checked_at;
    }

    public function setCheckedAt(\DateTimeImmutable $checked_at): self
    {
        $this->checked_at = $checked_at;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->created_at;
    }

    public function isSuccessful(): bool
    {
        return $this->status_code !== null 
            && $this->status_code >= 200 
            && $this->status_code < 300 
            && $this->error_message === null;
    }
}
