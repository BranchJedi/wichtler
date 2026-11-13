<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ExclusionRepository;
use Doctrine\ORM\Mapping as ORM;

/** "I do not want to draw this person." Only ever visible to the member who set it. */
#[ORM\Entity(repositoryClass: ExclusionRepository::class)]
#[ORM\Table(name: 'exclusion')]
#[ORM\UniqueConstraint(name: 'uniq_exclusion_pair', columns: ['member_id', 'excluded_member_id'])]
class Exclusion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private WichtelGroup $group;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private GroupMember $member;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private GroupMember $excludedMember;

    /** Decides which exclusions go first when the group shrinks. */
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(GroupMember $member, GroupMember $excludedMember)
    {
        $this->group = $member->getGroup();
        $this->member = $member;
        $this->excludedMember = $excludedMember;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMember(): GroupMember
    {
        return $this->member;
    }

    public function getExcludedMember(): GroupMember
    {
        return $this->excludedMember;
    }
}
