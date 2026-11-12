<?php

declare(strict_types=1);

namespace App\Dto;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['status', 'database', 'time'])]
final readonly class HealthResponse
{
    public function __construct(
        #[OA\Property(enum: ['ok', 'degraded'])]
        public string $status,
        #[OA\Property(description: 'Whether the database answered a trivial query.')]
        public bool $database,
        #[OA\Property(format: 'date-time')]
        public string $time,
    ) {
    }
}
