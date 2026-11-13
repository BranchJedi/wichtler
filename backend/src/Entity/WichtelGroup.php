<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\InvitedVia;
use App\Enum\MemberStatus;
use App\Repository\WichtelGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WichtelGroupRepository::class)]
#[ORM\Table(name: 'wichtel_group')]
#[ORM\UniqueConstraint(name: 'uniq_group_invite_token', columns: ['invite_token'])]
#[ORM\HasLifecycleCallbacks]
class WichtelGroup
{
    public const array CURRENCIES = ['CHF', 'EUR', 'USD', 'GBP'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $year;

    /** Whole units of the group currency. */
    #[ORM\Column(nullable: true)]
    private ?int $budgetMin = null;

    #[ORM\Column(nullable: true)]
    private ?int $budgetMax = null;

    /** ISO 4217 code. */
    #[ORM\Column(length: 3, options: ['default' => 'CHF'])]
    private string $currency = 'CHF';

    /** The day the gifts are exchanged. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $eventDate;

    /** How many wishes every participant has to enter before the draw. */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $minWishAmount;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDrawed = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $drawnAt = null;

    /** The group admin. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    /** Secret of the share link. Null while no link is valid. */
    #[ORM\Column(length: 43, nullable: true)]
    private ?string $inviteToken;

    /** @var Collection<int, GroupMember> */
    #[ORM\OneToMany(targetEntity: GroupMember::class, mappedBy: 'group', cascade: ['persist'], orphanRemoval: true)]
    private Collection $members;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $owner, string $title, int $year, \DateTimeImmutable $eventDate, int $minWishAmount)
    {
        $this->owner = $owner;
        $this->title = $title;
        $this->year = $year;
        $this->eventDate = $eventDate;
        $this->minWishAmount = $minWishAmount;
        $this->inviteToken = self::generateInviteToken();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;

        // The creator is the group admin and takes part like everyone else.
        $this->members = new ArrayCollection([new GroupMember($this, $owner, MemberStatus::Active, InvitedVia::Owner)]);
    }

    /** 256 bits, URL-safe, 43 characters. */
    public static function generateInviteToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): void
    {
        $this->year = $year;
    }

    public function getBudgetMin(): ?int
    {
        return $this->budgetMin;
    }

    public function getBudgetMax(): ?int
    {
        return $this->budgetMax;
    }

    public function setBudget(?int $min, ?int $max): void
    {
        $this->budgetMin = $min;
        $this->budgetMax = $max;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getEventDate(): \DateTimeImmutable
    {
        return $this->eventDate;
    }

    public function setEventDate(\DateTimeImmutable $eventDate): void
    {
        $this->eventDate = $eventDate;
    }

    public function getMinWishAmount(): int
    {
        return $this->minWishAmount;
    }

    public function setMinWishAmount(int $minWishAmount): void
    {
        $this->minWishAmount = $minWishAmount;
    }

    public function isDrawed(): bool
    {
        return $this->isDrawed;
    }

    public function getDrawnAt(): ?\DateTimeImmutable
    {
        return $this->drawnAt;
    }

    /** Records a completed draw. The share link stops working. */
    public function markDrawn(): void
    {
        $this->isDrawed = true;
        $this->drawnAt = new \DateTimeImmutable();
        $this->inviteToken = null;
    }

    /** Back to the state before the draw, with a fresh share link. */
    public function markUndrawn(): void
    {
        $this->isDrawed = false;
        $this->drawnAt = null;
        $this->inviteToken = self::generateInviteToken();
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getInviteToken(): ?string
    {
        return $this->inviteToken;
    }

    /** Invalidates the previous share link. */
    public function regenerateInviteToken(): string
    {
        return $this->inviteToken = self::generateInviteToken();
    }

    public function addMember(User $user, MemberStatus $status, InvitedVia $via): GroupMember
    {
        $member = new GroupMember($this, $user, $status, $via);
        $this->members->add($member);

        return $member;
    }

    public function removeMember(GroupMember $member): void
    {
        $this->members->removeElement($member);
    }

    /** @return Collection<int, GroupMember> */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    /** @return list<GroupMember> */
    public function getActiveMembers(): array
    {
        return array_values($this->members->filter(static fn (GroupMember $member) => MemberStatus::Active === $member->getStatus())->toArray());
    }

    public function getMembership(User $user): ?GroupMember
    {
        foreach ($this->members as $member) {
            if ($member->getUser() === $user) {
                return $member;
            }
        }

        return null;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
