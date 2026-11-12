<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Guards the chain "Symfony -> openapi.json -> orval": the committed specification
 * the frontend is generated from must match what the code produces.
 */
final class OpenApiSpecTest extends WebTestCase
{
    public function testCommittedSpecificationIsUpToDate(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/doc.json');
        self::assertResponseIsSuccessful();

        $committed = \dirname(__DIR__, 2).'/frontend/openapi.json';
        self::assertFileExists($committed);

        $normalize = static function (string $json): array {
            $spec = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
            unset($spec['servers']);

            return $spec;
        };

        self::assertSame(
            $normalize((string) file_get_contents($committed)),
            $normalize((string) $client->getResponse()->getContent()),
            'frontend/openapi.json is outdated. Run "ddev api-generate".',
        );
    }
}
