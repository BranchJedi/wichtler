<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Group\GroupRequest;
use App\Dto\Group\GroupResponse;
use App\Entity\User;
use App\Entity\WichtelGroup;
use App\Http\UserFacingHttpException;
use App\Repository\WichtelGroupRepository;
use App\Security\Voter\GroupVoter;
use App\Service\GroupPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Groups')]
#[OA\Response(response: 401, description: 'Not signed in', content: new OA\JsonContent(ref: '#/components/schemas/AuthError'))]
final readonly class GroupController
{
    private const string NOT_FOUND = 'Diese Wichtelgruppe gibt es nicht.';

    public function __construct(
        private GroupPresenter $presenter,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/groups', name: 'api_groups_list', methods: ['GET'])]
    #[OA\Get(operationId: 'listGroups', summary: 'Groups of the current user, or all groups for the Wichtler admin')]
    #[OA\Parameter(name: 'scope', in: 'query', description: '"all" is reserved for the Wichtler admin.', schema: new OA\Schema(type: 'string', enum: ['mine', 'all'], default: 'mine'))]
    #[OA\Response(response: 200, description: 'Groups, newest event first', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: GroupResponse::class))))]
    #[OA\Response(response: 403, description: 'scope=all without being the Wichtler admin', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function list(
        #[CurrentUser] User $user,
        WichtelGroupRepository $groups,
        #[MapQueryParameter] string $scope = 'mine',
    ): JsonResponse {
        if ('all' === $scope) {
            if (!$user->isAdmin()) {
                throw UserFacingHttpException::forbidden('Nur der Wichtler-Admin sieht alle Gruppen.');
            }
            $found = $groups->findAllOrdered();
        } else {
            $found = $groups->findJoinedBy($user);
        }

        return new JsonResponse(array_map(fn (WichtelGroup $group) => $this->presenter->present($group, $user), $found));
    }

    #[Route('/api/groups', name: 'api_groups_create', methods: ['POST'])]
    #[OA\Post(operationId: 'createGroup', summary: 'Create a group. The creator becomes its group admin and first participant.')]
    #[OA\Response(response: 201, description: 'The new group', content: new Model(type: GroupResponse::class))]
    #[OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function create(#[CurrentUser] User $user, #[MapRequestPayload] GroupRequest $payload): JsonResponse
    {
        $group = new WichtelGroup(
            $user,
            trim($payload->title),
            $payload->year,
            new \DateTimeImmutable($payload->eventDate),
            $payload->minWishAmount,
        );
        $this->applyOptionalFields($group, $payload);

        $this->entityManager->persist($group);
        $this->entityManager->flush();

        return new JsonResponse($this->presenter->present($group, $user), Response::HTTP_CREATED);
    }

    #[Route('/api/groups/{id}', name: 'api_groups_get', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(GroupVoter::VIEW, subject: 'group')]
    #[OA\Get(operationId: 'getGroup', summary: 'One group')]
    #[OA\Response(response: 200, description: 'The group', content: new Model(type: GroupResponse::class))]
    #[OA\Response(response: 403, description: 'Not a member of this group', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    #[OA\Response(response: 404, description: 'No such group', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function get(#[CurrentUser] User $user, #[MapEntity(message: self::NOT_FOUND)] WichtelGroup $group): JsonResponse
    {
        return new JsonResponse($this->presenter->present($group, $user));
    }

    #[Route('/api/groups/{id}', name: 'api_groups_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[IsGranted(GroupVoter::MANAGE, subject: 'group')]
    #[OA\Patch(
        operationId: 'updateGroup',
        summary: 'Edit a group',
        description: 'Expects the complete group. After the draw only title, description and event date may change.',
    )]
    #[OA\Response(response: 200, description: 'The updated group', content: new Model(type: GroupResponse::class))]
    #[OA\Response(response: 403, description: 'Not the group admin', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    #[OA\Response(response: 404, description: 'No such group', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    #[OA\Response(response: 409, description: 'The draw has locked one of the changed fields', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    #[OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function update(
        #[CurrentUser] User $user,
        #[MapEntity(message: self::NOT_FOUND)] WichtelGroup $group,
        #[MapRequestPayload] GroupRequest $payload,
    ): JsonResponse {
        if ($group->isDrawed() && $this->changesLockedFields($group, $payload)) {
            throw UserFacingHttpException::conflict('Nach der Auslosung können Jahr, Budget, Währung und Mindestanzahl Wünsche nicht mehr geändert werden.');
        }

        $group->setTitle(trim($payload->title));
        $group->setYear($payload->year);
        $group->setEventDate(new \DateTimeImmutable($payload->eventDate));
        $group->setMinWishAmount($payload->minWishAmount);
        $this->applyOptionalFields($group, $payload);
        $this->entityManager->flush();

        return new JsonResponse($this->presenter->present($group, $user));
    }

    #[Route('/api/groups/{id}', name: 'api_groups_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[IsGranted(GroupVoter::MANAGE, subject: 'group')]
    #[OA\Delete(
        operationId: 'deleteGroup',
        summary: 'Delete a group',
        description: 'Refused once the group has been drawn; the draw has to be deleted first. This also holds for the Wichtler admin.',
    )]
    #[OA\Response(response: 204, description: 'Deleted')]
    #[OA\Response(response: 403, description: 'Not the group admin', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    #[OA\Response(response: 404, description: 'No such group', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    #[OA\Response(response: 409, description: 'The group has been drawn', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function delete(#[MapEntity(message: self::NOT_FOUND)] WichtelGroup $group): Response
    {
        if ($group->isDrawed()) {
            throw UserFacingHttpException::conflict('Eine ausgeloste Gruppe kann nicht gelöscht werden. Lösche zuerst die Auslosung.');
        }

        $this->entityManager->remove($group);
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function applyOptionalFields(WichtelGroup $group, GroupRequest $payload): void
    {
        $description = trim((string) $payload->description);
        $group->setDescription('' === $description ? null : $description);
        $group->setBudget($payload->budgetMin, $payload->budgetMax);
        $group->setCurrency($payload->currency);
    }

    private function changesLockedFields(WichtelGroup $group, GroupRequest $payload): bool
    {
        return $group->getYear() !== $payload->year
            || $group->getBudgetMin() !== $payload->budgetMin
            || $group->getBudgetMax() !== $payload->budgetMax
            || $group->getCurrency() !== $payload->currency
            || $group->getMinWishAmount() !== $payload->minWishAmount;
    }
}
