<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Ignore;

#[ORM\Entity]
#[ORM\Table(name: 'api_keys')]
class ApiKey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $name = null;

    #[ORM\Column(length: 64, unique: true)]
    private ?string $token = null;

    #[ORM\Column(type: 'json')]
    private array $permissions = [];

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastUsed = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $expiresAt = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\ManyToOne(targetEntity: Tenant::class, inversedBy: 'apiKeys')]
    private ?Tenant $tenant = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->token = bin2hex(random_bytes(32));
    }

    // Getters and setters...
}
