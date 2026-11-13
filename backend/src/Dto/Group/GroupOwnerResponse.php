<?php

declare(strict_types=1);

namespace App\Dto\Group;

use App\Entity\User;
use OpenApi\Attributes as OA;

#[OA\Schema(required: ['id', 'firstName', 'lastName', 'username'])]
final readonly class GroupOwnerResponse
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $username,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self((int) $user->getId(), $user->getFirstName(), $user->getLastName(), $user->getUsername());
    }
}
