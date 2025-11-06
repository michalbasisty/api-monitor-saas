<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['email'], message: 'This email is already registered')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private ?string $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Please provide a valid email address')]
    private ?string $email = null;

    #[ORM\Column(type: 'string')]
    #[Assert\NotBlank(message: 'Password is required')]
    #[Assert\Length(min: 8, minMessage: 'Password must be at least 8 characters')]
    private string $password;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?string $company_id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $subscription_tier = 'free';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $last_login_at = null;

    #[ORM\Column(type: 'boolean')]
    private bool $is_verified = false;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $verification_token = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $verification_token_expires_at = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $reset_token = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reset_token_expires_at = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $stripe_customer_id = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $stripe_subscription_id = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $subscription_expires_at = null;

    #[ORM\Column(type: 'boolean')]
    private bool $is_active_subscription = false;

    public function __construct()
    {
    $this->id = Uuid::v4()->toRfc4122();
        $this->created_at = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function getCompanyId(): ?string
    {
        return $this->company_id;
    }

    public function setCompanyId(?string $company_id): self
    {
        $this->company_id = $company_id;
        return $this;
    }

    public function getSubscriptionTier(): string
    {
        return $this->subscription_tier;
    }

    public function setSubscriptionTier(string $subscription_tier): self
    {
        $this->subscription_tier = $subscription_tier;
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

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->last_login_at;
    }

    public function setLastLoginAt(?\DateTimeImmutable $last_login_at): self
    {
        $this->last_login_at = $last_login_at;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->is_verified;
    }

    public function setIsVerified(bool $is_verified): self
    {
        $this->is_verified = $is_verified;
        return $this;
    }

    public function getVerificationToken(): ?string
    {
        return $this->verification_token;
    }

    public function setVerificationToken(?string $verification_token): self
    {
        $this->verification_token = $verification_token;
        return $this;
    }

    public function getVerificationTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->verification_token_expires_at;
    }

    public function setVerificationTokenExpiresAt(?\DateTimeImmutable $verification_token_expires_at): self
    {
        $this->verification_token_expires_at = $verification_token_expires_at;
        return $this;
    }

    public function getResetToken(): ?string
    {
        return $this->reset_token;
    }

    public function setResetToken(?string $reset_token): self
    {
        $this->reset_token = $reset_token;
        return $this;
    }

    public function getResetTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->reset_token_expires_at;
    }

    public function setResetTokenExpiresAt(?\DateTimeImmutable $reset_token_expires_at): self
    {
        $this->reset_token_expires_at = $reset_token_expires_at;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripe_customer_id;
    }

    public function setStripeCustomerId(?string $stripe_customer_id): self
    {
        $this->stripe_customer_id = $stripe_customer_id;
        return $this;
    }

    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripe_subscription_id;
    }

    public function setStripeSubscriptionId(?string $stripe_subscription_id): self
    {
        $this->stripe_subscription_id = $stripe_subscription_id;
        return $this;
    }

    public function getSubscriptionExpiresAt(): ?\DateTimeImmutable
    {
    return $this->subscription_expires_at;
    }

    public function setSubscriptionExpiresAt(?\DateTimeImmutable $subscription_expires_at): self
    {
        $this->subscription_expires_at = $subscription_expires_at;
        return $this;
    }

    public function isActiveSubscription(): bool
    {
        return $this->is_active_subscription;
    }

    public function setIsActiveSubscription(bool $is_active_subscription): self
    {
        $this->is_active_subscription = $is_active_subscription;
        return $this;
    }
}