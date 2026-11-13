<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ApiProblem
{
    /**
     * Reports a business rule violation on one field in the same shape as a failed
     * request validation, so the frontend handles both alike.
     */
    public static function invalidField(object $payload, string $field, string $message): UnprocessableEntityHttpException
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation($message, null, [], $payload, $field, null),
        ]);

        return new UnprocessableEntityHttpException($message, new ValidationFailedException($payload, $violations));
    }
}
