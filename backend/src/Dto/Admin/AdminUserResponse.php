<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use App\Entity\User;
use OpenApi\Attributes as OA;

#[OA\Schema(required: ['id', 'firstName', 'lastName', 'username', 'isAdmin', 'mustChangePassword', 'createdAt'])]
final readonly class AdminUserResponse
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $username,
        public bool $isAdmin,
        #[OA\Property(description: 'A temporary password has been set and not been replaced yet.')]
        public bool $mustChangePassword,
        #[OA\Property(format: 'date-time')]
        public string $createdAt,
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
            createdAt: $user->getCreatedAt()->format(\DATE_ATOM),
        );
    }
}
