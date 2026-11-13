<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Assignment;
use App\Entity\GroupMember;
use App\Entity\WichtelGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Assignment> */
final class AssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Assignment::class);
    }

    public function findOneByGiver(GroupMember $giver): ?Assignment
    {
        return $this->findOneBy(['giver' => $giver]);
    }

    public function deleteByGroup(WichtelGroup $group): void
    {
        $this->createQueryBuilder('a')->delete()->where('a.group = :group')->setParameter('group', $group)->getQuery()->execute();
    }
}
