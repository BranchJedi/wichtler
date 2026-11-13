<?php

declare(strict_types=1);

namespace App\Dto\Member;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['status'])]
final readonly class UpdateMemberRequest
{
    public function __construct(
        #[Assert\Choice(choices: ['active', 'inactive'])]
        #[OA\Property(enum: ['active', 'inactive'])]
        public string $status = '',
    ) {
    }
}
