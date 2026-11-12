<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\HealthResponse;
use Doctrine\DBAL\Connection;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    #[OA\Get(operationId: 'getHealth', summary: 'Liveness and database check', tags: ['System'])]
    #[OA\Response(response: 200, description: 'API and database are reachable', content: new Model(type: HealthResponse::class))]
    #[OA\Response(response: 503, description: 'Database is not reachable', content: new Model(type: HealthResponse::class))]
    public function __invoke(Connection $connection): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1');
            $database = true;
        } catch (\Throwable) {
            $database = false;
        }

        return new JsonResponse(
            new HealthResponse(
                status: $database ? 'ok' : 'degraded',
                database: $database,
                time: new \DateTimeImmutable()->format(\DATE_ATOM),
            ),
            $database ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
