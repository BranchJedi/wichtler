<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class MeTest extends ApiTestCase
{
    public function testAdminFlagIsReported(): void
    {
        $this->createUser('chef', [User::ROLE_ADMIN]);
        $this->login('chef');

        $this->client->request('GET', '/api/me');

        self::assertTrue($this->json()['isAdmin']);
    }

    public function testProfileCanBeChanged(): void
    {
        $this->createUser('anna.muster');
        $this->login('anna.muster');

        $this->client->jsonRequest('PATCH', '/api/me', ['firstName' => ' Anna-Lena ', 'lastName' => 'Meier', 'username' => 'anna.muster']);

        self::assertResponseIsSuccessful();
        self::assertSame('Anna-Lena', $this->json()['firstName']);
        self::assertSame('Meier', $this->json()['lastName']);
    }

    public function testChangingTheUsernameKeepsTheSessionAlive(): void
    {
        $this->createUser('anna.muster');
        $this->login('anna.muster');

        $this->client->jsonRequest('PATCH', '/api/me', ['firstName' => 'Anna', 'lastName' => 'Muster', 'username' => 'Anna.Meier']);
        self::assertResponseIsSuccessful();
        self::assertSame('anna.meier', $this->json()['username']);

        $this->client->request('GET', '/api/me');
        self::assertResponseIsSuccessful();
        $this->client->request('POST', '/api/auth/refresh');
        self::assertResponseIsSuccessful();

        $this->login('anna.muster', browser: $other = $this->newBrowser());
        self::assertSame(401, $other->getResponse()->getStatusCode(), 'the old username is gone');
    }

    public function testUsernameOfAnotherUserIsRefused(): void
    {
        $this->createUser('anna.muster');
        $this->createUser('ben.beispiel');
        $this->login('anna.muster');

        $this->client->jsonRequest('PATCH', '/api/me', ['firstName' => 'Anna', 'lastName' => 'Muster', 'username' => 'Ben.Beispiel']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['username'], $this->violationPaths());
    }

    public function testPasswordChangeNeedsTheCurrentPassword(): void
    {
        $this->createUser('anna.muster');
        $this->login('anna.muster');

        $this->client->jsonRequest('PUT', '/api/me/password', ['currentPassword' => 'guessed-wrong', 'newPassword' => 'a-brand-new-password']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['currentPassword'], $this->violationPaths());
    }

    public function testNewPasswordMustBeLongEnough(): void
    {
        $this->createUser('anna.muster');
        $this->login('anna.muster');

        $this->client->jsonRequest('PUT', '/api/me/password', ['currentPassword' => self::PASSWORD, 'newPassword' => 'short']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['newPassword'], $this->violationPaths());
    }

    public function testPasswordChangeSignsOutOtherDevicesButNotThisOne(): void
    {
        $this->createUser('anna.muster');
        $this->login('anna.muster');
        $otherDevice = $this->newBrowser();
        $this->login('anna.muster', browser: $otherDevice);

        $this->client->jsonRequest('PUT', '/api/me/password', ['currentPassword' => self::PASSWORD, 'newPassword' => 'a-brand-new-password']);
        self::assertResponseIsSuccessful();

        $this->client->request('POST', '/api/auth/refresh');
        self::assertResponseIsSuccessful();

        $otherDevice->request('POST', '/api/auth/refresh');
        self::assertSame(401, $otherDevice->getResponse()->getStatusCode());

        $fresh = $this->newBrowser();
        $this->login('anna.muster', self::PASSWORD, $fresh);
        self::assertSame(401, $fresh->getResponse()->getStatusCode(), 'old password no longer works');
        $this->login('anna.muster', 'a-brand-new-password', $fresh);
        self::assertSame(200, $fresh->getResponse()->getStatusCode());
    }

    public function testPasswordChangeClearsTheResetFlag(): void
    {
        $user = $this->createUser('anna.muster');
        $user->setMustChangePassword(true);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->login('anna.muster');
        self::assertTrue($this->json()['mustChangePassword']);

        $this->client->jsonRequest('PUT', '/api/me/password', ['currentPassword' => self::PASSWORD, 'newPassword' => 'a-brand-new-password']);

        self::assertFalse($this->json()['mustChangePassword']);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertFalse(self::getContainer()->get(UserRepository::class)->findOneByUsername('anna.muster')?->mustChangePassword());
    }
}
