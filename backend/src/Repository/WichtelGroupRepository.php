<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\WichtelGroup;
use App\Enum\MemberStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<WichtelGroup> */
final class WichtelGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WichtelGroup::class);
    }

    /**
     * Groups the user has joined. Pending invitations are not included.
     *
     * @return list<WichtelGroup>
     */
    public function findJoinedBy(User $user): array
    {
        return $this->createQueryBuilder('g')
            ->innerJoin('g.members', 'm')
            ->where('m.user = :user')
            ->andWhere('m.status != :invited')
            ->setParameter('user', $user)
            ->setParameter('invited', MemberStatus::Invited)
            ->orderBy('g.eventDate', 'DESC')
            ->addOrderBy('g.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByInviteToken(string $token): ?WichtelGroup
    {
        return '' === $token ? null : $this->findOneBy(['inviteToken' => $token]);
    }

    /** @return list<WichtelGroup> */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['eventDate' => 'DESC', 'id' => 'DESC']);
    }
}
