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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getTwoFactorSecret(): ?string
    {
        return $this->twoFactorSecret;
    }

    public function setTwoFactorSecret(?string $twoFactorSecret): self
    {
        $this->twoFactorSecret = $twoFactorSecret;

        return $this;
    }

    public function isTwoFactorEnabled(): bool
    {
        return $this->isTwoFactorEnabled;
    }

    public function setIsTwoFactorEnabled(bool $isTwoFactorEnabled): self
    {
        $this->isTwoFactorEnabled = $isTwoFactorEnabled;

        return $this;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setDomain(?Domain $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    public function getDomain(): ?Domain
    {
        return $this->domain;
    }

    public function getQuotaUsed(): ?int
    {
        return $this->quotaUsed;
    }

    public function setQuotaUsed(?int $quotaUsed): self
    {
        $this->quotaUsed = $quotaUsed;

        return $this;
    }

    public function getQuotaLimit(): ?int
    {
        return $this->quotaLimit;
    }

    public function setQuotaLimit(?int $quotaLimit): self
    {
        $this->quotaLimit = $quotaLimit;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTimeInterface $lastLogin): self
    {
        $this->lastLogin = $lastLogin;

        return $this;
    }

    public function getQuotaUsed(): ?int
    {
        return $this->quotaUsed;
    }

    public function setQuotaUsed(?int $quotaUsed): self
    {
        $this->quotaUsed = $quotaUsed;

        return $this;
    }

    public function getQuotaLimit(): ?int
    {
        return $this->quotaLimit;
    }

    public function setQuotaLimit(?int $quotaLimit): self
    {
        $this->quotaLimit = $quotaLimit;

        return $this;
    }

    /**
     * Backwards-compat for older Symfony security APIs.
     */
    public function getUsername(): string
    {
        return $this->getUserIdentifier();
    }
}
