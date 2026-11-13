<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AssignmentRepository;
use Doctrine\ORM\Mapping as ORM;

/** Result of a draw: the giver gives a present to the receiver. */
#[ORM\Entity(repositoryClass: AssignmentRepository::class)]
#[ORM\Table(name: 'assignment')]
#[ORM\UniqueConstraint(name: 'uniq_assignment_giver', columns: ['group_id', 'giver_id'])]
#[ORM\UniqueConstraint(name: 'uniq_assignment_receiver', columns: ['group_id', 'receiver_id'])]
class Assignment
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
    private GroupMember $giver;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private GroupMember $receiver;

    public function __construct(GroupMember $giver, GroupMember $receiver)
    {
        $this->group = $giver->getGroup();
        $this->giver = $giver;
        $this->receiver = $receiver;
    }

    public function getGiver(): GroupMember
    {
        return $this->giver;
    }

    public function getReceiver(): GroupMember
    {
        return $this->receiver;
    }
}
