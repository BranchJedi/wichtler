<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Group\GroupOwnerResponse;
use App\Dto\Group\GroupResponse;
use App\Entity\User;
use App\Entity\WichtelGroup;

final readonly class GroupPresenter
{
    public function __construct(private DrawabilityChecker $drawability)
    {
    }

    public function present(WichtelGroup $group, User $viewer): GroupResponse
    {
        $isOwner = $group->getOwner() === $viewer;

        return new GroupResponse(
            id: (int) $group->getId(),
            title: $group->getTitle(),
            description: $group->getDescription(),
            year: $group->getYear(),
            budgetMin: $group->getBudgetMin(),
            budgetMax: $group->getBudgetMax(),
            currency: $group->getCurrency(),
            eventDate: $group->getEventDate()->format('Y-m-d'),
            minWishAmount: $group->getMinWishAmount(),
            drawable: $this->drawability->isDrawable($group),
            isDrawed: $group->isDrawed(),
            drawnAt: $group->getDrawnAt()?->format(\DATE_ATOM),
            owner: GroupOwnerResponse::fromUser($group->getOwner()),
            participantCount: \count($group->getActiveMembers()),
            isOwner: $isOwner,
            isMember: true === $group->getMembership($viewer)?->hasJoined(),
            isParticipant: true === $group->getMembership($viewer)?->isParticipating(),
            canManage: $isOwner || $viewer->isAdmin(),
        );
    }
}
