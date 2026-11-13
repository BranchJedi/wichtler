<?php

declare(strict_types=1);

namespace App\Dto\Group;

use App\Entity\WichtelGroup;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[OA\Schema(required: ['title', 'year', 'eventDate', 'minWishAmount', 'currency'])]
final readonly class GroupRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 120)]
        public string $title = '',
        #[Assert\Length(max: 2000)]
        public ?string $description = null,
        #[Assert\Range(min: 2000, max: 2100)]
        public int $year = 0,
        #[Assert\Range(min: 0, max: 100000)]
        #[OA\Property(description: 'Whole units of the currency. Omit both budget fields for "no budget".')]
        public ?int $budgetMin = null,
        #[Assert\Range(min: 0, max: 100000)]
        public ?int $budgetMax = null,
        #[Assert\Choice(choices: WichtelGroup::CURRENCIES)]
        #[OA\Property(enum: WichtelGroup::CURRENCIES)]
        public string $currency = 'CHF',
        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Property(format: 'date', example: '2026-12-24')]
        public string $eventDate = '',
        #[Assert\Range(min: 0, max: 20)]
        public int $minWishAmount = 0,
    ) {
    }

    #[Assert\Callback]
    public function validateBudget(ExecutionContextInterface $context): void
    {
        if (null !== $this->budgetMin && null !== $this->budgetMax && $this->budgetMin > $this->budgetMax) {
            $context->buildViolation('Das maximale Budget darf nicht kleiner sein als das minimale.')
                ->atPath('budgetMax')
                ->addViolation();
        }
    }
}
