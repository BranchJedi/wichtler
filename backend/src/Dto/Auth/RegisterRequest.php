<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use App\Validator\Password;
use App\Validator\Username;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['firstName', 'lastName', 'username', 'password'])]
final readonly class RegisterRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 80)]
        public string $firstName = '',
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 80)]
        public string $lastName = '',
        #[Username]
        public string $username = '',
        #[Password]
        #[OA\Property(format: 'password')]
        public string $password = '',
    ) {
    }
}
