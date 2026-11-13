<?php

declare(strict_types=1);

namespace App\Dto\Draw;

use App\Dto\Wish\WishResponse;
use App\Entity\Assignment;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** Who a participant gives a present to, together with that person's wishes. */
#[OA\Schema(required: ['receiverFirstName', 'receiverLastName', 'receiverUsername', 'wishes'])]
final readonly class AssignmentResponse
{
    /** @param list<WishResponse> $wishes */
    public function __construct(
        public string $receiverFirstName,
        public string $receiverLastName,
        public string $receiverUsername,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: WishResponse::class)))]
        public array $wishes,
    ) {
    }

    public static function fromAssignment(Assignment $assignment): self
    {
        $receiver = $assignment->getReceiver();

        return new self(
            receiverFirstName: $receiver->getUser()->getFirstName(),
            receiverLastName: $receiver->getUser()->getLastName(),
            receiverUsername: $receiver->getUser()->getUsername(),
            wishes: array_map(WishResponse::fromWish(...), $receiver->getWishes()->getValues()),
        );
    }
}
