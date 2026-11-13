<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Wish\WishRequest;
use App\Dto\Wish\WishResponse;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Entity\WichtelGroup;
use App\Entity\Wish;
use App\Http\UserFacingHttpException;
use App\Security\Voter\GroupVoter;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Every participant manages only their own wish list. There is deliberately no endpoint
 * that returns somebody else's wishes; those are revealed together with the draw result.
 */
#[OA\Tag(name: 'Wishes')]
#[IsGranted(GroupVoter::VIEW, subject: 'group')]
#[OA\Response(response: 401, description: 'Not signed in', content: new OA\JsonContent(ref: '#/components/schemas/AuthError'))]
#[OA\Response(response: 403, description: 'Not a member of this group', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
#[OA\Response(response: 404, description: 'No such group or wish', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
final readonly class WishController
{
    public const int MAX_WISHES = 30;
    private const string GROUP_NOT_FOUND = 'Diese Wichtelgruppe gibt es nicht.';

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Route('/api/groups/{id}/wishes', name: 'api_wishes_list', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(operationId: 'listMyWishes', summary: 'The current user\'s own wishes in this group')]
    #[OA\Response(response: 200, description: 'Own wishes, oldest first', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: WishResponse::class))))]
    #[OA\Response(response: 409, description: 'The current user has not joined this group', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function list(#[CurrentUser] User $user, #[MapEntity(message: self::GROUP_NOT_FOUND)] WichtelGroup $group): JsonResponse
    {
        return new JsonResponse(array_map(WishResponse::fromWish(...), $this->membership($group, $user)->getWishes()->getValues()));
    }

    #[Route('/api/groups/{id}/wishes', name: 'api_wishes_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[OA\Post(operationId: 'createWish', summary: 'Add a wish')]
    #[OA\Response(response: 201, description: 'The new wish', content: new Model(type: WishResponse::class))]
    #[OA\Response(response: 409, description: 'Not a member, or the wish list is full', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    #[OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function create(
        #[CurrentUser] User $user,
        #[MapEntity(message: self::GROUP_NOT_FOUND)] WichtelGroup $group,
        #[MapRequestPayload] WishRequest $payload,
    ): JsonResponse {
        $member = $this->membership($group, $user);
        if ($member->getWishes()->count() >= self::MAX_WISHES) {
            throw UserFacingHttpException::conflict(\sprintf('Mehr als %d Wünsche sind nicht möglich.', self::MAX_WISHES));
        }

        $wish = $member->addWish(trim($payload->title), self::blankToNull($payload->description), self::blankToNull($payload->url));
        $this->entityManager->flush();

        return new JsonResponse(WishResponse::fromWish($wish), Response::HTTP_CREATED);
    }

    #[Route('/api/groups/{id}/wishes/{wishId}', name: 'api_wishes_update', requirements: ['id' => '\d+', 'wishId' => '\d+'], methods: ['PATCH'])]
    #[OA\Patch(operationId: 'updateWish', summary: 'Edit one of the own wishes', description: 'Expects the complete wish. Allowed after the draw as well.')]
    #[OA\Response(response: 200, description: 'The updated wish', content: new Model(type: WishResponse::class))]
    #[OA\Response(response: 409, description: 'The current user has not joined this group', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    #[OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function update(
        #[CurrentUser] User $user,
        #[MapEntity(message: self::GROUP_NOT_FOUND)] WichtelGroup $group,
        int $wishId,
        #[MapRequestPayload] WishRequest $payload,
    ): JsonResponse {
        $wish = $this->ownWish($this->membership($group, $user), $wishId);
        $wish->update(trim($payload->title), self::blankToNull($payload->description), self::blankToNull($payload->url));
        $this->entityManager->flush();

        return new JsonResponse(WishResponse::fromWish($wish));
    }

    #[Route('/api/groups/{id}/wishes/{wishId}', name: 'api_wishes_delete', requirements: ['id' => '\d+', 'wishId' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        operationId: 'deleteWish',
        summary: 'Delete one of the own wishes',
        description: 'After the draw a wish can only be deleted while the minimum number of wishes stays met.',
    )]
    #[OA\Response(response: 204, description: 'Deleted')]
    #[OA\Response(response: 409, description: 'Not a member, or the draw requires this many wishes', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function delete(#[CurrentUser] User $user, #[MapEntity(message: self::GROUP_NOT_FOUND)] WichtelGroup $group, int $wishId): Response
    {
        $member = $this->membership($group, $user);
        $wish = $this->ownWish($member, $wishId);

        if ($group->isDrawed() && $member->getWishes()->count() <= $group->getMinWishAmount()) {
            throw UserFacingHttpException::conflict(\sprintf(
                'Die Gruppe ist ausgelost. Du brauchst mindestens %d Wünsche. Bearbeite den Wunsch oder füge zuerst einen neuen hinzu.',
                $group->getMinWishAmount(),
            ));
        }

        $member->removeWish($wish);
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /** The Wichtler admin may look at every group but has a wish list only where he takes part. */
    private function membership(WichtelGroup $group, User $user): GroupMember
    {
        $member = $group->getMembership($user);
        if (null === $member || !$member->hasJoined()) {
            throw UserFacingHttpException::conflict('Du bist kein Mitglied dieser Gruppe und hast hier keine Wunschliste.');
        }

        return $member;
    }

    private function ownWish(GroupMember $member, int $wishId): Wish
    {
        foreach ($member->getWishes() as $wish) {
            if ($wish->getId() === $wishId) {
                return $wish;
            }
        }

        // Someone else's wish is reported exactly like a missing one.
        throw UserFacingHttpException::notFound('Diesen Wunsch gibt es nicht.');
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }
}
