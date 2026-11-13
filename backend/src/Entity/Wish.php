<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** A wish belongs to one membership: the same user has separate wish lists in separate groups. */
#[ORM\Entity]
#[ORM\Table(name: 'wish')]
class Wish
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'wishes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private GroupMember $member;

    #[ORM\Column(length: 160)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $position;

    public function __construct(GroupMember $member, string $title, int $position)
    {
        $this->member = $member;
        $this->title = $title;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMember(): GroupMember
    {
        return $this->member;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function update(string $title, ?string $description, ?string $url): void
    {
        $this->title = $title;
        $this->description = $description;
        $this->url = $url;
    }
}
