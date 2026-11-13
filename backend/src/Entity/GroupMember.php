<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\InvitedVia;
use App\Enum\MemberStatus;
use App\Repository\GroupMemberRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GroupMemberRepository::class)]
#[ORM\Table(name: 'group_member')]
#[ORM\UniqueConstraint(name: 'uniq_member_group_user', columns: ['group_id', 'user_id'])]
class GroupMember
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'members')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private WichtelGroup $group;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 10, enumType: MemberStatus::class)]
    private MemberStatus $status;

    #[ORM\Column(length: 10, enumType: InvitedVia::class)]
    private InvitedVia $invitedVia;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $joinedAt;

    /** Set when exclusions of this member were removed because the group shrank; shown once as a hint. */
    #[ORM\Column(options: ['default' => false])]
    private bool $exclusionsPruned = false;

    /** @var Collection<int, Wish> */
    #[ORM\OneToMany(targetEntity: Wish::class, mappedBy: 'member', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $wishes;

    public function __construct(WichtelGroup $group, User $user, MemberStatus $status, InvitedVia $invitedVia)
    {
        $this->wishes = new ArrayCollection();
        $this->group = $group;
        $this->user = $user;
        $this->status = $status;
        $this->invitedVia = $invitedVia;
        $this->joinedAt = MemberStatus::Invited === $status ? null : new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGroup(): WichtelGroup
    {
        return $this->group;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getStatus(): MemberStatus
    {
        return $this->status;
    }

    public function getInvitedVia(): InvitedVia
    {
        return $this->invitedVia;
    }

    public function getJoinedAt(): ?\DateTimeImmutable
    {
        return $this->joinedAt;
    }

    /** Accepts an invitation. */
    public function join(InvitedVia $via): void
    {
        $this->status = MemberStatus::Active;
        $this->invitedVia = $via;
        $this->joinedAt = new \DateTimeImmutable();
    }

    /** Switches a joined member between taking part in the draw and sitting it out. */
    public function setParticipating(bool $participating): void
    {
        $this->status = $participating ? MemberStatus::Active : MemberStatus::Inactive;
    }

    public function isOwner(): bool
    {
        return $this->group->getOwner() === $this->user;
    }

    /** @return Collection<int, Wish> */
    public function getWishes(): Collection
    {
        return $this->wishes;
    }

    public function addWish(string $title, ?string $description, ?string $url): Wish
    {
        $last = $this->wishes->last();
        $wish = new Wish($this, $title, $last instanceof Wish ? $last->getPosition() + 1 : 1);
        $wish->update($title, $description, $url);
        $this->wishes->add($wish);

        return $wish;
    }

    public function removeWish(Wish $wish): void
    {
        $this->wishes->removeElement($wish);
    }

    public function hasExclusionsPruned(): bool
    {
        return $this->exclusionsPruned;
    }

    public function setExclusionsPruned(bool $pruned): void
    {
        $this->exclusionsPruned = $pruned;
    }

    public function isParticipating(): bool
    {
        return MemberStatus::Active === $this->status;
    }

    /** Invited users are not part of the group yet. */
    public function hasJoined(): bool
    {
        return MemberStatus::Invited !== $this->status;
    }
}
