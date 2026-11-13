<?php

declare(strict_types=1);

namespace App\Dto\Wish;

use App\Entity\Wish;
use OpenApi\Attributes as OA;

#[OA\Schema(required: ['id', 'title', 'description', 'url'])]
final readonly class WishResponse
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        #[OA\Property(format: 'uri')]
        public ?string $url,
    ) {
    }

    public static function fromWish(Wish $wish): self
    {
        return new self((int) $wish->getId(), $wish->getTitle(), $wish->getDescription(), $wish->getUrl());
    }
}
