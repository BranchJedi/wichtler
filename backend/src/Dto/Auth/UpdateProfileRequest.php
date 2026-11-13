<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use App\Validator\Username;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['firstName', 'lastName', 'username'])]
final readonly class UpdateProfileRequest
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
    ) {
    }
}
