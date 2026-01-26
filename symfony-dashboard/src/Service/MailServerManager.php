<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Entity\Domain;
use App\Entity\MailUser;

class MailServerManager
{
    private string $mailServerPath;
    private Filesystem $filesystem;

    public function __construct(
        private ParameterBagInterface $params,
        private EntityManagerInterface $entityManager
    ) {
        $this->mailServerPath = $this->params->get('mail_server_config_path');
        $this->filesystem = new Filesystem();
    }

    public function createDomain(Domain $domain): bool
    {
        try {
            // Add domain to docker-mailserver
            $process = new Process([
                'docker',
                'exec',
                'mailserver',
                'setup',
                'email',
                'add',
                'domain',
                $domain->getName()
            ]);
            $process->mustRun();

            // Generate DKIM keys if enabled
            if ($domain->isEnableDkim()) {
                $this->generateDkimKeys($domain);
            }

            return true;
        } catch (ProcessFailedException $e) {
            // Log error
            error_log($e->getMessage());
            return false;
        }
    }

    private function generateDkimKeys(Domain $domain): void
    {
        $selector = $domain->getDkimSelector() ?? 'default';

        $process = new Process([
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
        $process->mustRun();

        // Read the generated keys
        $privateKeyPath = "/tmp/docker-mailserver/opendkim/keys/{$domain->getName()}/{$selector}.private";
        $publicKeyPath = "/tmp/docker-mailserver/opendkim/keys/{$domain->getName()}/{$selector}.txt";

        $process = new Process(['docker', 'exec', 'mailserver', 'cat', $privateKeyPath]);
        $process->mustRun();
        $privateKey = $process->getOutput();

        $process = new Process(['docker', 'exec', 'mailserver', 'cat', $publicKeyPath]);
        $process->mustRun();
        $publicKey = $process->getOutput();

        $domain->setDkimPrivateKey($privateKey);
        $domain->setDkimPublicKey($publicKey);
        $domain->setDkimSelector($selector);

        $this->entityManager->flush();
    }

    public function createUser(MailUser $user): bool
    {
        try {
            $process = new Process([
                'docker',
                'exec',
                'mailserver',
                'setup',
                'email',
                'add',
                $user->getEmail(),
                $user->getPassword()
            ]);
            $process->mustRun();

            // Set quota if specified
            if ($user->getQuotaLimit()) {
                $this->setUserQuota($user);
            }

            return true;
        } catch (ProcessFailedException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    private function setUserQuota(MailUser $user): void
    {
        $quota = $user->getQuotaLimit() . 'M';
        $email = $user->getEmail();

        $process = new Process([
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
        $process->mustRun();
    }

    public function getServerStatistics(): array
    {
        try {
            // Get disk usage
            $process = new Process(['docker', 'exec', 'mailserver', 'df', '-h', '/var/mail']);
            $process->mustRun();
            $diskOutput = $process->getOutput();

            // Get active connections
            $process = new Process(['docker', 'exec', 'mailserver', 'netstat', '-an']);
            $process->mustRun();
            $connectionsOutput = $process->getOutput();

            // Get mail queue
            $process = new Process(['docker', 'exec', 'mailserver', 'mailq']);
            $process->mustRun();
            $queueOutput = $process->getOutput();

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
        $process = new Process([
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
        ]);
        $process->mustRun();
        $used = (int) trim($process->getOutput());

        $process = new Process([
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
        ]);
        $process->mustRun();
        $free = (int) trim($process->getOutput());

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

            $process = new Process([
                'docker',
                'exec',
                'mailserver',
                'tar',
                'czf',
                '-',
                '/var/mail',
                '/var/mail-state',
                '/tmp/docker-mailserver'
            ]);

            $process->setTimeout(300);
            $process->mustRun();

            file_put_contents($backupFile, $process->getOutput());

            // Also backup database
            $this->backupDatabase($backupPath);

            return true;
        } catch (ProcessFailedException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    private function backupDatabase(string $backupPath): void
    {
        $timestamp = date('Y-m-d-H-i-s');
        $dbBackupFile = "{$backupPath}/database-backup-{$timestamp}.sql.gz";

        $process = new Process([
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
        $process->mustRun();
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
}
