<?php

declare(strict_types=1);

namespace App\Service;

use App\Draw\BipartiteMatcher;
use App\Entity\GroupMember;
use App\Entity\WichtelGroup;
use App\Repository\ExclusionRepository;

/**
 * Decides whether a group can be drawn. "drawable" is never stored: it would have to be
 * kept in sync with every change to members and wishes and could go stale.
 */
final readonly class DrawabilityChecker
{
    public const int MIN_PARTICIPANTS = 3;

    public function __construct(
        private ExclusionRepository $exclusions,
        private BipartiteMatcher $matcher,
    ) {
    }

    public function isDrawable(WichtelGroup $group): bool
    {
        if ($group->isDrawed()
            || \count($group->getActiveMembers()) < self::MIN_PARTICIPANTS
            || [] !== $this->participantsMissingWishes($group)
        ) {
            return false;
        }

        // The exclusion limits guarantee that an assignment exists. This verifies it anyway,
        // as the safety net against inconsistent data.
        $participants = $group->getActiveMembers();
        $forbidden = $this->forbiddenReceivers($group, $participants);
        $adjacency = [];
        foreach ($participants as $giver => $_) {
            $adjacency[$giver] = array_values(array_diff(array_keys($participants), [$giver], $forbidden[$giver]));
        }

        return $this->matcher->hasPerfectMatching($adjacency);
    }

    /**
     * Active participants who have fewer wishes than the group asks for.
     *
     * @return list<GroupMember>
     */
    public function participantsMissingWishes(WichtelGroup $group): array
    {
        return array_values(array_filter(
            $group->getActiveMembers(),
            static fn (GroupMember $member) => $member->getWishes()->count() < $group->getMinWishAmount(),
        ));
    }

    /**
     * The exclusions of the group, translated to positions in $participants.
     *
     * @param list<GroupMember> $participants
     *
     * @return list<list<int>> for every giver position the receiver positions they must not draw
     */
    public function forbiddenReceivers(WichtelGroup $group, array $participants): array
    {
        $position = [];
        foreach ($participants as $index => $participant) {
            $position[$participant->getId()] = $index;
        }

        $forbidden = array_fill(0, \count($participants), []);
        foreach ($this->exclusions->findByGroup($group) as $exclusion) {
            $giver = $position[$exclusion->getMember()->getId()] ?? null;
            $receiver = $position[$exclusion->getExcludedMember()->getId()] ?? null;
            if (null !== $giver && null !== $receiver) {
                $forbidden[$giver][] = $receiver;
            }
        }

        return $forbidden;
    }
}
