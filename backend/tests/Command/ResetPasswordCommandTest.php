<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Tests\Api\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class ResetPasswordCommandTest extends ApiTestCase
{
    private function command(): CommandTester
    {
        return new CommandTester(new Application(self::$kernel)->find('app:user:reset-password'));
    }

    public function testGivesTheAdminAWayBackIn(): void
    {
        $this->createUser('chef', ['ROLE_ADMIN']);
        $this->login('chef');

        $command = $this->command();
        self::assertSame(0, $command->execute(['username' => 'Chef']));
        self::assertSame(1, preg_match('/([a-zA-Z2-9]{4}(?:-[a-zA-Z2-9]{4}){3})/', $command->getDisplay(), $match));

        $this->client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401, 'existing sessions are over');

        $fresh = $this->newBrowser();
        $this->login('chef', self::PASSWORD, $fresh);
        self::assertSame(401, $fresh->getResponse()->getStatusCode());
        $this->login('chef', $match[1], $fresh);
        self::assertSame(200, $fresh->getResponse()->getStatusCode());
        self::assertTrue($this->json($fresh)['mustChangePassword']);
    }

    public function testUnknownUserFails(): void
    {
        $command = $this->command();

        self::assertSame(1, $command->execute(['username' => 'niemand']));
        self::assertStringContainsString('niemand', $command->getDisplay());
    }
}
