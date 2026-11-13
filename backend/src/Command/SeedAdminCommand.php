<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Validator\Password;
use App\Validator\Username;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:admin:seed',
    description: 'Creates the Wichtler admin if there is none yet. Safe to run on every deployment.',
)]
final readonly class SeedAdminCommand
{
    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $hasher,
        private ValidatorInterface $validator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Defaults to the ADMIN_USERNAME environment variable')] ?string $username = null,
        #[Option(description: 'Defaults to the ADMIN_PASSWORD environment variable')] ?string $password = null,
        #[Option(name: 'first-name')] string $firstName = 'Wichtler',
        #[Option(name: 'last-name')] string $lastName = 'Admin',
    ): int {
        if ($this->users->hasAdmin()) {
            $io->writeln('A Wichtler admin already exists, nothing to do.');

            return Command::SUCCESS;
        }

        $username ??= self::env('ADMIN_USERNAME');
        $password ??= self::env('ADMIN_PASSWORD');
        if (null === $username || null === $password) {
            $io->error('Pass --username and --password or set ADMIN_USERNAME and ADMIN_PASSWORD.');

            return Command::FAILURE;
        }

        $violations = $this->validator->validate($username, new Username());
        $violations->addAll($this->validator->validate($password, new Password()));
        if (\count($violations) > 0) {
            foreach ($violations as $violation) {
                $io->error((string) $violation->getMessage());
            }

            return Command::FAILURE;
        }

        if (null !== $this->users->findOneByUsername($username)) {
            $io->error(\sprintf('The username "%s" is already taken by a regular user.', $username));

            return Command::FAILURE;
        }

        $admin = new User($firstName, $lastName, $username);
        $admin->setRoles([User::ROLE_ADMIN]);
        $admin->setPassword($this->hasher->hashPassword($admin, $password));
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success(\sprintf('Wichtler admin "%s" created with id %d.', $admin->getUsername(), $admin->getId()));

        return Command::SUCCESS;
    }

    private static function env(string $name): ?string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
