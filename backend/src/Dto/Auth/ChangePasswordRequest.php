<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use App\Validator\Password;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['currentPassword', 'newPassword'])]
final readonly class ChangePasswordRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[OA\Property(format: 'password')]
        public string $currentPassword = '',
        #[Password]
        #[OA\Property(format: 'password')]
        public string $newPassword = '',
    ) {
    }
}
