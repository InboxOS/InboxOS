<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'tenants')]
class Tenant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(nullable: true)]
    private ?int $quotaMb = 10240; // 10GB default

    #[ORM\Column(nullable: true)]
    private ?int $userLimit = 50;

    #[ORM\Column(nullable: true)]
    private ?int $domainLimit = 5;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $settings = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'tenant', targetEntity: Domain::class)]
    private Collection $domains;

    #[ORM\OneToMany(mappedBy: 'tenant', targetEntity: MailUser::class)]
    private Collection $users;

    #[ORM\OneToMany(mappedBy: 'tenant', targetEntity: ApiKey::class)]
    private Collection $apiKeys;

    public function __construct()
    {
        $this->domains = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->apiKeys = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters and setters...
}
