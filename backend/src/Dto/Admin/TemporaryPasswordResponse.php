<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(required: ['user', 'temporaryPassword'])]
final readonly class TemporaryPasswordResponse
{
    public function __construct(
        #[OA\Property(ref: new Model(type: AdminUserResponse::class))]
        public AdminUserResponse $user,
        #[OA\Property(description: 'Shown exactly once. It is not stored in readable form and cannot be retrieved again.')]
        public string $temporaryPassword,
    ) {
    }
}
