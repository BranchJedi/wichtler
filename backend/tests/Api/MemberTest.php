<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\GroupMember;
use App\Entity\User;
use App\Entity\WichtelGroup;
use App\Enum\InvitedVia;
use App\Enum\MemberStatus;
use Doctrine\ORM\EntityManagerInterface;

final class MemberTest extends ApiTestCase
{
    private User $anna;
    private User $ben;
    private WichtelGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->anna = $this->createUser('anna', firstName: 'Anna');
        $this->ben = $this->createUser('ben', firstName: 'Ben');
        $this->group = $this->createGroup($this->anna, 'Familie', $this->ben);
    }

    private function path(string $suffix = ''): string
    {
        return '/api/groups/'.$this->group->getId().'/members'.$suffix;
    }

    private function memberId(User $user): int
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $member = $entityManager->getRepository(GroupMember::class)->findOneBy(['group' => $this->group->getId(), 'user' => $user->getId()]);
        self::assertInstanceOf(GroupMember::class, $member);

        return (int) $member->getId();
    }

    private function memberStatus(User $user): ?string
    {
        $status = self::getContainer()->get(EntityManagerInterface::class)->getConnection()
            ->fetchOne('SELECT status FROM group_member WHERE group_id = ? AND user_id = ?', [$this->group->getId(), $user->getId()]);

        return false === $status ? null : $status;
    }

    public function testParticipantsSeeEachOther(): void
    {
        $this->login('ben');

        $this->client->request('GET', $this->path());

        self::assertResponseIsSuccessful();
        $members = $this->json();
        self::assertSame(['anna', 'ben'], array_column($members, 'username'), 'group admin first');
        self::assertSame([true, false], array_column($members, 'isOwner'));
        self::assertSame([false, true], array_column($members, 'isMe'));
        self::assertSame(['active', 'active'], array_column($members, 'status'));
    }

    public function testPendingInvitationsAreOnlyListedForTheGroupAdmin(): void
    {
        $this->createUser('carla');
        $this->login('anna');
        $this->client->jsonRequest('POST', $this->path(), ['username' => 'Carla']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('invited', $this->json()['status']);

        $this->client->request('GET', $this->path());
        self::assertSame(['anna', 'ben', 'carla'], array_column($this->json(), 'username'));

        $ben = $this->newBrowser();
        $this->login('ben', browser: $ben);
        $ben->request('GET', $this->path());
        self::assertSame(['anna', 'ben'], array_column($this->json($ben), 'username'));
    }

    public function testInvitationNeedsAnExistingUserWhoIsNotInTheGroup(): void
    {
        $this->createUser('carla');
        $this->login('anna');

        $this->client->jsonRequest('POST', $this->path(), ['username' => 'niemand']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['username'], $this->violationPaths());

        $this->client->jsonRequest('POST', $this->path(), ['username' => 'ben']);
        self::assertResponseStatusCodeSame(422, 'already a participant');

        $this->client->jsonRequest('POST', $this->path(), ['username' => 'carla']);
        $this->client->jsonRequest('POST', $this->path(), ['username' => 'carla']);
        self::assertResponseStatusCodeSame(422, 'already invited');
    }

    public function testOnlyTheGroupAdminManagesMembers(): void
    {
        $this->createUser('carla');
        $this->login('ben');

        $this->client->jsonRequest('POST', $this->path(), ['username' => 'carla']);
        self::assertResponseStatusCodeSame(403);
        $this->client->jsonRequest('PATCH', $this->path('/'.$this->memberId($this->ben)), ['status' => 'inactive']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('DELETE', $this->path('/'.$this->memberId($this->anna)));
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/api/groups/'.$this->group->getId().'/invite-link');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/api/groups/'.$this->group->getId().'/invite-link');
        self::assertResponseStatusCodeSame(403);
    }

    public function testStrangersSeeNoMembers(): void
    {
        $this->createUser('fremd');
        $this->login('fremd');

        $this->client->request('GET', $this->path());

        self::assertResponseStatusCodeSame(403);
    }

    public function testParticipantCanBeDeactivatedAndReactivated(): void
    {
        $this->login('anna');
        $benId = $this->memberId($this->ben);

        $this->client->jsonRequest('PATCH', $this->path('/'.$benId), ['status' => 'inactive']);
        self::assertResponseIsSuccessful();
        self::assertSame('inactive', $this->json()['status']);

        $this->client->request('GET', '/api/groups/'.$this->group->getId());
        self::assertSame(1, $this->json()['participantCount'], 'inactive members do not count as participants');
        $this->client->request('GET', $this->path());
        self::assertSame(['anna', 'ben'], array_column($this->json(), 'username'), 'but they stay listed');

        $this->client->jsonRequest('PATCH', $this->path('/'.$benId), ['status' => 'active']);
        self::assertSame('active', $this->json()['status']);

        $this->client->jsonRequest('PATCH', $this->path('/'.$benId), ['status' => 'invited']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testGroupAdminCannotBeDeactivatedOrRemoved(): void
    {
        $this->createUser('chef', [User::ROLE_ADMIN]);
        $this->login('chef');
        $annaId = $this->memberId($this->anna);

        $this->client->jsonRequest('PATCH', $this->path('/'.$annaId), ['status' => 'inactive']);
        self::assertResponseStatusCodeSame(409);
        $this->client->request('DELETE', $this->path('/'.$annaId));
        self::assertResponseStatusCodeSame(409);
    }

    public function testGroupAdminRemovesParticipantsAndWithdrawsInvitations(): void
    {
        $carla = $this->createUser('carla');
        $this->login('anna');
        $this->client->jsonRequest('POST', $this->path(), ['username' => 'carla']);

        $this->client->request('DELETE', $this->path('/'.$this->memberId($this->ben)));
        self::assertResponseStatusCodeSame(204);
        $this->client->request('DELETE', $this->path('/'.$this->memberId($carla)));
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', $this->path());
        self::assertSame(['anna'], array_column($this->json(), 'username'));

        $ben = $this->newBrowser();
        $this->login('ben', browser: $ben);
        $ben->request('GET', '/api/groups/'.$this->group->getId());
        self::assertSame(403, $ben->getResponse()->getStatusCode(), 'a removed participant loses access');
    }

    public function testMemberOfAnotherGroupIsNotFound(): void
    {
        $other = $this->createGroup($this->anna, 'Andere', $this->createUser('carla'));
        $foreignMemberId = (int) $other->getMembers()->last()->getId();
        $this->login('anna');

        $this->client->request('DELETE', $this->path('/'.$foreignMemberId));

        self::assertResponseStatusCodeSame(404);
    }

    public function testParticipantCanLeaveButTheGroupAdminCannot(): void
    {
        $this->login('ben');
        $this->client->request('DELETE', $this->path('/me'));
        self::assertResponseStatusCodeSame(204);
        self::assertNull($this->memberStatus($this->ben));

        $this->client->request('GET', '/api/groups');
        self::assertSame([], $this->json());

        $anna = $this->newBrowser();
        $this->login('anna', browser: $anna);
        $anna->request('DELETE', $this->path('/me'));
        self::assertSame(409, $anna->getResponse()->getStatusCode());
    }

    public function testDrawLocksTheMemberList(): void
    {
        $this->createUser('carla');
        $this->markAsDrawn($this->group);
        $this->login('anna');
        $benId = $this->memberId($this->ben);

        $this->client->jsonRequest('POST', $this->path(), ['username' => 'carla']);
        self::assertResponseStatusCodeSame(409);
        $this->client->jsonRequest('PATCH', $this->path('/'.$benId), ['status' => 'inactive']);
        self::assertResponseStatusCodeSame(409);
        $this->client->request('DELETE', $this->path('/'.$benId));
        self::assertResponseStatusCodeSame(409);
        $this->client->request('GET', '/api/groups/'.$this->group->getId().'/invite-link');
        self::assertResponseStatusCodeSame(409);
        $this->client->request('POST', '/api/groups/'.$this->group->getId().'/invite-link');
        self::assertResponseStatusCodeSame(409);

        $ben = $this->newBrowser();
        $this->login('ben', browser: $ben);
        $ben->request('DELETE', $this->path('/me'));
        self::assertSame(409, $ben->getResponse()->getStatusCode());
        self::assertSame('active', $this->memberStatus($this->ben));
    }
}
