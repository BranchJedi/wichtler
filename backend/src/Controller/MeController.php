<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Auth\ChangePasswordRequest;
use App\Dto\Auth\UpdateProfileRequest;
use App\Dto\UserResponse;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\ApiProblem;
use App\Security\TokenIssuer;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[OA\Tag(name: 'Me')]
#[OA\Response(response: 401, description: 'Not signed in', content: new OA\JsonContent(ref: '#/components/schemas/AuthError'))]
final class MeController
{
    #[Route('/api/me', name: 'api_me_get', methods: ['GET'])]
    #[OA\Get(operationId: 'getMe', summary: 'The signed-in user')]
    #[OA\Response(response: 200, description: 'The signed-in user', content: new Model(type: UserResponse::class))]
    public function get(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse(UserResponse::fromUser($user));
    }

    #[Route('/api/me', name: 'api_me_update', methods: ['PATCH'])]
    #[OA\Patch(
        operationId: 'updateMe',
        summary: 'Change name and username',
        description: 'Renews the session cookies, because the tokens carry the username.',
    )]
    #[OA\Response(response: 200, description: 'The updated user', content: new Model(type: UserResponse::class))]
    #[OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function update(
        #[CurrentUser] User $user,
        #[MapRequestPayload] UpdateProfileRequest $payload,
        UserRepository $users,
        EntityManagerInterface $entityManager,
        TokenIssuer $tokenIssuer,
    ): Response {
        $existing = $users->findOneByUsername($payload->username);
        if (null !== $existing && $existing !== $user) {
            throw ApiProblem::invalidField($payload, 'username', 'Dieser Username ist bereits vergeben.');
        }

        $previousUsername = $user->getUsername();
        $user->setFirstName(trim($payload->firstName));
        $user->setLastName(trim($payload->lastName));
        $user->setUsername($payload->username);
        $entityManager->flush();

        if ($previousUsername === $user->getUsername()) {
            return new JsonResponse(UserResponse::fromUser($user));
        }

        // Tokens issued for the old username no longer resolve to a user.
        $tokenIssuer->endSessions($user, $previousUsername);
        $entityManager->flush();

        return $tokenIssuer->issue($user);
    }

    #[Route('/api/me/password', name: 'api_me_password', methods: ['PUT'])]
    #[OA\Put(
        operationId: 'changeMyPassword',
        summary: 'Change the password',
        description: 'Signs out every other device and renews the session cookies of this one.',
    )]
    #[OA\Response(response: 200, description: 'Password changed', content: new Model(type: UserResponse::class))]
    #[OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function changePassword(
        #[CurrentUser] User $user,
        #[MapRequestPayload] ChangePasswordRequest $payload,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $entityManager,
        TokenIssuer $tokenIssuer,
    ): Response {
        if (!$hasher->isPasswordValid($user, $payload->currentPassword)) {
            throw ApiProblem::invalidField($payload, 'currentPassword', 'Das aktuelle Passwort ist nicht korrekt.');
        }

        $user->setPassword($hasher->hashPassword($user, $payload->newPassword));
        $user->setMustChangePassword(false);
        $tokenIssuer->endSessions($user);
        $entityManager->flush();

        return $tokenIssuer->issue($user);
    }
}
