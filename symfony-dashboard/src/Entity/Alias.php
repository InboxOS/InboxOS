<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'aliases')]
#[ORM\UniqueConstraint(name: 'unique_alias', columns: ['source', 'domain_id'])]
class Alias
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $source = null; // alias@domain.com

    #[ORM\Column(length: 255)]
    private ?string $destination = null; // real@domain.com or comma-separated list

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Domain::class, inversedBy: 'aliases')]
    private ?Domain $domain = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters and setters...
}
