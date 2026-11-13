<?php

declare(strict_types=1);

namespace App\Dto\Wish;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['title'])]
final readonly class WishRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 160)]
        public string $title = '',
        #[Assert\Length(max: 2000)]
        public ?string $description = null,
        #[Assert\Length(max: 500)]
        #[Assert\Url(protocols: ['http', 'https'], requireTld: true, message: 'Bitte einen gültigen Link angeben, der mit http:// oder https:// beginnt.')]
        #[OA\Property(format: 'uri', description: 'http or https only.')]
        public ?string $url = null,
    ) {
    }
}
