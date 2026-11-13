<?php

declare(strict_types=1);

namespace App\Dto\Draw;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** The group admin's checklist before the draw. */
#[OA\Schema(required: ['isDrawed', 'drawable', 'participantCount', 'minParticipants', 'minWishAmount', 'missingWishes', 'pendingInvitations', 'hasExclusions'])]
final readonly class DrawStatusResponse
{
    /** @param list<MissingWishesResponse> $missingWishes */
    public function __construct(
        public bool $isDrawed,
        public bool $drawable,
        public int $participantCount,
        public int $minParticipants,
        public int $minWishAmount,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: MissingWishesResponse::class)), description: 'Active participants who still have too few wishes.')]
        public array $missingWishes,
        #[OA\Property(description: 'Invitations by username that have not been answered. They lapse with the draw.')]
        public int $pendingInvitations,
        #[OA\Property(description: 'Somebody has set an exclusion. Not who, and not how many.')]
        public bool $hasExclusions,
    ) {
    }
}
