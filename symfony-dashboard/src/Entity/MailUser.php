<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'mail_users')]
class MailUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(nullable: true)]
    private ?string $twoFactorSecret = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isTwoFactorEnabled = false;

    #[ORM\ManyToOne(targetEntity: Domain::class, inversedBy: 'users')]
    private ?Domain $domain = null;

    #[ORM\Column(nullable: true)]
    private ?int $quotaUsed = 0;

    #[ORM\Column(nullable: true)]
    private ?int $quotaLimit = 1024; // in MB

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastLogin = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    // Getters and setters...
}
