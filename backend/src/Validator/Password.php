<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraints as Assert;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Password extends Assert\Compound
{
    public const int MIN_LENGTH = 10;

    protected function getConstraints(array $options): array
    {
        return [
            new Assert\NotBlank(),
            new Assert\Length(
                min: self::MIN_LENGTH,
                // Symfony refuses to hash anything longer.
                max: 4096,
                minMessage: 'Das Passwort muss mindestens {{ limit }} Zeichen lang sein.',
            ),
        ];
    }
}
