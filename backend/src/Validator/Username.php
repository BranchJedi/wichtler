<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraints as Assert;

/** 3 to 30 characters out of a-z, 0-9, dot, underscore and hyphen. Case is ignored. */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Username extends Assert\Compound
{
    protected function getConstraints(array $options): array
    {
        return [
            new Assert\NotBlank(),
            new Assert\Regex(
                pattern: '/^[a-z0-9._-]{3,30}$/i',
                message: 'Der Username muss 3 bis 30 Zeichen lang sein und darf nur Buchstaben (a-z), Ziffern, Punkt, Unterstrich und Bindestrich enthalten.',
            ),
        ];
    }
}
