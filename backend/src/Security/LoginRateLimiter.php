<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RateLimiter\PeekableRequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\RateLimiter\DefaultLoginRateLimiter;

/**
 * Login throttling that only looks at the login endpoint.
 *
 * Symfony's throttling listens to every authenticator of the firewall. In this app that
 * includes the JWT check on each API request and the session refresh, which fails harmlessly
 * whenever a guest opens the app. Counting those as failed logins lets a handful of page views
 * lock an entire address out, signed-in users included. So everything except real password
 * attempts passes through untouched.
 */
final readonly class LoginRateLimiter implements PeekableRequestRateLimiterInterface
{
    public const string LOGIN_PATH = '/api/auth/login';

    private DefaultLoginRateLimiter $inner;

    public function __construct(
        #[Autowire(service: 'limiter.login_per_address')] RateLimiterFactory $perAddress,
        #[Autowire(service: 'limiter.login_per_user')] RateLimiterFactory $perUser,
        #[Autowire('%kernel.secret%')] #[\SensitiveParameter] string $secret,
    ) {
        $this->inner = new DefaultLoginRateLimiter($perAddress, $perUser, $secret);
    }

    public function peek(Request $request): RateLimit
    {
        return $this->isLogin($request) ? $this->inner->peek($request) : self::unlimited();
    }

    public function consume(Request $request): RateLimit
    {
        return $this->isLogin($request) ? $this->inner->consume($request) : self::unlimited();
    }

    public function reset(Request $request): void
    {
        if ($this->isLogin($request)) {
            $this->inner->reset($request);
        }
    }

    private function isLogin(Request $request): bool
    {
        return self::LOGIN_PATH === $request->getPathInfo();
    }

    /** "Nothing used, nothing to reset" in the terms of Symfony's listener. */
    private static function unlimited(): RateLimit
    {
        return new RateLimit(\PHP_INT_MAX, new \DateTimeImmutable(), true, \PHP_INT_MAX);
    }
}
