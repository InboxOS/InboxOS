<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;
use App\Entity\Domain;
use App\Entity\MailUser;

class MailServerManager
{
    private string $mailServerPath;
    private Filesystem $filesystem;

    public function __construct(
        private ParameterBagInterface $params,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private ShellCommandRunner $commandRunner
    ) {
        $this->mailServerPath = $this->params->get('mail_server_config_path');
        $this->filesystem = new Filesystem();
    }

    public function createDomain(Domain $domain): bool
    {
        try {
            // Add domain to docker-mailserver
            $this->commandRunner->run([
                'docker',
                'exec',
                'mailserver',
                'setup',
                'email',
                'add',
                'domain',
                $domain->getName()
            ]);

            // Generate DKIM keys if enabled
            if ($domain->isEnableDkim()) {
                $this->generateDkimKeys($domain);
            }

            return true;
        } catch (ProcessFailedException $e) {
            $this->logger->error('Failed to create domain in mail server', [
                'domain' => $domain->getName(),
                'error' => $e->getMessage(),
                'command' => $e->getProcess()->getCommandLine()
            ]);
            return false;
        }
    }

    private function generateDkimKeys(Domain $domain): void
    {
        $selector = $domain->getDkimSelector() ?? 'default';

        $this->commandRunner->run([
            'docker',
            'exec',
            'mailserver',
            'opendkim-genkey',
            '-b',
            '2048',
            '-h',
            'rsa-sha256',
            '-r',
            '-s',
            $selector,
            '-d',
            $domain->getName()
        ]);

        // Read the generated keys
        $privateKeyPath = "/tmp/docker-mailserver/opendkim/keys/{$domain->getName()}/{$selector}.private";
        $publicKeyPath = "/tmp/docker-mailserver/opendkim/keys/{$domain->getName()}/{$selector}.txt";

        $privateKey = $this->commandRunner->run(['docker', 'exec', 'mailserver', 'cat', $privateKeyPath]);
        $publicKey = $this->commandRunner->run(['docker', 'exec', 'mailserver', 'cat', $publicKeyPath]);

        $domain->setDkimPrivateKey($privateKey);
        $domain->setDkimPublicKey($publicKey);
        $domain->setDkimSelector($selector);

        $this->entityManager->flush();
    }

    public function createUser(MailUser $user): bool
    {
        try {
            $this->commandRunner->run([
                'docker',
                'exec',
                'mailserver',
                'setup',
                'email',
                'add',
                $user->getEmail(),
                $user->getPassword()
            ]);

            // Set quota if specified
            if ($user->getQuotaLimit()) {
                $this->setUserQuota($user);
            }

            return true;
        } catch (ProcessFailedException $e) {
            $this->logger->error('Failed to create user in mail server', [
                'email' => $user->getEmail(),
                'error' => $e->getMessage(),
                'command' => $e->getProcess()->getCommandLine()
            ]);
            return false;
        }
    }

    private function setUserQuota(MailUser $user): void
    {
        $quota = $user->getQuotaLimit() . 'M';
        $email = $user->getEmail();

        $this->commandRunner->run([
            'docker',
            'exec',
            'mailserver',
            'setup',
            'config',
            'dovecot-quotas',
            'user',
            $email,
            $quota
        ]);
    }

    public function getServerStatistics(): array
    {
        try {
            // Get disk usage
            $diskOutput = $this->commandRunner->run(['docker', 'exec', 'mailserver', 'df', '-h', '/var/mail']);

            // Get active connections
            $connectionsOutput = $this->commandRunner->run(['docker', 'exec', 'mailserver', 'netstat', '-an']);

            // Get mail queue
            $queueOutput = $this->commandRunner->run(['docker', 'exec', 'mailserver', 'mailq']);

            return [
                'disk_usage' => $this->parseDiskUsage($diskOutput),
                'connections' => $this->parseConnections($connectionsOutput),
                'queue' => $this->parseMailQueue($queueOutput),
            ];
        } catch (ProcessFailedException $e) {
            return [
                'disk_usage' => ['error' => $e->getMessage()],
                'connections' => 0,
                'queue' => 0,
            ];
        }
    }

    public function getStorageUsage(): array
    {
        $used = (int) trim($this->commandRunner->run([
            'docker',
            'exec',
            'mailserver',
            'du',
            '-sb',
            '/var/mail',
            '2>/dev/null',
            '||',
            'echo',
            '0'
        ]));

        $free = (int) trim($this->commandRunner->run([
            'docker',
            'exec',
            'mailserver',
            'df',
            '-B1',
            '/var/mail',
            '|',
            'tail',
            '-1',
            '|',
            'awk',
            '\'{print $4}\''
        ]));

        return [
            'used' => $used,
            'free' => $free,
            'total' => $used + $free,
        ];
    }

    public function getTrafficData(): array
    {
        // This would typically query a database of mail logs
        // For now, return mock data
        return [
            'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'incoming' => [120, 150, 180, 130, 200, 90, 110],
            'outgoing' => [80, 100, 120, 90, 150, 70, 95],
        ];
    }

    public function backupServer(string $backupPath): bool
    {
        try {
            $timestamp = date('Y-m-d-H-i-s');
            $backupFile = "{$backupPath}/mailserver-backup-{$timestamp}.tar.gz";

            $output = $this->commandRunner->run([
                'docker',
                'exec',
                'mailserver',
                'tar',
                'czf',
                '-',
                '/var/mail',
                '/var/mail-state',
                '/tmp/docker-mailserver'
            ], 300);

            file_put_contents($backupFile, $output);

            // Also backup database
            $this->backupDatabase($backupPath);

            return true;
        } catch (ProcessFailedException $e) {
            $this->logger->error('Failed to backup mail server', [
                'backup_path' => $backupPath,
                'error' => $e->getMessage(),
                'command' => $e->getProcess()->getCommandLine()
            ]);
            return false;
        }
    }

    private function backupDatabase(string $backupPath): void
    {
        $timestamp = date('Y-m-d-H-i-s');
        $dbBackupFile = "{$backupPath}/database-backup-{$timestamp}.sql.gz";

        $this->commandRunner->run([
            'mysqldump',
            '--host=mysql',
            '--user=mailadmin',
            '--password=' . $_ENV['MYSQL_PASSWORD'],
            'mail_dashboard',
            '|',
            'gzip',
            '>',
            $dbBackupFile
        ]);
    }

    public function generateMtaStsPolicy(Domain $domain): string
    {
        return sprintf(
            'version: STSv1
mode: enforce
max_age: 604800
mx: mail.%s
mx: mail2.%s',
            $domain->getName(),
            $domain->getName()
        );
    }

    public function generateDmarcPolicy(Domain $domain): string
    {
        return sprintf(
            'v=DMARC1; p=reject; rua=mailto:dmarc@%s; ruf=mailto:dmarc@%s; fo=1',
            $domain->getName(),
            $domain->getName()
        );
    }

    public function generateSpfRecord(Domain $domain): string
    {
        return sprintf(
            'v=spf1 mx a ptr ip4:%s ip6:%s -all',
            $this->getServerIp(),
            $this->getServerIpv6()
        );
    }

    private function getServerIp(): string
    {
        // This should be your server's public IP
        return $_ENV['SERVER_IP'] ?? 'your.server.ip';
    }

    private function getServerIpv6(): string
    {
        return $_ENV['SERVER_IPV6'] ?? 'your:server:ipv6';
    }

    private function parseDiskUsage(string $output): array
    {
        $lines = explode("\n", trim($output));
        if (count($lines) < 2) {
            return [];
        }

        $parts = preg_split('/\s+/', $lines[1]);

        return [
            'total' => $parts[1] ?? '0',
            'used' => $parts[2] ?? '0',
            'free' => $parts[3] ?? '0',
            'percent' => $parts[4] ?? '0%',
        ];
    }

    private function parseConnections(string $output): int
    {
        return substr_count($output, 'ESTABLISHED');
    }

    private function parseMailQueue(string $output): int
    {
        if (str_contains($output, 'Mail queue is empty')) {
            return 0;
        }

        $lines = explode("\n", trim($output));
        $count = 0;

        foreach ($lines as $line) {
            // Match Postfix queue ID line format (begins with ID)
            if (preg_match('/^[A-F0-9]+\s/', $line)) {
                $count++;
            }
        }

        return $count;
    }
}
