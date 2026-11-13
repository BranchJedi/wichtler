<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;
use OpenApi\Attributes as OA;

#[OA\Schema(required: ['id', 'firstName', 'lastName', 'username', 'isAdmin', 'mustChangePassword'])]
final readonly class UserResponse
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $username,
        #[OA\Property(description: 'True for the Wichtler admin.')]
        public bool $isAdmin,
        #[OA\Property(description: 'True after an admin reset: the user must set a new password.')]
        public bool $mustChangePassword,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self(
            id: (int) $user->getId(),
            firstName: $user->getFirstName(),
            lastName: $user->getLastName(),
            username: $user->getUsername(),
            isAdmin: $user->isAdmin(),
            mustChangePassword: $user->mustChangePassword(),
        );
    }
}
