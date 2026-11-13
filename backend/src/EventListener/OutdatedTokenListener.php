<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Stamps every access token with the user's session version and refuses tokens whose
 * version is outdated. Without this, a session would survive a password reset until its
 * token expires.
 */
final readonly class OutdatedTokenListener
{
    private const string CLAIM = 'sv';

    public function __construct(private UserRepository $users)
    {
    }

    #[AsEventListener(event: Events::JWT_CREATED)]
    public function stamp(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof User) {
            $event->setData([...$event->getData(), self::CLAIM => $user->getSessionVersion()]);
        }
    }

    #[AsEventListener(event: Events::JWT_DECODED)]
    public function check(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        $username = $payload['username'] ?? null;
        if (!\is_string($username)) {
            return;
        }

        // An unknown username is left to the authenticator, which reports it like any bad token.
        $user = $this->users->findOneByUsername($username);
        if (null !== $user && ($payload[self::CLAIM] ?? null) !== $user->getSessionVersion()) {
            $event->markAsInvalid();
        }
    }
}
