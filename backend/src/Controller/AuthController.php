<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Auth\RegisterRequest;
use App\Dto\UserResponse;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\ApiProblem;
use App\Security\TokenIssuer;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController
{
    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    #[OA\Post(
        operationId: 'register',
        summary: 'Create an account and sign in',
        description: 'Sets the BEARER and refresh_token cookies like a login.',
        security: [],
        tags: ['Auth'],
    )]
    #[OA\Response(response: 201, description: 'Account created, user is signed in', content: new Model(type: UserResponse::class))]
    #[OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    #[OA\Response(response: 429, description: 'Too many registrations from this address', content: new OA\JsonContent(ref: '#/components/schemas/ProblemDetails'))]
    public function register(
        #[MapRequestPayload] RegisterRequest $payload,
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $entityManager,
        TokenIssuer $tokenIssuer,
        #[Autowire(service: 'limiter.registration')] RateLimiterFactoryInterface $registrationLimiter,
    ): Response {
        $limit = $registrationLimiter->create($request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time(), 'Zu viele Registrierungen. Bitte später erneut versuchen.');
        }

        if (null !== $users->findOneByUsername($payload->username)) {
            throw ApiProblem::invalidField($payload, 'username', 'Dieser Username ist bereits vergeben.');
        }

        $user = new User(trim($payload->firstName), trim($payload->lastName), $payload->username);
        $user->setPassword($hasher->hashPassword($user, $payload->password));

        $entityManager->persist($user);
        $entityManager->flush();

        return $tokenIssuer->issue($user)->setStatusCode(Response::HTTP_CREATED);
    }
}
