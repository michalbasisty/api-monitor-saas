<?php

namespace App\Entity;

use App\Repository\AlertRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AlertRepository::class)]
#[ORM\Table(name: 'alerts')]
class Alert
{
    public const TYPE_RESPONSE_TIME = 'response_time';
    public const TYPE_STATUS_CODE = 'status_code';
    public const TYPE_AVAILABILITY = 'availability';

    public const VALID_TYPES = [
        self::TYPE_RESPONSE_TIME,
        self::TYPE_STATUS_CODE,
        self::TYPE_AVAILABILITY
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?string $id = null;

    #[ORM\Column(type: 'uuid')]
    #[Assert\NotBlank(message: 'User ID is required')]
    private string $user_id;

    #[ORM\Column(type: 'uuid')]
    #[Assert\NotBlank(message: 'Endpoint ID is required')]
    private string $endpoint_id;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Alert type is required')]
    #[Assert\Choice(choices: self::VALID_TYPES, message: 'Invalid alert type')]
    private string $alert_type;

    #[ORM\Column(type: 'json')]
    #[Assert\NotBlank(message: 'Threshold is required')]
    private array $threshold;

    #[ORM\Column(type: 'boolean')]
    private bool $is_active = true;

    #[ORM\Column(type: 'json')]
    #[Assert\NotBlank(message: 'Notification channels are required')]
    private array $notification_channels = ['email'];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $last_triggered_at = null;

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

    public function getEndpointId(): string
    {
        return $this->endpoint_id;
    }

    public function setEndpointId(string $endpoint_id): self
    {
        $this->endpoint_id = $endpoint_id;
        return $this;
    }

    public function getAlertType(): string
    {
        return $this->alert_type;
    }

    public function setAlertType(string $alert_type): self
    {
        $this->alert_type = $alert_type;
        return $this;
    }

    public function getThreshold(): array
    {
        return $this->threshold;
    }

    public function setThreshold(array $threshold): self
    {
        $this->threshold = $threshold;
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

    public function getNotificationChannels(): array
    {
        return $this->notification_channels;
    }

    public function setNotificationChannels(array $notification_channels): self
    {
        $this->notification_channels = $notification_channels;
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

    public function getLastTriggeredAt(): ?\DateTimeImmutable
    {
        return $this->last_triggered_at;
    }

    public function setLastTriggeredAt(?\DateTimeImmutable $last_triggered_at): self
    {
        $this->last_triggered_at = $last_triggered_at;
        return $this;
    }
}
