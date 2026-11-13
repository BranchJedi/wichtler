<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SeedAdminCommandTest extends KernelTestCase
{
    private CommandTester $command;

    protected function setUp(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $connection->executeStatement('DELETE FROM wichtel_group');
        $connection->executeStatement('DELETE FROM app_user');
        $this->command = new CommandTester(new Application(self::$kernel)->find('app:admin:seed'));
    }

    public function testCreatesTheAdmin(): void
    {
        $exitCode = $this->command->execute(['--username' => 'Chef', '--password' => 'a-long-admin-password']);

        self::assertSame(0, $exitCode);
        $admin = self::getContainer()->get(UserRepository::class)->findOneByUsername('chef');
        self::assertInstanceOf(User::class, $admin);
        self::assertTrue($admin->isAdmin());
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($admin, 'a-long-admin-password'));
    }

    public function testDoesNothingWhenAnAdminExists(): void
    {
        $this->command->execute(['--username' => 'chef', '--password' => 'a-long-admin-password']);

        $exitCode = $this->command->execute(['--username' => 'second', '--password' => 'another-long-password']);

        self::assertSame(0, $exitCode);
        self::assertNull(self::getContainer()->get(UserRepository::class)->findOneByUsername('second'));
        self::assertSame(1, self::getContainer()->get(UserRepository::class)->count());
    }

    public function testFailsWithoutCredentials(): void
    {
        self::assertSame(1, $this->command->execute([]));
        self::assertSame(0, self::getContainer()->get(UserRepository::class)->count());
    }

    public function testRefusesAWeakPassword(): void
    {
        self::assertSame(1, $this->command->execute(['--username' => 'chef', '--password' => 'short']));
        self::assertSame(0, self::getContainer()->get(UserRepository::class)->count());
    }
}
