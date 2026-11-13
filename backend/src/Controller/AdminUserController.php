<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Admin\AdminUserResponse;
use App\Dto\Admin\TemporaryPasswordResponse;
use App\Dto\Auth\UpdateProfileRequest;
use App\Entity\User;
use App\Http\UserFacingHttpException;
use App\Repository\UserRepository;
use App\Security\ApiProblem;
use App\Security\TokenIssuer;
use App\Service\PasswordResetter;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The app sends no e-mails, so nobody can recover a forgotten username or password on their
 * own. The Wichtler admin looks the person up here and helps. All of /api/admin is reserved
 * for ROLE_ADMIN in config/packages/security.yaml.
 */
#[OA\Tag(name: 'Admin')]
#[OA\Response(response: 401, description: 'Not signed in', content: new OA\JsonContent(ref: '#/components/schemas/AuthError'))]
#[OA\Response(response: 403, description: 'Not the Wichtler admin', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
final readonly class AdminUserController
{
    private const string NOT_FOUND = 'Diesen Benutzer gibt es nicht.';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TokenIssuer $tokenIssuer,
    ) {
    }

    #[Route('/api/admin/users', name: 'api_admin_users_list', methods: ['GET'])]
    #[OA\Get(operationId: 'listUsers', summary: 'Find users by name or username')]
    #[OA\Parameter(name: 'q', in: 'query', description: 'Words that have to occur in first name, last name or username. Empty lists everyone.', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'At most 100 users, by name', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: AdminUserResponse::class))))]
    public function list(UserRepository $users, #[MapQueryParameter] string $q = ''): JsonResponse
    {
        return new JsonResponse(array_map(AdminUserResponse::fromUser(...), $users->search($q)));
    }

    #[Route('/api/admin/users/{id}', name: 'api_admin_users_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[OA\Patch(
        operationId: 'updateUser',
        summary: 'Change a user\'s name and username',
        description: 'Changing the username signs the user out everywhere; they sign in again with the new one.',
    )]
    #[OA\Response(response: 200, description: 'The updated user', content: new Model(type: AdminUserResponse::class))]
    #[OA\Response(response: 404, description: 'No such user', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    #[OA\Response(response: 409, description: 'The Wichtler admin edits the own account in the profile', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    #[OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function update(
        #[CurrentUser] User $admin,
        #[MapEntity(message: self::NOT_FOUND)] User $user,
        #[MapRequestPayload] UpdateProfileRequest $payload,
        UserRepository $users,
    ): JsonResponse {
        $this->assertNotSelf($admin, $user);

        $existing = $users->findOneByUsername($payload->username);
        if (null !== $existing && $existing !== $user) {
            throw ApiProblem::invalidField($payload, 'username', 'Dieser Username ist bereits vergeben.');
        }

        $previousUsername = $user->getUsername();
        $user->setFirstName(trim($payload->firstName));
        $user->setLastName(trim($payload->lastName));
        $user->setUsername($payload->username);
        if ($previousUsername !== $user->getUsername()) {
            $this->tokenIssuer->endSessions($user, $previousUsername);
        }
        $this->entityManager->flush();

        return new JsonResponse(AdminUserResponse::fromUser($user));
    }

    #[Route('/api/admin/users/{id}/reset-password', name: 'api_admin_users_reset_password', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[OA\Post(
        operationId: 'resetUserPassword',
        summary: 'Give a user a temporary password',
        description: 'The server generates the password and returns it once. The user is signed out everywhere and has to choose a new password after signing in with it.',
    )]
    #[OA\Response(response: 200, description: 'The temporary password', content: new Model(type: TemporaryPasswordResponse::class))]
    #[OA\Response(response: 404, description: 'No such user', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    #[OA\Response(response: 409, description: 'The Wichtler admin changes the own password in the profile', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function resetPassword(
        #[CurrentUser] User $admin,
        #[MapEntity(message: self::NOT_FOUND)] User $user,
        PasswordResetter $resetter,
    ): JsonResponse {
        $this->assertNotSelf($admin, $user);

        $temporaryPassword = $resetter->reset($user);
        $this->entityManager->flush();

        $response = new JsonResponse(new TemporaryPasswordResponse(AdminUserResponse::fromUser($user), $temporaryPassword));
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private function assertNotSelf(User $admin, User $user): void
    {
        if ($admin === $user) {
            throw UserFacingHttpException::conflict('Dein eigenes Konto bearbeitest du im Profil.');
        }
    }
}
