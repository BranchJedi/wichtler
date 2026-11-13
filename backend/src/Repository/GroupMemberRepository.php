<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GroupMember;
use App\Entity\User;
use App\Enum\MemberStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<GroupMember> */
final class GroupMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupMember::class);
    }

    /** @return list<GroupMember> */
    public function findPendingInvitations(User $user): array
    {
        return $this->findBy(['user' => $user, 'status' => MemberStatus::Invited], ['id' => 'ASC']);
    }
}
