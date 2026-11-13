<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<User> */
final class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByUsername(string $username): ?User
    {
        return $this->findOneBy(['username' => User::normalizeUsername($username)]);
    }

    /**
     * Users whose name or username contains the search text, by name.
     *
     * @return list<User>
     */
    public function search(string $query, int $limit = 100): array
    {
        $builder = $this->createQueryBuilder('u')
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->addOrderBy('u.id', 'ASC')
            ->setMaxResults($limit);

        foreach (preg_split('/\\s+/', trim($query), -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $index => $word) {
            // Every word has to match somewhere, so "anna mus" finds Anna Muster.
            $builder
                ->andWhere(\sprintf('u.firstName LIKE :w%1$d OR u.lastName LIKE :w%1$d OR u.username LIKE :w%1$d', $index))
                ->setParameter('w'.$index, '%'.addcslashes($word, '%_\\').'%');
        }

        return $builder->getQuery()->getResult();
    }

    public function hasAdmin(): bool
    {
        // Roles are a small JSON list; the quoted needle cannot match a prefix of another role.
        return null !== $this->createQueryBuilder('u')
            ->select('u.id')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%"'.User::ROLE_ADMIN.'"%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
