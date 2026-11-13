<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Everything below /api speaks JSON, whatever the Accept header says. This makes Symfony
 * render errors (including 404 for unknown routes) as application/problem+json.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 256)]
final class ApiRequestFormatListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (str_starts_with($path, '/api/') && '/api/doc' !== $path) {
            $request->setRequestFormat('json');
        }
    }
}
