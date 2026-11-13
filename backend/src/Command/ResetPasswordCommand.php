<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\PasswordResetter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:reset-password',
    description: 'Gives a user a temporary password. The way back in when the Wichtler admin forgot their own.',
)]
final readonly class ResetPasswordCommand
{
    public function __construct(
        private UserRepository $users,
        private PasswordResetter $resetter,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument(description: 'Username of the account')] string $username): int
    {
        $user = $this->users->findOneByUsername($username);
        if (null === $user) {
            $io->error(\sprintf('There is no user "%s".', $username));

            return Command::FAILURE;
        }

        $temporaryPassword = $this->resetter->reset($user);
        $this->entityManager->flush();

        $io->success(\sprintf('"%s" was signed out everywhere and has to choose a new password after signing in.', $user->getUsername()));
        $io->writeln('Temporary password (shown only now): '.$temporaryPassword);

        return Command::SUCCESS;
    }
}
