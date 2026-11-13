<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Symfony renders errors of JSON requests as RFC 7807 problem documents but labels them
 * "application/json". This sets the media type the RFC asks for.
 */
final class ApiProblemContentTypeListener
{
    private const string ATTRIBUTE = '_api_problem';

    #[AsEventListener(priority: 1024)]
    public function onException(ExceptionEvent $event): void
    {
        if ('json' === $event->getRequest()->getRequestFormat(null)) {
            $event->getRequest()->attributes->set(self::ATTRIBUTE, true);
        }
    }

    #[AsEventListener]
    public function onResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        // Authentication failures are answered by the firewall in its own shape, not as a problem document.
        if ($event->getRequest()->attributes->get(self::ATTRIBUTE)
            && $response->getStatusCode() >= 400
            && str_starts_with((string) $response->getContent(), '{"type":')
        ) {
            $response->headers->set('Content-Type', 'application/problem+json');
        }
    }
}
