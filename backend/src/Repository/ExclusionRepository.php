<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exclusion;
use App\Entity\GroupMember;
use App\Entity\WichtelGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Exclusion> */
final class ExclusionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exclusion::class);
    }

    /**
     * All exclusions of a group, newest first.
     *
     * @return list<Exclusion>
     */
    public function findByGroup(WichtelGroup $group): array
    {
        return $this->findBy(['group' => $group], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    /** @return list<Exclusion> */
    public function findByMember(GroupMember $member): array
    {
        return $this->findBy(['member' => $member], ['id' => 'ASC']);
    }

    public function countByGroup(WichtelGroup $group): int
    {
        return $this->count(['group' => $group]);
    }
}
