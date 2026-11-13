<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class AdminUserTest extends ApiTestCase
{
    private User $anna;
    private KernelBrowser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createUser('chef', [User::ROLE_ADMIN], 'Wichtler', 'Admin');
        $this->anna = $this->createUser('anna.muster', firstName: 'Anna', lastName: 'Muster');
        $this->createUser('ben', firstName: 'Ben', lastName: 'Beispiel');
        $this->admin = $this->newBrowser();
        $this->login('chef', browser: $this->admin);
    }

    private function reset(User $user): string
    {
        $this->admin->request('POST', '/api/admin/users/'.$user->getId().'/reset-password');
        self::assertSame(200, $this->admin->getResponse()->getStatusCode());

        return $this->json($this->admin)['temporaryPassword'];
    }

    public function testAdminAreaIsClosedToEveryoneElse(): void
    {
        $this->login('ben');
        foreach ([['GET', '/api/admin/users'], ['PATCH', '/api/admin/users/'.$this->anna->getId()], ['POST', '/api/admin/users/'.$this->anna->getId().'/reset-password']] as [$method, $path]) {
            $this->client->jsonRequest($method, $path, ['firstName' => 'X', 'lastName' => 'Y', 'username' => 'gekapert']);
            self::assertResponseStatusCodeSame(403, "$method $path");
        }

        $guest = $this->newBrowser();
        $guest->request('GET', '/api/admin/users');
        self::assertSame(401, $guest->getResponse()->getStatusCode());

        self::assertNotNull(self::getContainer()->get(UserRepository::class)->findOneByUsername('anna.muster'), 'nothing was changed');
    }

    public function testAdminFindsUsersByNameToTellThemTheirUsername(): void
    {
        $this->admin->request('GET', '/api/admin/users');
        self::assertSame(['anna.muster', 'ben', 'chef'], array_column($this->json($this->admin), 'username'), 'everyone, by name');
        self::assertStringNotContainsString('password', (string) $this->admin->getResponse()->getContent());
        self::assertStringNotContainsString('email', (string) $this->admin->getResponse()->getContent());

        foreach (['muster' => ['anna.muster'], 'ANNA mus' => ['anna.muster'], 'be' => ['ben'], 'anna beispiel' => [], '%' => [], '_' => []] as $query => $expected) {
            $this->admin->request('GET', '/api/admin/users?q='.rawurlencode((string) $query));
            self::assertSame($expected, array_column($this->json($this->admin), 'username'), "search for \"$query\"");
        }
    }

    public function testAdminChangesNameAndUsername(): void
    {
        $this->login('anna.muster');

        $this->admin->jsonRequest('PATCH', '/api/admin/users/'.$this->anna->getId(), ['firstName' => 'Anna', 'lastName' => 'Meier', 'username' => 'Anna.Meier']);
        self::assertSame(200, $this->admin->getResponse()->getStatusCode());
        self::assertSame(['Meier', 'anna.meier'], [$this->json($this->admin)['lastName'], $this->json($this->admin)['username']]);

        $this->client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401, 'the session under the old username is over');
        $this->client->request('POST', '/api/auth/refresh');
        self::assertResponseStatusCodeSame(401);

        $this->login('anna.meier');
        self::assertResponseIsSuccessful('the password is unchanged');
        self::assertFalse($this->json()['mustChangePassword']);
    }

    public function testChangingOnlyTheNameKeepsTheUserSignedIn(): void
    {
        $this->login('anna.muster');

        $this->admin->jsonRequest('PATCH', '/api/admin/users/'.$this->anna->getId(), ['firstName' => 'Anna-Lena', 'lastName' => 'Muster', 'username' => 'anna.muster']);

        $this->client->request('GET', '/api/me');
        self::assertResponseIsSuccessful();
        self::assertSame('Anna-Lena', $this->json()['firstName']);
    }

    public function testUsernameMustStayUniqueAndValid(): void
    {
        $this->admin->jsonRequest('PATCH', '/api/admin/users/'.$this->anna->getId(), ['firstName' => 'Anna', 'lastName' => 'Muster', 'username' => 'BEN']);
        self::assertSame(422, $this->admin->getResponse()->getStatusCode());
        self::assertSame(['username'], array_column($this->json($this->admin)['violations'], 'propertyPath'));

        $this->admin->jsonRequest('PATCH', '/api/admin/users/'.$this->anna->getId(), ['firstName' => '', 'lastName' => 'Muster', 'username' => 'a b']);
        self::assertSame(422, $this->admin->getResponse()->getStatusCode());
    }

    public function testResetGivesATemporaryPasswordAndEndsAllSessions(): void
    {
        $this->login('anna.muster');
        $otherDevice = $this->newBrowser();
        $this->login('anna.muster', browser: $otherDevice);

        $temporary = $this->reset($this->anna);

        self::assertMatchesRegularExpression('/^[a-zA-Z2-9]{4}(-[a-zA-Z2-9]{4}){3}$/', $temporary);
        self::assertTrue($this->json($this->admin)['user']['mustChangePassword']);
        self::assertStringContainsString('no-store', (string) $this->admin->getResponse()->headers->get('Cache-Control'));

        foreach ([$this->client, $otherDevice] as $device) {
            $device->request('GET', '/api/me');
            self::assertSame(401, $device->getResponse()->getStatusCode(), 'access token refused at once, not after it expires');
            $device->request('POST', '/api/auth/refresh');
            self::assertSame(401, $device->getResponse()->getStatusCode());
        }

        $fresh = $this->newBrowser();
        $this->login('anna.muster', self::PASSWORD, $fresh);
        self::assertSame(401, $fresh->getResponse()->getStatusCode(), 'the forgotten password no longer works');
        $this->login('anna.muster', $temporary, $fresh);
        self::assertSame(200, $fresh->getResponse()->getStatusCode());
        self::assertTrue($this->json($fresh)['mustChangePassword']);
    }

    public function testEveryResetProducesADifferentPassword(): void
    {
        self::assertNotSame($this->reset($this->anna), $this->reset($this->anna));
    }

    public function testTemporaryPasswordIsStoredHashedOnly(): void
    {
        $temporary = $this->reset($this->anna);

        $stored = self::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchOne('SELECT password FROM app_user WHERE id = ?', [$this->anna->getId()]);
        self::assertStringNotContainsString($temporary, $stored);
    }

    public function testTemporaryPasswordOnlyOpensThePasswordChange(): void
    {
        $group = $this->createGroup($this->anna);
        $temporary = $this->reset($this->anna);
        $this->login('anna.muster', $temporary);

        foreach ([['GET', '/api/groups'], ['GET', '/api/groups/'.$group->getId()], ['POST', '/api/groups'], ['GET', '/api/invitations'], ['PATCH', '/api/me']] as [$method, $path]) {
            $this->client->jsonRequest($method, $path, []);
            self::assertResponseStatusCodeSame(403, "$method $path");
            self::assertStringContainsString('neues Passwort', $this->json()['detail']);
        }
        $this->client->request('GET', '/api/me');
        self::assertResponseIsSuccessful('the app still learns who is signed in');

        $this->client->jsonRequest('PUT', '/api/me/password', ['currentPassword' => $temporary, 'newPassword' => 'mein-neues-passwort']);
        self::assertResponseIsSuccessful();
        self::assertFalse($this->json()['mustChangePassword']);

        $this->client->request('GET', '/api/groups/'.$group->getId());
        self::assertResponseIsSuccessful('everything is open again, on the same device');
        $this->client->request('POST', '/api/auth/refresh');
        self::assertResponseIsSuccessful();
    }

    public function testUserCanSignOutWhileThePasswordChangeIsPending(): void
    {
        $temporary = $this->reset($this->anna);
        $this->login('anna.muster', $temporary);

        $this->client->request('POST', '/api/auth/logout');

        self::assertResponseStatusCodeSame(204);
    }

    public function testAdminManagesTheOwnAccountInTheProfile(): void
    {
        $adminId = self::getContainer()->get(UserRepository::class)->findOneByUsername('chef')?->getId();

        $this->admin->request('POST', '/api/admin/users/'.$adminId.'/reset-password');
        self::assertSame(409, $this->admin->getResponse()->getStatusCode());
        $this->admin->jsonRequest('PATCH', '/api/admin/users/'.$adminId, ['firstName' => 'X', 'lastName' => 'Y', 'username' => 'neu']);
        self::assertSame(409, $this->admin->getResponse()->getStatusCode());

        $this->admin->request('GET', '/api/me');
        self::assertSame(200, $this->admin->getResponse()->getStatusCode(), 'the admin did not lock himself out');
    }

    public function testUnknownUserIsNotFound(): void
    {
        $this->admin->request('POST', '/api/admin/users/999999/reset-password');

        self::assertSame(404, $this->admin->getResponse()->getStatusCode());
    }

    public function testOwnPasswordChangeEndsOtherDevicesAtOnce(): void
    {
        $this->login('anna.muster');
        $otherDevice = $this->newBrowser();
        $this->login('anna.muster', browser: $otherDevice);

        $this->client->jsonRequest('PUT', '/api/me/password', ['currentPassword' => self::PASSWORD, 'newPassword' => 'mein-neues-passwort']);
        self::assertResponseIsSuccessful();

        $otherDevice->request('GET', '/api/me');
        self::assertSame(401, $otherDevice->getResponse()->getStatusCode(), 'not only once the access token expires');
        $this->client->request('GET', '/api/me');
        self::assertResponseIsSuccessful('this device stays signed in');
    }
}
