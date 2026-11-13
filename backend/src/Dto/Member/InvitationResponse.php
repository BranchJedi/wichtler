<?php

declare(strict_types=1);

namespace App\Dto\Member;

use App\Dto\Group\GroupOwnerResponse;
use App\Entity\User;
use App\Entity\WichtelGroup;
use OpenApi\Attributes as OA;

/** What someone who is not in the group yet gets to see before deciding to join. */
#[OA\Schema(required: [
    'groupId', 'title', 'description', 'year', 'eventDate', 'budgetMin', 'budgetMax', 'currency', 'minWishAmount',
    'owner', 'participantCount', 'memberId', 'alreadyJoined',
])]
final readonly class InvitationResponse
{
    public function __construct(
        public int $groupId,
        public string $title,
        public ?string $description,
        public int $year,
        #[OA\Property(format: 'date')]
        public string $eventDate,
        public ?int $budgetMin,
        public ?int $budgetMax,
        public string $currency,
        public int $minWishAmount,
        public GroupOwnerResponse $owner,
        public int $participantCount,
        #[OA\Property(description: 'Id of the pending invitation by username, if there is one.')]
        public ?int $memberId,
        #[OA\Property(description: 'The current user is in the group already.')]
        public bool $alreadyJoined,
    ) {
    }

    public static function fromGroup(WichtelGroup $group, User $viewer): self
    {
        $membership = $group->getMembership($viewer);
        $joined = true === $membership?->hasJoined();

        return new self(
            groupId: (int) $group->getId(),
            title: $group->getTitle(),
            description: $group->getDescription(),
            year: $group->getYear(),
            eventDate: $group->getEventDate()->format('Y-m-d'),
            budgetMin: $group->getBudgetMin(),
            budgetMax: $group->getBudgetMax(),
            currency: $group->getCurrency(),
            minWishAmount: $group->getMinWishAmount(),
            owner: GroupOwnerResponse::fromUser($group->getOwner()),
            participantCount: \count($group->getActiveMembers()),
            memberId: null !== $membership && !$joined ? $membership->getId() : null,
            alreadyJoined: $joined,
        );
    }
}
