<?php

namespace App\Command;

use App\Entity\MailUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Admin email')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Admin password')
            ->addOption('update', null, InputOption::VALUE_NONE, 'Update the user if it already exists')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = (string) $input->getOption('email');
        $password = (string) $input->getOption('password');
        $update = (bool) $input->getOption('update');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('You must provide a valid --email.');
            return Command::FAILURE;
        }
        if ($password === '' || strlen($password) < 8) {
            $io->error('You must provide a --password (min 8 chars).');
            return Command::FAILURE;
        }

        $existing = $this->em->getRepository(MailUser::class)->findOneBy(['email' => $email]);
        if ($existing) {
            if (!$update) {
                $io->warning('User already exists, no changes made. Pass --update to reset password/roles.');
                return Command::SUCCESS;
            }

            $existing->setIsActive(true);
            $existing->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
            $existing->setPassword($this->passwordHasher->hashPassword($existing, $password));

            $this->em->flush();

            $io->success("Updated admin user: {$email}");
            return Command::SUCCESS;
        }

        $user = new MailUser();
        $user->setEmail($email);
        $user->setIsActive(true);
        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $io->success("Created admin user: {$email}");
        return Command::SUCCESS;
    }
}

