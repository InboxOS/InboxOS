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
    #[Assert\Hostname]
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }
    
    public function isEnableDkim(): bool
    {
        return $this->enableDkim;
    }
    
    public function getDkimSelector(): ?string
    {
        return $this->dkimSelector;
    }
    
    public function setDkimPrivateKey(?string $dkimPrivateKey): self
    {
        $this->dkimPrivateKey = $dkimPrivateKey;

        return $this;
    }

    public function setDkimPublicKey(?string $dkimPublicKey): self
    {
        $this->dkimPublicKey = $dkimPublicKey;

        return $this;
    }

    public function setDkimSelector(?string $dkimSelector): self
    {
        $this->dkimSelector = $dkimSelector;

        return $this;
    }

    // Getters and setters...
}
