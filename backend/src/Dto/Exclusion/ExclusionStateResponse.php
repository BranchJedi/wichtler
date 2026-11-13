<?php

declare(strict_types=1);

namespace App\Dto\Exclusion;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** The current user's own exclusions in one group. Never contains what others chose. */
#[OA\Schema(required: ['limit', 'remaining', 'participantCount', 'editable', 'prunedNotice', 'candidates'])]
final readonly class ExclusionStateResponse
{
    /** @param list<ExclusionCandidateResponse> $candidates */
    public function __construct(
        #[OA\Property(description: 'How many participants one may exclude: floor(participants / 2) - 1.')]
        public int $limit,
        #[OA\Property(description: 'How many more the current user may exclude.')]
        public int $remaining,
        public int $participantCount,
        #[OA\Property(description: 'False once the group has been drawn.')]
        public bool $editable,
        #[OA\Property(description: 'Exclusions of the current user were removed because the group shrank.')]
        public bool $prunedNotice,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: ExclusionCandidateResponse::class)), description: 'All other active participants.')]
        public array $candidates,
    ) {
    }
}
