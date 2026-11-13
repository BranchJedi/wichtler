<?php

declare(strict_types=1);

namespace App\Controller;

use App\Draw\DrawFailed;
use App\Dto\Draw\AssignmentResponse;
use App\Dto\Draw\DrawStatusResponse;
use App\Dto\Draw\MissingWishesResponse;
use App\Dto\Group\GroupResponse;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Entity\WichtelGroup;
use App\Enum\MemberStatus;
use App\Http\UserFacingHttpException;
use App\Repository\AssignmentRepository;
use App\Repository\ExclusionRepository;
use App\Security\Voter\GroupVoter;
use App\Service\DrawabilityChecker;
use App\Service\DrawService;
use App\Service\GroupPresenter;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Secrecy of the draw lives here. A participant gets exactly one assignment, their own.
 * The group admin has no endpoint that returns anybody else's; only the Wichtler admin does.
 */
#[OA\Tag(name: 'Draw')]
#[OA\Response(response: 401, description: 'Not signed in', content: new OA\JsonContent(ref: '#/components/schemas/AuthError'))]
#[OA\Response(response: 403, description: 'Not allowed for this group', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
#[OA\Response(response: 404, description: 'No such group', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
final readonly class DrawController
{
    private const string GROUP_NOT_FOUND = 'Diese Wichtelgruppe gibt es nicht.';
    private const string NOT_DRAWN = 'Die Gruppe ist noch nicht ausgelost.';

    public function __construct(
        private DrawabilityChecker $drawability,
        private DrawService $drawService,
        private GroupPresenter $presenter,
        private AssignmentRepository $assignments,
    ) {
    }

    #[Route('/api/groups/{id}/draw/status', name: 'api_draw_status', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(GroupVoter::MANAGE, subject: 'group')]
    #[OA\Get(operationId: 'getDrawStatus', summary: 'What is still missing before the group can be drawn')]
    #[OA\Response(response: 200, description: 'The checklist', content: new Model(type: DrawStatusResponse::class))]
    public function status(#[MapEntity(message: self::GROUP_NOT_FOUND)] WichtelGroup $group, ExclusionRepository $exclusions): JsonResponse
    {
        $missing = array_map(
            static fn (GroupMember $member) => new MissingWishesResponse(
                memberId: (int) $member->getId(),
                firstName: $member->getUser()->getFirstName(),
                lastName: $member->getUser()->getLastName(),
                username: $member->getUser()->getUsername(),
                wishCount: $member->getWishes()->count(),
            ),
            $this->drawability->participantsMissingWishes($group),
        );

        return new JsonResponse(new DrawStatusResponse(
            isDrawed: $group->isDrawed(),
            drawable: $this->drawability->isDrawable($group),
            participantCount: \count($group->getActiveMembers()),
            minParticipants: DrawabilityChecker::MIN_PARTICIPANTS,
            minWishAmount: $group->getMinWishAmount(),
            missingWishes: $missing,
            pendingInvitations: $group->getMembers()->filter(static fn (GroupMember $member) => MemberStatus::Invited === $member->getStatus())->count(),
            hasExclusions: $exclusions->countByGroup($group) > 0,
        ));
    }

    #[Route('/api/groups/{id}/draw', name: 'api_draw_start', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(GroupVoter::MANAGE, subject: 'group')]
    #[OA\Post(
        operationId: 'startDraw',
        summary: 'Draw the group',
        description: 'Picks one assignment uniformly at random from all valid ones. Afterwards members, exclusions and the rules of the group are locked, and unanswered invitations lapse. The response never contains the result.',
    )]
    #[OA\Response(response: 200, description: 'The drawn group', content: new Model(type: GroupResponse::class))]
    #[OA\Response(response: 409, description: 'Already drawn, not ready, or no assignment could be found', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function start(#[CurrentUser] User $user, #[MapEntity(message: self::GROUP_NOT_FOUND)] WichtelGroup $group): JsonResponse
    {
        try {
            $this->drawService->draw($group);
        } catch (DrawFailed $failure) {
            throw UserFacingHttpException::conflict($failure->getMessage(), $failure);
        }

        return new JsonResponse($this->presenter->present($group, $user));
    }

    #[Route('/api/groups/{id}/draw', name: 'api_draw_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[IsGranted(GroupVoter::MANAGE, subject: 'group')]
    #[OA\Delete(
        operationId: 'deleteDraw',
        summary: 'Delete the draw',
        description: 'Removes all assignments and unlocks the group again. Participants who already looked at their result know the old one.',
    )]
    #[OA\Response(response: 200, description: 'The group, no longer drawn', content: new Model(type: GroupResponse::class))]
    #[OA\Response(response: 409, description: 'The group has not been drawn', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function delete(#[CurrentUser] User $user, #[MapEntity(message: self::GROUP_NOT_FOUND)] WichtelGroup $group): JsonResponse
    {
        if (!$group->isDrawed()) {
            throw UserFacingHttpException::conflict(self::NOT_DRAWN);
        }

        $this->drawService->undo($group);

        return new JsonResponse($this->presenter->present($group, $user));
    }

    #[Route('/api/groups/{id}/draw/my-assignment', name: 'api_draw_my_assignment', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(GroupVoter::VIEW, subject: 'group')]
    #[OA\Get(
        operationId: 'getMyAssignment',
        summary: 'Who the current user gives a present to, with that person\'s wishes',
        description: 'The app requests this only when the user chooses to reveal it.',
    )]
    #[OA\Response(response: 200, description: 'The own assignment', content: new Model(type: AssignmentResponse::class))]
    #[OA\Response(response: 409, description: 'Not drawn yet, or the current user did not take part in the draw', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function myAssignment(#[CurrentUser] User $user, #[MapEntity(message: self::GROUP_NOT_FOUND)] WichtelGroup $group): JsonResponse
    {
        if (!$group->isDrawed()) {
            throw UserFacingHttpException::conflict(self::NOT_DRAWN);
        }

        $member = $group->getMembership($user);
        $assignment = null === $member ? null : $this->assignments->findOneByGiver($member);
        if (null === $assignment) {
            throw UserFacingHttpException::conflict('Du nimmst an dieser Auslosung nicht teil.');
        }

        return self::secret(AssignmentResponse::fromAssignment($assignment));
    }

    #[Route('/api/groups/{id}/draw/assignments/{memberId}', name: 'api_draw_assignment', requirements: ['id' => '\d+', 'memberId' => '\d+'], methods: ['GET'])]
    #[IsGranted(GroupVoter::VIEW_ALL_ASSIGNMENTS, subject: 'group')]
    #[OA\Get(
        operationId: 'getAssignmentOfMember',
        summary: 'Who a given participant gives a present to',
        description: 'Wichtler admin only. One participant per request, so nothing is transferred before it is revealed.',
    )]
    #[OA\Response(response: 200, description: 'The assignment of that participant', content: new Model(type: AssignmentResponse::class))]
    #[OA\Response(response: 409, description: 'The group has not been drawn', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function assignmentOfMember(#[MapEntity(message: self::GROUP_NOT_FOUND)] WichtelGroup $group, int $memberId): JsonResponse
    {
        if (!$group->isDrawed()) {
            throw UserFacingHttpException::conflict(self::NOT_DRAWN);
        }

        foreach ($group->getMembers() as $member) {
            if ($member->getId() === $memberId && null !== $assignment = $this->assignments->findOneByGiver($member)) {
                return self::secret(AssignmentResponse::fromAssignment($assignment));
            }
        }

        throw UserFacingHttpException::notFound('Diese Person hat an der Auslosung nicht teilgenommen.');
    }

    /** A draw result must never be kept by a browser or proxy cache. */
    private static function secret(AssignmentResponse $assignment): JsonResponse
    {
        $response = new JsonResponse($assignment);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
