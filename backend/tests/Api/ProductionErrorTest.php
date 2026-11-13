<?php

declare(strict_types=1);

namespace App\Tests\Api;

/**
 * Outside of debug mode Symfony replaces exception messages by the bare status text. These
 * tests run the kernel that way, as in production, to make sure the explanations written for
 * users still arrive, and nothing else does.
 */
final class ProductionErrorTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::ensureKernelShutdown();
        $this->client = self::createClient(['debug' => false], ['HTTPS' => 'on']);
    }

    public function testRefusalsKeepTheirExplanation(): void
    {
        $anna = $this->createUser('anna');
        $group = $this->createGroup($anna);
        $this->login('anna');

        $this->client->request('POST', '/api/groups/'.$group->getId().'/draw');

        self::assertResponseStatusCodeSame(409);
        self::assertResponseHeaderSame('content-type', 'application/problem+json');
        self::assertStringContainsString('kann noch nicht ausgelost werden', $this->json()['detail']);
        self::assertSame(409, $this->json()['status']);
        self::assertArrayNotHasKey('trace', $this->json());
        self::assertArrayNotHasKey('class', $this->json());
    }

    public function testValidationErrorsKeepTheirMessages(): void
    {
        $this->createUser('anna');
        $this->login('anna');

        $this->client->jsonRequest('POST', '/api/groups', ['title' => '', 'year' => 2026, 'eventDate' => '2026-12-24', 'minWishAmount' => 3]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['title'], $this->violationPaths());
        self::assertNotEmpty($this->json()['violations'][0]['title']);
    }

    public function testInternalMessagesStayHidden(): void
    {
        $this->createUser('anna');
        $group = $this->createGroup($this->createUser('ben'));
        $this->login('anna');

        $this->client->request('GET', '/api/gibt/es/nicht');
        self::assertResponseStatusCodeSame(404);
        self::assertSame('Not Found', $this->json()['detail'], 'no echo of the requested address');

        $this->client->request('GET', '/api/groups/'.$group->getId());
        self::assertResponseStatusCodeSame(403);
        self::assertStringNotContainsString('IsGranted', (string) $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('App\\', (string) $this->client->getResponse()->getContent());
    }
}
