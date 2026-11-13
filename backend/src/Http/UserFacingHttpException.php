<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A refusal whose message is written for the person using the app and is safe to show.
 *
 * Symfony hides exception messages outside of debug mode, rightly, since most of them are
 * written for developers. Messages of this class are the exception: they reach the client
 * as the "detail" of the problem document in every environment.
 */
final class UserFacingHttpException extends HttpException
{
    public static function conflict(string $message, ?\Throwable $previous = null): self
    {
        return new self(Response::HTTP_CONFLICT, $message, $previous);
    }

    public static function forbidden(string $message): self
    {
        return new self(Response::HTTP_FORBIDDEN, $message);
    }

    public static function notFound(string $message): self
    {
        return new self(Response::HTTP_NOT_FOUND, $message);
    }

    public static function unprocessable(string $message): self
    {
        return new self(Response::HTTP_UNPROCESSABLE_ENTITY, $message);
    }
}
