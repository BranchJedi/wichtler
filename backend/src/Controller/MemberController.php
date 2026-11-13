<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Member\InviteByUsernameRequest;
use App\Dto\Member\InviteLinkResponse;
use App\Dto\Member\MemberResponse;
use App\Dto\Member\UpdateMemberRequest;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Entity\WichtelGroup;
use App\Enum\InvitedVia;
use App\Enum\MemberStatus;
use App\Http\UserFacingHttpException;
use App\Repository\UserRepository;
use App\Security\ApiProblem;
use App\Security\Voter\GroupVoter;
use App\Service\ExclusionManager;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Members')]
#[OA\Response(response: 401, description: 'Not signed in', content: new OA\JsonContent(ref: '#/components/schemas/AuthError'))]
#[OA\Response(response: 403, description: 'Not allowed for this group', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
#[OA\Response(response: 404, description: 'No such group or member', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
final readonly class MemberController
{
    private const string GROUP_NOT_FOUND = 'Diese Wichtelgruppe gibt es nicht.';
    private const string DRAWN = 'Die Gruppe ist ausgelost. Teilnehmer können nicht mehr eingeladen, entfernt oder geändert werden.';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ExclusionManager $exclusions,
    ) {
    }

    #[Route('/api/groups/{id}/members', name: 'api_members_list', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(GroupVoter::VIEW, subject: 'group')]
    #[OA\Get(operationId: 'listMembers', summary: 'Members of a group', description: 'Pending invitations are only listed for the group admin.')]
    #[OA\Response(response: 200, description: 'Members', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: MemberResponse::class))))]
    public function list(
        #[CurrentUser] User $user,
        #[MapEntity(message: self::GROUP_NOT_FOUND)] WichtelGroup $group,
        AuthorizationCheckerInterface $authorization,
    ): JsonResponse {
        $members = $group->getMembers()->toArray();
        if (!$authorization->isGranted(GroupVoter::MANAGE, $group)) {
            $members = array_filter($members, static fn (GroupMember $member) => $member->hasJoined());
        }
        usort($members, MemberResponse::order(...));

        return new JsonResponse(array_map(static fn (GroupMember $member) => MemberResponse::fromMember($member, $user), $members));
    }

    #[Route('/api/groups/{id}/members', name: 'api_members_invite', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(GroupVoter::MANAGE, subject: 'group')]
    #[OA\Post(operationId: 'inviteMember', summary: 'Invite a user by username', description: 'The user has to confirm the invitation before taking part.')]
    #[OA\Response(response: 201, description: 'The pending invitation', content: new Model(type: MemberResponse::class))]
    #[OA\Response(response: 409, description: 'The group has been drawn', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    #[OA\Response(response: 422, description: 'Unknown username, or already in the group', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function invite(
        #[CurrentUser] User $user,
        #[MapEntity(message: self::GROUP_NOT_FOUND)] WichtelGroup $group,
        #[MapRequestPayload] InviteByUsernameRequest $payload,
        UserRepository $users,
    ): JsonResponse {
        $this->assertNotDrawn($group);

        $invitee = $users->findOneByUsername($payload->username);
        if (null === $invitee) {
            throw ApiProblem::invalidField($payload, 'username', 'Diesen Username gibt es nicht.');
        }

        $existing = $group->getMembership($invitee);
        if (null !== $existing) {
            throw ApiProblem::invalidField($payload, 'username', $existing->hasJoined() ? 'Diese Person ist bereits in der Gruppe.' : 'Diese Person ist bereits eingeladen.');
        }

        $member = $group->addMember($invitee, MemberStatus::Invited, InvitedVia::Username);
        $this->entityManager->flush();

        return new JsonResponse(MemberResponse::fromMember($member, $user), Response::HTTP_CREATED);
    }

    #[Route('/api/groups/{id}/members/{memberId}', name: 'api_members_update', requirements: ['id' => '\d+', 'memberId' => '\d+'], methods: ['PATCH'])]
    #[IsGranted(GroupVoter::MANAGE, subject: 'group')]
    #[OA\Patch(operationId: 'updateMember', summary: 'Mark a participant as inactive or active', description: 'Inactive participants stay listed but are not drawn.')]
    #[OA\Response(response: 200, description: 'The updated member', content: new Model(type: MemberResponse::class))]
    #[OA\Response(response: 409, description: 'The group has been drawn, the member is the group admin, or has not joined yet', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    #[OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function update(
        #[CurrentUser] User $user,
        #[MapEntity(message: self::GROUP_NOT_FOUND)] WichtelGroup $group,
        int $memberId,
        #[MapRequestPayload] UpdateMemberRequest $payload,
    ): JsonResponse {
        $member = $this->findMember($group, $memberId);
        $this->assertNotDrawn($group);
        if ($member->isOwner()) {
            throw UserFacingHttpException::conflict('Der Gruppenadmin nimmt immer teil.');
        }
        if (!$member->hasJoined()) {
            throw UserFacingHttpException::conflict('Diese Person hat die Einladung noch nicht bestätigt.');
        }

        $member->setParticipating('active' === $payload->status);
        $this->entityManager->flush();
        $this->pruneExclusions($group);

        return new JsonResponse(MemberResponse::fromMember($member, $user));
    }

    #[Route('/api/groups/{id}/members/me', name: 'api_members_leave', requirements: ['id' => '\d+'], methods: ['DELETE'], priority: 1)]
    #[IsGranted(GroupVoter::VIEW, subject: 'group')]
    #[OA\Delete(operationId: 'leaveGroup', summary: 'Leave a group', description: 'Not possible for the group admin and not after the draw.')]
    #[OA\Response(response: 204, description: 'Left the group')]
    #[OA\Response(response: 409, description: 'The group has been drawn, or the current user is its group admin or not a member', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function leave(#[CurrentUser] User $user, #[MapEntity(message: self::GROUP_NOT_FOUND)] WichtelGroup $group): Response
    {
        $member = $group->getMembership($user);
        if (null === $member || !$member->hasJoined()) {
            throw UserFacingHttpException::conflict('Du bist kein Mitglied dieser Gruppe.');
        }
        if ($member->isOwner()) {
            throw UserFacingHttpException::conflict('Als Gruppenadmin kannst du die Gruppe nicht verlassen.');
        }
        $this->assertNotDrawn($group);

        $group->removeMember($member);
        $this->entityManager->flush();
        $this->pruneExclusions($group);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/groups/{id}/members/{memberId}', name: 'api_members_remove', requirements: ['id' => '\d+', 'memberId' => '\d+'], methods: ['DELETE'])]
    #[IsGranted(GroupVoter::MANAGE, subject: 'group')]
    #[OA\Delete(operationId: 'removeMember', summary: 'Remove a participant or withdraw an invitation')]
    #[OA\Response(response: 204, description: 'Removed')]
    #[OA\Response(response: 409, description: 'The group has been drawn, or the member is the group admin', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function remove(#[MapEntity(message: self::GROUP_NOT_FOUND)] WichtelGroup $group, int $memberId): Response
    {
        $member = $this->findMember($group, $memberId);
        $this->assertNotDrawn($group);
        if ($member->isOwner()) {
            throw UserFacingHttpException::conflict('Der Gruppenadmin kann nicht entfernt werden.');
        }

        $group->removeMember($member);
        $this->entityManager->flush();
        $this->pruneExclusions($group);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/groups/{id}/invite-link', name: 'api_invite_link_get', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(GroupVoter::MANAGE, subject: 'group')]
    #[OA\Get(operationId: 'getInviteLink', summary: 'The current share link of a group')]
    #[OA\Response(response: 200, description: 'The share link secret', content: new Model(type: InviteLinkResponse::class))]
    #[OA\Response(response: 409, description: 'The group has been drawn', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function inviteLink(#[MapEntity(message: self::GROUP_NOT_FOUND)] WichtelGroup $group): JsonResponse
    {
        $this->assertNotDrawn($group);

        return new JsonResponse(new InviteLinkResponse($group->getInviteToken() ?? $this->regenerate($group)));
    }

    #[Route('/api/groups/{id}/invite-link', name: 'api_invite_link_regenerate', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(GroupVoter::MANAGE, subject: 'group')]
    #[OA\Post(operationId: 'regenerateInviteLink', summary: 'Create a new share link', description: 'The previous link stops working immediately.')]
    #[OA\Response(response: 200, description: 'The new share link secret', content: new Model(type: InviteLinkResponse::class))]
    #[OA\Response(response: 409, description: 'The group has been drawn', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function regenerateInviteLink(#[MapEntity(message: self::GROUP_NOT_FOUND)] WichtelGroup $group): JsonResponse
    {
        $this->assertNotDrawn($group);

        return new JsonResponse(new InviteLinkResponse($this->regenerate($group)));
    }

    /** Fewer participants mean a lower exclusion limit; see ExclusionManager::pruneAfterShrink(). */
    private function pruneExclusions(WichtelGroup $group): void
    {
        $this->exclusions->pruneAfterShrink($group);
        $this->entityManager->flush();
    }

    private function regenerate(WichtelGroup $group): string
    {
        $token = $group->regenerateInviteToken();
        $this->entityManager->flush();

        return $token;
    }

    private function findMember(WichtelGroup $group, int $memberId): GroupMember
    {
        foreach ($group->getMembers() as $member) {
            if ($member->getId() === $memberId) {
                return $member;
            }
        }

        throw UserFacingHttpException::notFound('Dieses Mitglied gibt es in dieser Gruppe nicht.');
    }

    private function assertNotDrawn(WichtelGroup $group): void
    {
        if ($group->isDrawed()) {
            throw UserFacingHttpException::conflict(self::DRAWN);
        }
    }
}
