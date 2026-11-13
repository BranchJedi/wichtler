<?php

declare(strict_types=1);

namespace App\Dto\Member;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['token'])]
final readonly class InviteLinkResponse
{
    public function __construct(
        #[OA\Property(description: 'Secret of the share link. The link is "/einladung/{token}" on the app\'s own address.')]
        public string $token,
    ) {
    }
}
