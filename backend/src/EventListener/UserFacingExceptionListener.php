<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Http\UserFacingHttpException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/** Renders refusals meant for the user as a problem document that keeps their explanation. */
#[AsEventListener(priority: 16)]
final class UserFacingExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof UserFacingHttpException) {
            return;
        }

        $status = $exception->getStatusCode();
        $event->setResponse(new JsonResponse(
            [
                'type' => 'about:blank',
                'title' => Response::$statusTexts[$status] ?? 'Error',
                'status' => $status,
                'detail' => $exception->getMessage(),
            ],
            $status,
            ['Content-Type' => 'application/problem+json'] + $exception->getHeaders(),
        ));
    }
}
