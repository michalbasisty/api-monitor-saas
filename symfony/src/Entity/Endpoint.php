<?php

namespace App\Entity;

use App\Repository\EndpointRepository;
use App\Validator\ValidHttpUrl;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EndpointRepository::class)]
#[ORM\Table(name: 'api_endpoints')]
class Endpoint
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?string $id = null;

    #[ORM\Column(type: 'uuid')]
    #[Assert\NotBlank(message: 'User ID is required')]
    private string $user_id;

    #[ORM\Column(type: 'string', length: 2048)]
    #[Assert\NotBlank(message: 'URL is required')]
    #[Assert\Url(message: 'Please provide a valid URL')]
    #[Assert\Length(max: 2048, maxMessage: 'URL cannot exceed 2048 characters')]
    #[ValidHttpUrl]
    private string $url;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank(message: 'Check interval is required')]
    #[Assert\Range(min: 60, minMessage: 'Check interval must be at least 60 seconds')]
    private int $check_interval;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank(message: 'Timeout is required')]
    #[Assert\Range(min: 100, max: 30000, minMessage: 'Timeout must be between 100ms and 30000ms', maxMessage: 'Timeout must be between 100ms and 30000ms')]
    private int $timeout;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $headers = null;

    #[ORM\Column(type: 'boolean')]
    private bool $is_active = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updated_at = null;

    public function __construct()
    {
        $this->created_at = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->user_id;
    }

    public function setUserId(string $user_id): self
    {
        $this->user_id = $user_id;
        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function getCheckInterval(): int
    {
        return $this->check_interval;
    }

    public function setCheckInterval(int $check_interval): self
    {
        $this->check_interval = $check_interval;
        return $this;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function getHeaders(): ?array
    {
        return $this->headers;
    }

    public function setHeaders(?array $headers): self
    {
        $this->headers = $headers;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function setIsActive(bool $is_active): self
    {
        $this->is_active = $is_active;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updated_at): self
    {
        $this->updated_at = $updated_at;
        return $this;
    }

    public function getCompanyId(): ?string
    {
        return null;
    }
}
