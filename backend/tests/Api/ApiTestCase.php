<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\GroupMember;
use App\Entity\User;
use App\Entity\WichtelGroup;
use App\Enum\InvitedVia;
use App\Enum\MemberStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiTestCase extends WebTestCase
{
    protected const string PASSWORD = 'correct-horse-battery';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        // The session cookies are "Secure", so the browser has to speak HTTPS to send them back.
        $this->client = self::createClient([], ['HTTPS' => 'on']);

        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $connection->executeStatement('DELETE FROM refresh_tokens');
        $connection->executeStatement('DELETE FROM wichtel_group');
        $connection->executeStatement('DELETE FROM app_user');

        // Throttling state must not leak from one test into the next.
        self::getContainer()->get('cache.rate_limiter')->clear();
    }

    /** A second, independent browser (another device) against the same kernel. */
    protected function newBrowser(): KernelBrowser
    {
        return new KernelBrowser(self::$kernel, ['HTTPS' => 'on']);
    }

    /** @param list<string> $roles */
    protected function createUser(string $username, array $roles = [], string $firstName = 'Anna', string $lastName = 'Muster'): User
    {
        $container = self::getContainer();
        $user = new User($firstName, $lastName, $username);
        $user->setRoles($roles);
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));

        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    /** A group owned by $owner, with further users as active participants. */
    protected function createGroup(User $owner, string $title = 'Familie', User ...$participants): WichtelGroup
    {
        $group = new WichtelGroup($owner, $title, 2026, new \DateTimeImmutable('2026-12-24'), 3);
        foreach ($participants as $participant) {
            $group->getMembers()->add(new GroupMember($group, $participant, MemberStatus::Active, InvitedVia::Link));
        }

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($group);
        $entityManager->flush();

        return $group;
    }

    /** Gives every active participant of the group enough wishes for a draw. */
    protected function fillWishes(WichtelGroup $group): void
    {
        foreach ($group->getActiveMembers() as $member) {
            for ($i = $member->getWishes()->count() + 1; $i <= $group->getMinWishAmount(); ++$i) {
                $member->addWish(\sprintf('Wunsch %d von %s', $i, $member->getUser()->getUsername()), null, null);
            }
        }
        self::getContainer()->get(EntityManagerInterface::class)->flush();
    }

    /**
     * The draw as stored in the database: giver username => receiver username.
     *
     * @return array<string, string>
     */
    protected function storedAssignments(WichtelGroup $group): array
    {
        return self::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchAllKeyValue(
            'SELECT gu.username, ru.username FROM assignment a
             JOIN group_member gm ON gm.id = a.giver_id JOIN app_user gu ON gu.id = gm.user_id
             JOIN group_member rm ON rm.id = a.receiver_id JOIN app_user ru ON ru.id = rm.user_id
             WHERE a.group_id = ? ORDER BY gu.username',
            [$group->getId()],
        );
    }

    protected function memberIdOf(WichtelGroup $group, User $user): int
    {
        return (int) self::getContainer()->get(EntityManagerInterface::class)->getConnection()
            ->fetchOne('SELECT id FROM group_member WHERE group_id = ? AND user_id = ?', [$group->getId(), $user->getId()]);
    }

    /** Sets the "drawn" flag without a draw, for tests that only care about the locks. */
    protected function markAsDrawn(WichtelGroup $group): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->getConnection()
            ->executeStatement('UPDATE wichtel_group SET is_drawed = 1, drawn_at = NOW() WHERE id = ?', [$group->getId()]);
    }

    protected function login(string $username, string $password = self::PASSWORD, ?KernelBrowser $browser = null): void
    {
        ($browser ?? $this->client)->jsonRequest('POST', '/api/auth/login', ['username' => $username, 'password' => $password]);
    }

    /** @return array<string, mixed> */
    protected function json(?KernelBrowser $browser = null): array
    {
        return json_decode((string) ($browser ?? $this->client)->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /** @return list<string> */
    protected function violationPaths(): array
    {
        return array_column($this->json()['violations'] ?? [], 'propertyPath');
    }
}
