<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User;
use App\Repository\UserRepository;

final class AuthTest extends ApiTestCase
{
    private const array REGISTRATION = [
        'firstName' => 'Anna',
        'lastName' => 'Muster',
        'username' => 'Anna.Muster',
        'password' => self::PASSWORD,
    ];

    public function testRegistrationCreatesTheUserAndSignsIn(): void
    {
        $this->client->jsonRequest('POST', '/api/auth/register', self::REGISTRATION);

        self::assertResponseStatusCodeSame(201);
        $body = $this->json();
        self::assertSame('anna.muster', $body['username'], 'usernames are stored in lower case');
        self::assertFalse($body['isAdmin']);
        self::assertArrayNotHasKey('email', $body);
        self::assertArrayNotHasKey('token', $body);
        self::assertArrayNotHasKey('refresh_token', $body);

        $this->client->request('GET', '/api/me');
        self::assertResponseIsSuccessful();
        self::assertSame($body, $this->json());

        $user = self::getContainer()->get(UserRepository::class)->findOneByUsername('anna.muster');
        self::assertInstanceOf(User::class, $user);
        self::assertNull($user->getEmail());
        self::assertNotSame(self::PASSWORD, $user->getPassword());
    }

    public function testSessionCookiesAreNotReadableByScripts(): void
    {
        $this->client->jsonRequest('POST', '/api/auth/register', self::REGISTRATION);

        $cookies = [];
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie;
        }

        self::assertEqualsCanonicalizing(['BEARER', 'refresh_token'], array_keys($cookies));
        foreach ($cookies as $name => $cookie) {
            self::assertTrue($cookie->isHttpOnly(), $name);
            self::assertTrue($cookie->isSecure(), $name);
            self::assertSame('strict', $cookie->getSameSite(), $name);
        }
        self::assertSame('/api', $cookies['BEARER']->getPath());
        self::assertSame('/api/auth', $cookies['refresh_token']->getPath());
    }

    public function testUsernamesAreUniqueIgnoringCase(): void
    {
        $this->createUser('anna.muster');

        $this->client->jsonRequest('POST', '/api/auth/register', self::REGISTRATION);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type', 'application/problem+json');
        self::assertSame(['username'], $this->violationPaths());
    }

    public function testRegistrationValidatesItsInput(): void
    {
        $this->client->jsonRequest('POST', '/api/auth/register', [
            'firstName' => '',
            'lastName' => 'Muster',
            'username' => 'a b',
            'password' => 'too-short',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['firstName', 'username', 'password'], $this->violationPaths());
    }

    public function testRegistrationIsThrottledPerClient(): void
    {
        for ($i = 0; $i < 30; ++$i) {
            $this->client->jsonRequest('POST', '/api/auth/register', ['username' => 'user-'.$i] + self::REGISTRATION);
            self::assertResponseStatusCodeSame(201);
        }

        $this->client->jsonRequest('POST', '/api/auth/register', ['username' => 'one-too-many'] + self::REGISTRATION);

        self::assertResponseStatusCodeSame(429);
        self::assertNull(self::getContainer()->get(UserRepository::class)->findOneByUsername('one-too-many'));
    }

    public function testLoginIgnoresTheCaseOfTheUsername(): void
    {
        $this->createUser('anna.muster');

        $this->login('ANNA.Muster');

        self::assertResponseIsSuccessful();
        self::assertSame('anna.muster', $this->json()['username']);
    }

    public function testLoginRejectsAWrongPassword(): void
    {
        $this->createUser('anna.muster');

        $this->login('anna.muster', 'not-the-password');
        self::assertResponseStatusCodeSame(401);

        $this->client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginIsLockedAfterRepeatedFailures(): void
    {
        $this->createUser('anna.muster');

        for ($i = 0; $i < 5; ++$i) {
            $this->login('anna.muster', 'not-the-password');
        }

        $this->login('anna.muster');
        self::assertResponseStatusCodeSame(401, 'even the correct password is refused while locked');
    }

    /**
     * A guest opening the app triggers a session refresh that fails. Such failures, and
     * requests with a stale token, must never count as failed logins: otherwise a few page
     * views would lock everybody at that address out, signed-in users included.
     */
    public function testFailedRefreshesAndStaleTokensDoNotThrottleAnybody(): void
    {
        $this->createUser('anna.muster');
        $this->login('anna.muster');

        $guest = $this->newBrowser();
        for ($i = 0; $i < 40; ++$i) {
            $guest->request('POST', '/api/auth/refresh');
            self::assertSame(401, $guest->getResponse()->getStatusCode());
            $guest->request('GET', '/api/me', server: ['HTTP_COOKIE' => 'BEARER=not-a-token']);
            self::assertSame(401, $guest->getResponse()->getStatusCode());
        }

        $this->client->request('GET', '/api/me');
        self::assertResponseIsSuccessful('a signed-in user at the same address keeps working');

        $this->login('anna.muster', browser: $guest);
        self::assertSame(200, $guest->getResponse()->getStatusCode(), 'and logging in is still possible');
    }

    public function testManyWrongPasswordsFromOneAddressLockThatAddress(): void
    {
        for ($i = 0; $i < 25; ++$i) {
            $this->login('guess-'.$i, 'not-the-password');
            self::assertResponseStatusCodeSame(401);
        }
        $this->createUser('anna.muster');

        $this->login('anna.muster');

        self::assertResponseStatusCodeSame(401, 'the address is locked for every username');
    }

    public function testSuccessfulLoginClearsTheFailuresOfThatUser(): void
    {
        $this->createUser('anna.muster');
        for ($i = 0; $i < 4; ++$i) {
            $this->login('anna.muster', 'not-the-password');
        }
        $this->login('anna.muster');
        self::assertResponseIsSuccessful();

        for ($i = 0; $i < 4; ++$i) {
            $this->login('anna.muster', 'not-the-password');
        }
        $this->login('anna.muster');
        self::assertResponseIsSuccessful('four more failures are allowed again');
    }

    /** After a reset or a rename the browser still holds a cookie that is no longer good. */
    public function testAStaleSessionCookieDoesNotGetInTheWayOfSigningIn(): void
    {
        $this->createUser('anna.muster');
        $stale = ['HTTP_COOKIE' => 'BEARER=eyJhbGciOiJSUzI1NiJ9.e30.not-valid'];

        $this->client->jsonRequest('POST', '/api/auth/login', ['username' => 'anna.muster', 'password' => self::PASSWORD], $stale);
        self::assertResponseIsSuccessful();

        $other = $this->newBrowser();
        $other->jsonRequest('POST', '/api/auth/register', ['username' => 'neu'] + self::REGISTRATION, $stale);
        self::assertSame(201, $other->getResponse()->getStatusCode());

        $third = $this->newBrowser();
        $third->request('POST', '/api/auth/logout', server: $stale);
        self::assertSame(204, $third->getResponse()->getStatusCode());
    }

    public function testProtectedEndpointsRequireASession(): void
    {
        $this->client->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }

    public function testARefreshTokenWorksExactlyOnce(): void
    {
        $this->createUser('anna.muster');
        $this->login('anna.muster');
        $usedToken = $this->client->getCookieJar()->get('refresh_token', '/api/auth')?->getValue();
        self::assertNotEmpty($usedToken);

        $this->client->request('POST', '/api/auth/refresh');
        self::assertResponseIsSuccessful();
        self::assertSame('anna.muster', $this->json()['username']);
        self::assertNotSame($usedToken, $this->client->getCookieJar()->get('refresh_token', '/api/auth')?->getValue());

        $thief = $this->newBrowser();
        $thief->request('POST', '/api/auth/refresh', server: ['HTTP_COOKIE' => 'refresh_token='.$usedToken]);
        self::assertSame(401, $thief->getResponse()->getStatusCode());
    }

    public function testLogoutEndsTheSession(): void
    {
        $this->createUser('anna.muster');
        $this->login('anna.muster');

        $this->client->request('POST', '/api/auth/logout');
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
        $this->client->request('POST', '/api/auth/refresh');
        self::assertResponseStatusCodeSame(401);
    }

    public function testLogoutWithoutASessionSucceeds(): void
    {
        $this->client->request('POST', '/api/auth/logout');

        self::assertResponseStatusCodeSame(204);
    }

    public function testUnknownApiRoutesAnswerWithProblemJson(): void
    {
        $this->client->request('GET', '/api/auth/does-not-exist');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type', 'application/problem+json');
    }
}
