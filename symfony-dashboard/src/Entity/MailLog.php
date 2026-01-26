<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'mail_logs')]
#[ORM\Index(name: 'idx_timestamp', columns: ['timestamp'])]
#[ORM\Index(name: 'idx_client_ip', columns: ['client_ip'])]
class MailLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null; // 'smtp_in', 'smtp_out', 'imap', 'pop3'

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sender = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $recipient = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $clientIp = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $message = null;

    #[ORM\Column]
    private int $size = 0;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $status = null; // 'delivered', 'bounced', 'rejected', 'deferred'

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $timestamp = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Domain::class)]
    private ?Domain $domain = null;

    #[ORM\ManyToOne(targetEntity: MailUser::class)]
    private ?MailUser $user = null;

    public function __construct()
    {
        $this->timestamp = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters and setters...
}
