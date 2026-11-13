<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Group\GroupResponse;
use App\Dto\Member\InvitationResponse;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Entity\WichtelGroup;
use App\Enum\InvitedVia;
use App\Enum\MemberStatus;
use App\Http\UserFacingHttpException;
use App\Repository\GroupMemberRepository;
use App\Repository\WichtelGroupRepository;
use App\Service\GroupPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Both ways into a group end here: an invitation by username, addressed to one user, and
 * the share link, open to whoever holds it. Either one has to be confirmed by the user.
 */
#[OA\Tag(name: 'Invitations')]
#[OA\Response(response: 401, description: 'Not signed in', content: new OA\JsonContent(ref: '#/components/schemas/AuthError'))]
final readonly class InvitationController
{
    private const string INVITATION_NOT_FOUND = 'Diese Einladung gibt es nicht mehr.';
    private const string LINK_NOT_FOUND = 'Diese Einladung ist abgelaufen oder ungültig.';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GroupPresenter $presenter,
    ) {
    }

    #[Route('/api/invitations', name: 'api_invitations_list', methods: ['GET'])]
    #[OA\Get(operationId: 'listInvitations', summary: 'Invitations by username the current user has not answered yet')]
    #[OA\Response(response: 200, description: 'Pending invitations', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: InvitationResponse::class))))]
    public function list(#[CurrentUser] User $user, GroupMemberRepository $members): JsonResponse
    {
        return new JsonResponse(array_map(
            static fn (GroupMember $member) => InvitationResponse::fromGroup($member->getGroup(), $user),
            $members->findPendingInvitations($user),
        ));
    }

    #[Route('/api/invitations/{memberId}/accept', name: 'api_invitations_accept', requirements: ['memberId' => '\d+'], methods: ['POST'])]
    #[OA\Post(operationId: 'acceptInvitation', summary: 'Join the group of an invitation by username')]
    #[OA\Response(response: 200, description: 'The joined group', content: new Model(type: GroupResponse::class))]
    #[OA\Response(response: 404, description: 'No such pending invitation for the current user', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function accept(#[CurrentUser] User $user, int $memberId, GroupMemberRepository $members): JsonResponse
    {
        $invitation = $this->findInvitation($user, $memberId, $members);
        $invitation->join(InvitedVia::Username);
        $this->entityManager->flush();

        return new JsonResponse($this->presenter->present($invitation->getGroup(), $user));
    }

    #[Route('/api/invitations/{memberId}/decline', name: 'api_invitations_decline', requirements: ['memberId' => '\d+'], methods: ['POST'])]
    #[OA\Post(operationId: 'declineInvitation', summary: 'Decline an invitation by username')]
    #[OA\Response(response: 204, description: 'Declined')]
    #[OA\Response(response: 404, description: 'No such pending invitation for the current user', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function decline(#[CurrentUser] User $user, int $memberId, GroupMemberRepository $members): Response
    {
        $invitation = $this->findInvitation($user, $memberId, $members);
        $invitation->getGroup()->removeMember($invitation);
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/invite-links/{token}', name: 'api_invite_link_preview', methods: ['GET'])]
    #[OA\Get(operationId: 'previewInviteLink', summary: 'What a share link invites to', description: 'Requires a session, so anonymous visitors learn nothing about the group.')]
    #[OA\Response(response: 200, description: 'The group behind the link', content: new Model(type: InvitationResponse::class))]
    #[OA\Response(response: 404, description: 'Unknown, replaced or expired link', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function previewLink(#[CurrentUser] User $user, string $token, WichtelGroupRepository $groups): JsonResponse
    {
        return new JsonResponse(InvitationResponse::fromGroup($this->findGroupByLink($token, $groups), $user));
    }

    #[Route('/api/invite-links/{token}/accept', name: 'api_invite_link_accept', methods: ['POST'])]
    #[OA\Post(operationId: 'acceptInviteLink', summary: 'Join a group through its share link', description: 'Succeeds without effect for users who are in the group already.')]
    #[OA\Response(response: 200, description: 'The joined group', content: new Model(type: GroupResponse::class))]
    #[OA\Response(response: 404, description: 'Unknown, replaced or expired link', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function acceptLink(#[CurrentUser] User $user, string $token, WichtelGroupRepository $groups): JsonResponse
    {
        $group = $this->findGroupByLink($token, $groups);
        $membership = $group->getMembership($user);

        if (null === $membership) {
            $group->addMember($user, MemberStatus::Active, InvitedVia::Link);
        } elseif (!$membership->hasJoined()) {
            $membership->join(InvitedVia::Link);
        }
        $this->entityManager->flush();

        return new JsonResponse($this->presenter->present($group, $user));
    }

    private function findInvitation(User $user, int $memberId, GroupMemberRepository $members): GroupMember
    {
        $invitation = $members->find($memberId);

        // Someone else's invitation is reported exactly like a missing one.
        if (null === $invitation || $invitation->getUser() !== $user || MemberStatus::Invited !== $invitation->getStatus() || $invitation->getGroup()->isDrawed()) {
            throw UserFacingHttpException::notFound(self::INVITATION_NOT_FOUND);
        }

        return $invitation;
    }

    private function findGroupByLink(string $token, WichtelGroupRepository $groups): WichtelGroup
    {
        $group = $groups->findOneByInviteToken($token);
        if (null === $group || $group->isDrawed()) {
            throw UserFacingHttpException::notFound(self::LINK_NOT_FOUND);
        }

        return $group;
    }
}
