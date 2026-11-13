<?php

declare(strict_types=1);

namespace App\Dto\Draw;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['memberId', 'firstName', 'lastName', 'username', 'wishCount'])]
final readonly class MissingWishesResponse
{
    public function __construct(
        public int $memberId,
        public string $firstName,
        public string $lastName,
        public string $username,
        #[OA\Property(description: 'How many wishes the participant has entered so far. Never the wishes themselves.')]
        public int $wishCount,
    ) {
    }
}
