<?php

declare(strict_types=1);

namespace App\Dto\Member;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['username'])]
final readonly class InviteByUsernameRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 30)]
        public string $username = '',
    ) {
    }
}
