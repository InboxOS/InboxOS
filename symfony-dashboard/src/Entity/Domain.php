<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'domains')]
#[ORM\HasLifecycleCallbacks]
class Domain
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Domain]
    private ?string $name = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private bool $enableSpf = true;

    #[ORM\Column]
    private bool $enableDkim = true;

    #[ORM\Column]
    private bool $enableDmarc = true;

    #[ORM\Column]
    private bool $enableMtaSts = true;

    #[ORM\Column(nullable: true)]
    private ?string $dkimSelector = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $dkimPrivateKey = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $dkimPublicKey = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Tenant::class, inversedBy: 'domains')]
    private ?Tenant $tenant = null;

    #[ORM\OneToMany(mappedBy: 'domain', targetEntity: MailUser::class)]
    private $users;

    #[ORM\OneToMany(mappedBy: 'domain', targetEntity: Alias::class)]
    private $aliases;

    #[ORM\Column(nullable: true)]
    private ?int $quotaMb = 1024;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isEnableSpf(): bool
    {
        return $this->enableSpf;
    }

    public function setEnableSpf(bool $enableSpf): static
    {
        $this->enableSpf = $enableSpf;

        return $this;
    }

    public function isEnableDkim(): bool
    {
        return $this->enableDkim;
    }

    public function setEnableDkim(bool $enableDkim): static
    {
        $this->enableDkim = $enableDkim;

        return $this;
    }

    public function isEnableDmarc(): bool
    {
        return $this->enableDmarc;
    }

    public function setEnableDmarc(bool $enableDmarc): static
    {
        $this->enableDmarc = $enableDmarc;

        return $this;
    }

    public function isEnableMtaSts(): bool
    {
        return $this->enableMtaSts;
    }

    public function setEnableMtaSts(bool $enableMtaSts): static
    {
        $this->enableMtaSts = $enableMtaSts;

        return $this;
    }

    public function getDkimSelector(): ?string
    {
        return $this->dkimSelector;
    }

    public function setDkimSelector(?string $dkimSelector): static
    {
        $this->dkimSelector = $dkimSelector;

        return $this;
    }

    public function getDkimPrivateKey(): ?string
    {
        return $this->dkimPrivateKey;
    }

    public function setDkimPrivateKey(?string $dkimPrivateKey): static
    {
        $this->dkimPrivateKey = $dkimPrivateKey;

        return $this;
    }

    public function getDkimPublicKey(): ?string
    {
        return $this->dkimPublicKey;
    }

    public function setDkimPublicKey(?string $dkimPublicKey): static
    {
        $this->dkimPublicKey = $dkimPublicKey;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function setTenant(?Tenant $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function getUsers()
    {
        return $this->users;
    }

    public function getAliases()
    {
        return $this->aliases;
    }

    public function getQuotaMb(): ?int
    {
        return $this->quotaMb;
    }

    public function setQuotaMb(?int $quotaMb): static
    {
        $this->quotaMb = $quotaMb;

        return $this;
    }
}
