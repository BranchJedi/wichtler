<?php

declare(strict_types=1);

namespace App\Dto\Member;

use App\Entity\GroupMember;
use App\Entity\User;
use App\Enum\MemberStatus;
use OpenApi\Attributes as OA;

#[OA\Schema(required: ['id', 'firstName', 'lastName', 'username', 'status', 'isOwner', 'isMe'])]
final readonly class MemberResponse
{
    public function __construct(
        #[OA\Property(description: 'Id of the membership, not of the user.')]
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $username,
        #[OA\Property(enum: ['invited', 'active', 'inactive'])]
        public string $status,
        #[OA\Property(description: 'This member is the group admin.')]
        public bool $isOwner,
        public bool $isMe,
    ) {
    }

    public static function fromMember(GroupMember $member, User $viewer): self
    {
        $user = $member->getUser();

        return new self(
            id: (int) $member->getId(),
            firstName: $user->getFirstName(),
            lastName: $user->getLastName(),
            username: $user->getUsername(),
            status: $member->getStatus()->value,
            isOwner: $member->isOwner(),
            isMe: $user === $viewer,
        );
    }

    /** Group admin first, then participants, then inactive, then pending invitations; by name within each. */
    public static function order(GroupMember $a, GroupMember $b): int
    {
        $rank = static fn (GroupMember $m): int => match (true) {
            $m->isOwner() => 0,
            MemberStatus::Active === $m->getStatus() => 1,
            MemberStatus::Inactive === $m->getStatus() => 2,
            default => 3,
        };
        $name = static fn (GroupMember $m): string => mb_strtolower($m->getUser()->getFirstName().' '.$m->getUser()->getLastName());

        return [$rank($a), $name($a)] <=> [$rank($b), $name($b)];
    }
}
