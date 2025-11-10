<?php

namespace App\Modules\Ecommerce\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: 'App\Modules\Ecommerce\Repository\StoreRepository')]
#[ORM\Table(name: 'ecommerce_stores')]
class Store
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $storeName = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $storeUrl = null;

    #[ORM\Column(length: 50)]
    private ?string $platform = null; // 'shopify', 'woocommerce', 'custom', etc.

    #[ORM\Column(length: 3, options: ['default' => 'USD'])]
    private ?string $currency = 'USD';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $timezone = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private array $metadata = [];

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\OneToMany(targetEntity: CheckoutStep::class, mappedBy: 'store', cascade: ['all'])]
    private Collection $checkoutSteps;

    #[ORM\OneToMany(targetEntity: PaymentGateway::class, mappedBy: 'store', cascade: ['all'])]
    private Collection $paymentGateways;

    #[ORM\OneToMany(targetEntity: SalesMetric::class, mappedBy: 'store', cascade: ['all'])]
    private Collection $salesMetrics;

    #[ORM\OneToMany(targetEntity: EcommerceAlert::class, mappedBy: 'store', cascade: ['all'])]
    private Collection $alerts;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->createdAt = new \DateTime();
        $this->checkoutSteps = new ArrayCollection();
        $this->paymentGateways = new ArrayCollection();
        $this->salesMetrics = new ArrayCollection();
        $this->alerts = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getStoreName(): ?string
    {
        return $this->storeName;
    }

    public function setStoreName(string $storeName): self
    {
        $this->storeName = $storeName;
        return $this;
    }

    public function getStoreUrl(): ?string
    {
        return $this->storeUrl;
    }

    public function setStoreUrl(string $storeUrl): self
    {
        $this->storeUrl = $storeUrl;
        return $this;
    }

    public function getPlatform(): ?string
    {
        return $this->platform;
    }

    public function setPlatform(string $platform): self
    {
        $this->platform = $platform;
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

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): self
    {
        $this->timezone = $timezone;
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
     * @return Collection<int, CheckoutStep>
     */
    public function getCheckoutSteps(): Collection
    {
        return $this->checkoutSteps;
    }

    public function addCheckoutStep(CheckoutStep $checkoutStep): self
    {
        if (!$this->checkoutSteps->contains($checkoutStep)) {
            $this->checkoutSteps->add($checkoutStep);
            $checkoutStep->setStore($this);
        }
        return $this;
    }

    public function removeCheckoutStep(CheckoutStep $checkoutStep): self
    {
        if ($this->checkoutSteps->removeElement($checkoutStep)) {
            if ($checkoutStep->getStore() === $this) {
                $checkoutStep->setStore(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, PaymentGateway>
     */
    public function getPaymentGateways(): Collection
    {
        return $this->paymentGateways;
    }

    public function addPaymentGateway(PaymentGateway $paymentGateway): self
    {
        if (!$this->paymentGateways->contains($paymentGateway)) {
            $this->paymentGateways->add($paymentGateway);
            $paymentGateway->setStore($this);
        }
        return $this;
    }

    /**
     * @return Collection<int, SalesMetric>
     */
    public function getSalesMetrics(): Collection
    {
        return $this->salesMetrics;
    }

    /**
     * @return Collection<int, EcommerceAlert>
     */
    public function getAlerts(): Collection
    {
        return $this->alerts;
    }
}
