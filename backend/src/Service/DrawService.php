<?php

declare(strict_types=1);

namespace App\Service;

use App\Draw\AssignmentSampler;
use App\Draw\DrawFailed;
use App\Entity\Assignment;
use App\Entity\WichtelGroup;
use App\Enum\MemberStatus;
use App\Repository\AssignmentRepository;
use App\Repository\ExclusionRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Random\Engine\Secure;
use Random\Randomizer;

final readonly class DrawService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DrawabilityChecker $drawability,
        private ExclusionRepository $exclusions,
        private AssignmentRepository $assignments,
        private AssignmentSampler $sampler,
    ) {
    }

    /**
     * Draws the group. Runs in one transaction that holds a lock on the group row, so two
     * simultaneous requests cannot both draw.
     *
     * @throws DrawFailed when the group is not ready or no assignment could be found
     */
    public function draw(WichtelGroup $group): void
    {
        $this->entityManager->wrapInTransaction(function () use ($group): void {
            $this->entityManager->lock($group, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($group);

            if ($group->isDrawed()) {
                throw new DrawFailed('Die Gruppe ist bereits ausgelost.');
            }
            if (!$this->drawability->isDrawable($group)) {
                throw new DrawFailed('Die Gruppe kann noch nicht ausgelost werden. Es braucht mindestens 3 Teilnehmer, und alle müssen genügend Wünsche erfasst haben.');
            }

            $participants = $group->getActiveMembers();
            $forbidden = $this->drawability->forbiddenReceivers($group, $participants);

            // Cryptographically secure randomness: the outcome must not be predictable.
            $receivers = $this->sampler->sample(\count($participants), $forbidden, new Randomizer(new Secure()));

            foreach ($receivers as $giver => $receiver) {
                $this->entityManager->persist(new Assignment($participants[$giver], $participants[$receiver]));
            }

            // Invitations that were not answered in time lapse with the draw.
            foreach ($group->getMembers()->toArray() as $member) {
                if (MemberStatus::Invited === $member->getStatus()) {
                    $group->removeMember($member);
                }
            }

            $group->markDrawn();
        });
    }

    /** Deletes the draw. Everything that was locked by it becomes editable again. */
    public function undo(WichtelGroup $group): void
    {
        $this->entityManager->wrapInTransaction(function () use ($group): void {
            $this->entityManager->lock($group, LockMode::PESSIMISTIC_WRITE);
            $this->assignments->deleteByGroup($group);
            $group->markUndrawn();
        });
    }
}
