<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Dto\UserResponse;
use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Login, refresh and registration answer with the signed-in user. The tokens themselves
 * are only ever sent as HttpOnly cookies.
 */
#[AsEventListener(event: Events::AUTHENTICATION_SUCCESS, priority: -100)]
final class LoginResponseListener
{
    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $event->setData((array) UserResponse::fromUser($user));
    }
}
