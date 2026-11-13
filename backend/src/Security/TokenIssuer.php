<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs a user in outside of the login endpoint (registration) and renews a session whose
 * tokens became stale because the username or the password changed.
 */
final readonly class TokenIssuer
{
    public function __construct(
        #[Autowire(service: 'lexik_jwt_authentication.handler.authentication_success')]
        private AuthenticationSuccessHandler $successHandler,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** Returns the same response as a login: user data in the body, tokens in cookies. */
    public function issue(User $user): Response
    {
        return $this->successHandler->handleAuthenticationSuccess($user);
    }

    /**
     * Ends every session of the user, on all devices: the refresh tokens stored under
     * $username are deleted and access tokens issued so far are no longer accepted.
     * Flush afterwards. Call issue() after the flush to keep the current device signed in.
     */
    public function endSessions(User $user, ?string $username = null): void
    {
        $user->invalidateSessions();
        $this->revokeRefreshTokens($username ?? $user->getUsername());
    }

    private function revokeRefreshTokens(string $username): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(RefreshToken::class, 't')
            ->where('t.username = :username')
            ->setParameter('username', $username)
            ->getQuery()
            ->execute();
    }
}
