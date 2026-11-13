<?php

declare(strict_types=1);

namespace App\Dto\Group;

use App\Entity\WichtelGroup;
use OpenApi\Attributes as OA;

#[OA\Schema(required: [
    'id', 'title', 'description', 'year', 'budgetMin', 'budgetMax', 'currency', 'eventDate', 'minWishAmount',
    'drawable', 'isDrawed', 'drawnAt', 'owner', 'participantCount', 'isOwner', 'isMember', 'isParticipant', 'canManage',
])]
final readonly class GroupResponse
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public int $year,
        public ?int $budgetMin,
        public ?int $budgetMax,
        #[OA\Property(enum: WichtelGroup::CURRENCIES)]
        public string $currency,
        #[OA\Property(format: 'date')]
        public string $eventDate,
        public int $minWishAmount,
        #[OA\Property(description: 'Computed: enough participants, everyone has enough wishes, not drawn yet.')]
        public bool $drawable,
        public bool $isDrawed,
        #[OA\Property(format: 'date-time')]
        public ?string $drawnAt,
        public GroupOwnerResponse $owner,
        #[OA\Property(description: 'Participants with status "active".')]
        public int $participantCount,
        #[OA\Property(description: 'The current user is the group admin.')]
        public bool $isOwner,
        #[OA\Property(description: 'The current user has joined the group. False when the Wichtler admin looks at a foreign group.')]
        public bool $isMember,
        #[OA\Property(description: 'The current user takes part in the draw (status "active").')]
        public bool $isParticipant,
        #[OA\Property(description: 'The current user may edit and delete the group.')]
        public bool $canManage,
    ) {
    }
}
