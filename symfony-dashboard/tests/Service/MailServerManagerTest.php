<?php

namespace App\Tests\Service;

use App\Service\MailServerManager;
use App\Service\ShellCommandRunner;
use App\Entity\Domain;
use App\Entity\MailUser;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class MailServerManagerTest extends TestCase
{
    private MailServerManager $mailServerManager;
    /** @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject */
    private EntityManagerInterface $entityManager;
    /** @var LoggerInterface&\PHPUnit\Framework\MockObject\MockObject */
    private LoggerInterface $logger;
    /** @var ParameterBagInterface&\PHPUnit\Framework\MockObject\MockObject */
    private ParameterBagInterface $params;
    /** @var ShellCommandRunner&\PHPUnit\Framework\MockObject\MockObject */
    private ShellCommandRunner $commandRunner;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->params = $this->createMock(ParameterBagInterface::class);
        $this->commandRunner = $this->createMock(ShellCommandRunner::class);

        $this->params->method('get')
            ->with('mail_server_config_path')
            ->willReturn('/tmp/docker-mailserver');

        $this->mailServerManager = new MailServerManager(
            $this->params,
            $this->entityManager,
            $this->logger,
            $this->commandRunner
        );
    }

    public function testCreateDomainSuccess(): void
    {
        $domain = new Domain();
        $domain->setName('example.com');

        // Mock successful domain creation
        // 1. setup email add domain
        // 2. opendkim-genkey
        // 3. cat private
        // 4. cat public
        $this->commandRunner->expects($this->exactly(4))
            ->method('run')
            ->willReturnOnConsecutiveCalls(
                '', // setup success
                '', // keygen success
                'private_key_content',
                'public_key_content'
            );

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logger->expects($this->never())
            ->method('error');

        $result = $this->mailServerManager->createDomain($domain);

        $this->assertTrue($result);
        // $this->assertEquals('private_key_content', $domain->getDkimPrivateKey()); // Verification
    }

    public function testCreateDomainFailure(): void
    {
        $domain = new Domain();
        $domain->setName('example.com');

        // Mock domain creation failure
        $process = $this->createMock(Process::class);
        $process->method('getCommandLine')->willReturn('docker exec ...');
        $process->method('getExitCode')->willReturn(1);
        $process->method('getErrorOutput')->willReturn('Some error');
        
        $exception = new ProcessFailedException($process);

        $this->commandRunner->expects($this->once())
            ->method('run')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Failed to create domain in mail server',
                $this->callback(function ($context) {
                    return isset($context['domain']) && isset($context['error']);
                })
            );

        $result = $this->mailServerManager->createDomain($domain);

        $this->assertFalse($result);
    }

    public function testCreateUserSuccess(): void
    {
        $user = new MailUser();
        $user->setEmail('test@example.com');
        $user->setPassword('hashedpassword');

        // 1. add user
        // 2. set quota (MailUser has default quotaLimit)
        $this->commandRunner->expects($this->exactly(2))
            ->method('run');

        $this->logger->expects($this->never())
            ->method('error');

        $result = $this->mailServerManager->createUser($user);

        $this->assertTrue($result);
    }

    public function testCreateUserFailure(): void
    {
        $user = new MailUser();
        $user->setEmail('test@example.com');
        $user->setPassword('hashedpassword');

        $process = $this->createMock(Process::class);
        $process->method('getCommandLine')->willReturn('docker exec ...');
        $process->method('getExitCode')->willReturn(1);
        $process->method('getErrorOutput')->willReturn('Some error');
        
        $exception = new ProcessFailedException($process);

        $this->commandRunner->expects($this->once())
            ->method('run')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Failed to create user in mail server',
                $this->callback(function ($context) {
                    return isset($context['email']) && isset($context['error']);
                })
            );

        $result = $this->mailServerManager->createUser($user);

        $this->assertFalse($result);
    }

    public function testGetServerStatistics(): void
    {
        $this->commandRunner->expects($this->exactly(3))
            ->method('run')
            ->willReturnOnConsecutiveCalls(
                "Filesystem Size Used Avail Use% Mounted on\n/dev/sda1 10G 1G 9G 10% /",
                "Active Internet connections (servers and established)\nProto Recv-Q Send-Q Local Address Foreign Address State\ntcp 0 0 0.0.0.0:25 0.0.0.0:* LISTEN",
                "Mail queue is empty"
            );

        $stats = $this->mailServerManager->getServerStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('disk_usage', $stats);
        $this->assertArrayHasKey('connections', $stats);
        $this->assertArrayHasKey('queue', $stats);
    }
}