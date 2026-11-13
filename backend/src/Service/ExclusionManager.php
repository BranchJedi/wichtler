<?php

declare(strict_types=1);

namespace App\Service;

use App\Draw\ExclusionLimit;
use App\Entity\Exclusion;
use App\Entity\GroupMember;
use App\Entity\WichtelGroup;
use App\Repository\ExclusionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Keeps the exclusions of a group within the two limits (see ExclusionLimit), both when one
 * is added and when the group shrinks.
 */
final readonly class ExclusionManager
{
    public function __construct(
        private ExclusionRepository $exclusions,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function limit(WichtelGroup $group): int
    {
        return ExclusionLimit::for(\count($group->getActiveMembers()));
    }

    /**
     * Why $member may not exclude $target, or null if they may. The answer for a person who
     * is already excluded too often is deliberately vague: it must not reveal what others chose.
     */
    public function refusal(GroupMember $member, GroupMember $target): ?string
    {
        $limit = $this->limit($member->getGroup());
        if (0 === $limit) {
            return 'In dieser Gruppe sind noch keine Ausschlüsse möglich. Dafür braucht es mindestens 4 Teilnehmer.';
        }

        $own = 0;
        $onTarget = 0;
        foreach ($this->exclusions->findByGroup($member->getGroup()) as $exclusion) {
            if ($exclusion->getMember() === $member) {
                ++$own;
            }
            if ($exclusion->getExcludedMember() === $target) {
                ++$onTarget;
            }
        }

        if ($own >= $limit) {
            return 1 === $limit
                ? 'Du kannst in dieser Gruppe höchstens 1 Person ausschliessen.'
                : \sprintf('Du kannst in dieser Gruppe höchstens %d Personen ausschliessen.', $limit);
        }
        if ($onTarget >= $limit) {
            return 'Diese Person kann nicht weiter ausgeschlossen werden.';
        }

        return null;
    }

    public function add(GroupMember $member, GroupMember $target): void
    {
        $this->entityManager->persist(new Exclusion($member, $target));
        $member->setExclusionsPruned(false);
    }

    /**
     * Call after the group lost a participant. Removes exclusions that involve someone who no
     * longer takes part, then the newest ones for as long as a limit is exceeded. Whoever loses
     * an exclusion this way is told so the next time they look.
     *
     * Because this never needs anyone's cooperation, a group cannot get stuck in a state that
     * only an unknown third person could resolve.
     */
    public function pruneAfterShrink(WichtelGroup $group): void
    {
        $limit = $this->limit($group);
        $kept = [];
        foreach ($this->exclusions->findByGroup($group) as $exclusion) {
            $member = $exclusion->getMember();
            if ($member->isParticipating() && $exclusion->getExcludedMember()->isParticipating()) {
                $kept[] = $exclusion;
                continue;
            }
            // The excluder is only told if they are still around and it was not their target who left.
            if (!$member->isParticipating()) {
                $member->setExclusionsPruned(true);
            }
            $this->entityManager->remove($exclusion);
        }

        $given = [];
        $received = [];
        foreach ($kept as $exclusion) {
            $given[$exclusion->getMember()->getId()] = ($given[$exclusion->getMember()->getId()] ?? 0) + 1;
            $received[$exclusion->getExcludedMember()->getId()] = ($received[$exclusion->getExcludedMember()->getId()] ?? 0) + 1;
        }

        // $kept is ordered newest first, so the most recent exclusions are given up first.
        foreach ($kept as $exclusion) {
            $from = $exclusion->getMember()->getId();
            $to = $exclusion->getExcludedMember()->getId();
            if ($given[$from] > $limit || $received[$to] > $limit) {
                --$given[$from];
                --$received[$to];
                $exclusion->getMember()->setExclusionsPruned(true);
                $this->entityManager->remove($exclusion);
            }
        }
    }
}
