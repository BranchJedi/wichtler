<?php

declare(strict_types=1);

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\Request;

/**
 * Ignores the session cookie on the endpoints that start or end a session.
 *
 * A browser may still hold a cookie that is no longer good, for instance after an admin
 * reset. Left alone, that stale cookie would be checked first and fail the request, so the
 * user could not sign in again until the cookie expires. Login, registration, refresh and
 * logout do not depend on an existing session, so they never look at it.
 */
#[AsDecorator('lexik_jwt_authentication.extractor.chain_extractor')]
final readonly class SessionTokenExtractor implements TokenExtractorInterface
{
    public function __construct(#[AutowireDecorated] private TokenExtractorInterface $inner)
    {
    }

    public function extract(Request $request): string|false
    {
        if (str_starts_with($request->getPathInfo(), '/api/auth/')) {
            return false;
        }

        return $this->inner->extract($request);
    }
}
