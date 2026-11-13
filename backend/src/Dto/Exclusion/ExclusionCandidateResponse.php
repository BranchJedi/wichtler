<?php

declare(strict_types=1);

namespace App\Dto\Exclusion;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['memberId', 'firstName', 'lastName', 'username', 'excluded'])]
final readonly class ExclusionCandidateResponse
{
    public function __construct(
        public int $memberId,
        public string $firstName,
        public string $lastName,
        public string $username,
        #[OA\Property(description: 'The current user has excluded this participant.')]
        public bool $excluded,
    ) {
    }
}
