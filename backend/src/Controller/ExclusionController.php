<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Exclusion\ExclusionCandidateResponse;
use App\Dto\Exclusion\ExclusionStateResponse;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Entity\WichtelGroup;
use App\Http\UserFacingHttpException;
use App\Repository\ExclusionRepository;
use App\Security\Voter\GroupVoter;
use App\Service\ExclusionManager;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "I do not want to draw this person." Everybody manages only their own exclusions, and
 * nobody, the group admin and the Wichtler admin included, can read those of someone else.
 */
#[OA\Tag(name: 'Exclusions')]
#[IsGranted(GroupVoter::VIEW, subject: 'group')]
#[OA\Response(response: 401, description: 'Not signed in', content: new OA\JsonContent(ref: '#/components/schemas/AuthError'))]
#[OA\Response(response: 403, description: 'Not a member of this group', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
#[OA\Response(response: 404, description: 'No such group or participant', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
#[OA\Response(response: 409, description: 'The current user does not take part, or the group has been drawn', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
final readonly class ExclusionController
{
    private const string GROUP_NOT_FOUND = 'Diese Wichtelgruppe gibt es nicht.';

    public function __construct(
        private ExclusionManager $manager,
        private ExclusionRepository $exclusions,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/groups/{id}/exclusions', name: 'api_exclusions_get', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(operationId: 'getMyExclusions', summary: 'The current user\'s own exclusions and how many are allowed')]
    #[OA\Response(response: 200, description: 'Own exclusions', content: new Model(type: ExclusionStateResponse::class))]
    public function get(#[CurrentUser] User $user, #[MapEntity(message: self::GROUP_NOT_FOUND)] WichtelGroup $group): JsonResponse
    {
        return $this->state($this->participant($group, $user));
    }

    #[Route('/api/groups/{id}/exclusions/{memberId}', name: 'api_exclusions_add', requirements: ['id' => '\d+', 'memberId' => '\d+'], methods: ['PUT'])]
    #[OA\Put(
        operationId: 'excludeMember',
        summary: 'Exclude a participant from one\'s own draw',
        description: 'Refused when the current user has reached the limit, or the participant is already excluded by as many others as the limit allows.',
    )]
    #[OA\Response(response: 200, description: 'Own exclusions after the change', content: new Model(type: ExclusionStateResponse::class))]
    #[OA\Response(response: 422, description: 'One of the two limits would be exceeded', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function add(#[CurrentUser] User $user, #[MapEntity(message: self::GROUP_NOT_FOUND)] WichtelGroup $group, int $memberId): JsonResponse
    {
        $member = $this->participant($group, $user);
        $this->assertNotDrawn($group);
        $target = $this->target($group, $member, $memberId);

        if (!$this->isExcluded($member, $target)) {
            $refusal = $this->manager->refusal($member, $target);
            if (null !== $refusal) {
                throw UserFacingHttpException::unprocessable($refusal);
            }
            $this->manager->add($member, $target);
            $this->entityManager->flush();
        }

        return $this->state($member);
    }

    #[Route('/api/groups/{id}/exclusions/{memberId}', name: 'api_exclusions_remove', requirements: ['id' => '\d+', 'memberId' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(operationId: 'includeMember', summary: 'Take back an exclusion')]
    #[OA\Response(response: 200, description: 'Own exclusions after the change', content: new Model(type: ExclusionStateResponse::class))]
    public function remove(#[CurrentUser] User $user, #[MapEntity(message: self::GROUP_NOT_FOUND)] WichtelGroup $group, int $memberId): JsonResponse
    {
        $member = $this->participant($group, $user);
        $this->assertNotDrawn($group);

        foreach ($this->exclusions->findByMember($member) as $exclusion) {
            if ($exclusion->getExcludedMember()->getId() === $memberId) {
                $this->entityManager->remove($exclusion);
            }
        }
        $this->entityManager->flush();

        return $this->state($member);
    }

    #[Route('/api/groups/{id}/exclusions/notice', name: 'api_exclusions_dismiss_notice', requirements: ['id' => '\d+'], methods: ['DELETE'], priority: 1)]
    #[OA\Delete(operationId: 'dismissExclusionNotice', summary: 'Acknowledge the hint that exclusions were removed')]
    #[OA\Response(response: 200, description: 'Own exclusions', content: new Model(type: ExclusionStateResponse::class))]
    public function dismissNotice(#[CurrentUser] User $user, #[MapEntity(message: self::GROUP_NOT_FOUND)] WichtelGroup $group): JsonResponse
    {
        $member = $this->participant($group, $user);
        $member->setExclusionsPruned(false);
        $this->entityManager->flush();

        return $this->state($member);
    }

    private function state(GroupMember $member): JsonResponse
    {
        $group = $member->getGroup();
        $excludedIds = array_map(static fn ($exclusion) => $exclusion->getExcludedMember()->getId(), $this->exclusions->findByMember($member));
        $limit = $this->manager->limit($group);

        $candidates = [];
        foreach ($group->getActiveMembers() as $other) {
            if ($other !== $member) {
                $candidates[] = new ExclusionCandidateResponse(
                    memberId: (int) $other->getId(),
                    firstName: $other->getUser()->getFirstName(),
                    lastName: $other->getUser()->getLastName(),
                    username: $other->getUser()->getUsername(),
                    excluded: \in_array($other->getId(), $excludedIds, true),
                );
            }
        }
        usort($candidates, static fn (ExclusionCandidateResponse $a, ExclusionCandidateResponse $b) => [mb_strtolower($a->firstName), mb_strtolower($a->lastName)] <=> [mb_strtolower($b->firstName), mb_strtolower($b->lastName)]);

        return new JsonResponse(new ExclusionStateResponse(
            limit: $limit,
            remaining: max(0, $limit - \count($excludedIds)),
            participantCount: \count($group->getActiveMembers()),
            editable: !$group->isDrawed(),
            prunedNotice: $member->hasExclusionsPruned(),
            candidates: $candidates,
        ));
    }

    /** Exclusions only make sense for someone who is drawn. */
    private function participant(WichtelGroup $group, User $user): GroupMember
    {
        $member = $group->getMembership($user);
        if (null === $member || !$member->isParticipating()) {
            throw UserFacingHttpException::conflict('Du nimmst in dieser Gruppe nicht an der Auslosung teil.');
        }

        return $member;
    }

    private function target(WichtelGroup $group, GroupMember $member, int $memberId): GroupMember
    {
        foreach ($group->getActiveMembers() as $candidate) {
            if ($candidate->getId() === $memberId && $candidate !== $member) {
                return $candidate;
            }
        }

        throw UserFacingHttpException::notFound('Diese Person nimmt in dieser Gruppe nicht an der Auslosung teil.');
    }

    private function isExcluded(GroupMember $member, GroupMember $target): bool
    {
        foreach ($this->exclusions->findByMember($member) as $exclusion) {
            if ($exclusion->getExcludedMember() === $target) {
                return true;
            }
        }

        return false;
    }

    private function assertNotDrawn(WichtelGroup $group): void
    {
        if ($group->isDrawed()) {
            throw UserFacingHttpException::conflict('Die Gruppe ist ausgelost. Ausschlüsse können nicht mehr geändert werden.');
        }
    }
}
