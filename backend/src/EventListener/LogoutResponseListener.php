<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * An API logout always answers 204, also when there was no session left to end. Runs last
 * and keeps the cookie-clearing headers the earlier listeners put on the response.
 */
#[AsEventListener(event: LogoutEvent::class, priority: -1024)]
final class LogoutResponseListener
{
    public function __invoke(LogoutEvent $event): void
    {
        $response = new Response(null, Response::HTTP_NO_CONTENT);

        foreach ($event->getResponse()?->headers->getCookies() ?? [] as $cookie) {
            $response->headers->setCookie($cookie);
        }

        $event->setResponse($response);
    }
}
