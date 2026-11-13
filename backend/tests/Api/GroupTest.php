<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\GroupMember;
use App\Entity\User;
use App\Entity\WichtelGroup;
use App\Enum\InvitedVia;
use App\Enum\MemberStatus;
use Doctrine\ORM\EntityManagerInterface;

final class GroupTest extends ApiTestCase
{
    private const array GROUP = [
        'title' => 'Familie Muster',
        'description' => 'Bei Oma im Wohnzimmer',
        'year' => 2026,
        'budgetMin' => 20,
        'budgetMax' => 40,
        'currency' => 'EUR',
        'eventDate' => '2026-12-24',
        'minWishAmount' => 3,
    ];

    public function testCreatorBecomesGroupAdminAndFirstParticipant(): void
    {
        $this->createUser('anna');
        $this->login('anna');

        $this->client->jsonRequest('POST', '/api/groups', self::GROUP);

        self::assertResponseStatusCodeSame(201);
        $group = $this->json();
        self::assertSame('Familie Muster', $group['title']);
        self::assertSame('2026-12-24', $group['eventDate']);
        self::assertSame([20, 40, 'EUR'], [$group['budgetMin'], $group['budgetMax'], $group['currency']]);
        self::assertSame('anna', $group['owner']['username']);
        self::assertSame(1, $group['participantCount']);
        self::assertTrue($group['isOwner']);
        self::assertTrue($group['isMember']);
        self::assertTrue($group['canManage']);
        self::assertFalse($group['isDrawed']);
        self::assertFalse($group['drawable'], 'one participant is not enough for a draw');
        self::assertArrayNotHasKey('inviteToken', $group, 'the share link secret is not part of the group data');
    }

    public function testBudgetIsOptional(): void
    {
        $this->createUser('anna');
        $this->login('anna');

        $this->client->jsonRequest('POST', '/api/groups', ['budgetMin' => null, 'budgetMax' => null] + self::GROUP);

        self::assertResponseStatusCodeSame(201);
        self::assertNull($this->json()['budgetMin']);
        self::assertNull($this->json()['budgetMax']);
    }

    public function testInvalidGroupIsRejected(): void
    {
        $this->createUser('anna');
        $this->login('anna');

        $this->client->jsonRequest('POST', '/api/groups', [
            'title' => ' ',
            'year' => 1999,
            'budgetMin' => 50,
            'budgetMax' => 10,
            'currency' => 'XYZ',
            'eventDate' => '24.12.2026',
            'minWishAmount' => 21,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertEqualsCanonicalizing(
            ['title', 'year', 'budgetMax', 'currency', 'eventDate', 'minWishAmount'],
            $this->violationPaths(),
        );
    }

    public function testOverviewListsOnlyJoinedGroups(): void
    {
        $anna = $this->createUser('anna');
        $ben = $this->createUser('ben');
        $this->createGroup($anna, 'Annas Gruppe');
        $this->createGroup($ben, 'Bens Gruppe', $anna);
        $this->createGroup($ben, 'Ohne Anna');
        $invitedOnly = $this->createGroup($ben, 'Nur eingeladen');
        $invitedOnly->getMembers()->add(new GroupMember($invitedOnly, $anna, MemberStatus::Invited, InvitedVia::Username));
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->login('anna');

        $this->client->request('GET', '/api/groups');

        self::assertResponseIsSuccessful();
        self::assertEqualsCanonicalizing(['Annas Gruppe', 'Bens Gruppe'], array_column($this->json(), 'title'));
        $bens = array_values(array_filter($this->json(), static fn (array $group) => 'Bens Gruppe' === $group['title']))[0];
        self::assertFalse($bens['isOwner']);
        self::assertFalse($bens['canManage']);
        self::assertSame(2, $bens['participantCount']);
    }

    public function testOverviewNeverContainsDrawResults(): void
    {
        $anna = $this->createUser('anna');
        $group = $this->createGroup($anna, 'Familie', $this->createUser('ben'), $this->createUser('carla'));
        $this->markAsDrawn($group);
        $this->login('anna');

        $this->client->request('GET', '/api/groups');

        self::assertTrue($this->json()[0]['isDrawed']);
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('carla', $content);
        self::assertStringNotContainsString('ben', $content);
    }

    public function testOnlyTheWichtlerAdminSeesAllGroups(): void
    {
        $anna = $this->createUser('anna');
        $this->createUser('chef', [User::ROLE_ADMIN]);
        $this->createGroup($anna, 'Annas Gruppe');

        $this->login('anna');
        $this->client->request('GET', '/api/groups?scope=all');
        self::assertResponseStatusCodeSame(403);

        $admin = $this->newBrowser();
        $this->login('chef', browser: $admin);
        $admin->request('GET', '/api/groups?scope=all');
        self::assertSame(200, $admin->getResponse()->getStatusCode());
        $groups = $this->json($admin);
        self::assertSame(['Annas Gruppe'], array_column($groups, 'title'));
        self::assertFalse($groups[0]['isMember']);
        self::assertTrue($groups[0]['canManage']);

        $admin->request('GET', '/api/groups');
        self::assertSame([], $this->json($admin), 'the default scope stays "mine" for the admin too');
    }

    public function testStrangersCannotSeeAGroup(): void
    {
        $group = $this->createGroup($this->createUser('anna'));
        $this->createUser('fremd');
        $this->login('fremd');

        $this->client->request('GET', '/api/groups/'.$group->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testInvitedUsersCannotSeeTheGroupYet(): void
    {
        $group = $this->createGroup($this->createUser('anna'));
        $group->getMembers()->add(new GroupMember($group, $this->createUser('ben'), MemberStatus::Invited, InvitedVia::Username));
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->login('ben');

        $this->client->request('GET', '/api/groups/'.$group->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testUnknownGroupIsNotFound(): void
    {
        $this->createUser('anna');
        $this->login('anna');

        $this->client->request('GET', '/api/groups/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testParticipantsCanViewButNotManage(): void
    {
        $ben = $this->createUser('ben');
        $group = $this->createGroup($this->createUser('anna'), 'Familie', $ben);
        $this->login('ben');

        $this->client->request('GET', '/api/groups/'.$group->getId());
        self::assertResponseIsSuccessful();
        self::assertFalse($this->json()['canManage']);

        $this->client->jsonRequest('PATCH', '/api/groups/'.$group->getId(), self::GROUP);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('DELETE', '/api/groups/'.$group->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testGroupAdminCanEdit(): void
    {
        $group = $this->createGroup($this->createUser('anna'));
        $this->login('anna');

        $this->client->jsonRequest('PATCH', '/api/groups/'.$group->getId(), ['description' => '  '] + self::GROUP);

        self::assertResponseIsSuccessful();
        self::assertSame('Familie Muster', $this->json()['title']);
        self::assertSame(40, $this->json()['budgetMax']);
        self::assertNull($this->json()['description'], 'blank descriptions are stored as none');
    }

    public function testWichtlerAdminCanEditAndDeleteForeignGroups(): void
    {
        $group = $this->createGroup($this->createUser('anna'));
        $this->createUser('chef', [User::ROLE_ADMIN]);
        $this->login('chef');

        $this->client->jsonRequest('PATCH', '/api/groups/'.$group->getId(), self::GROUP);
        self::assertResponseIsSuccessful();

        $this->client->request('DELETE', '/api/groups/'.$group->getId());
        self::assertResponseStatusCodeSame(204);
    }

    public function testGroupAdminCanDelete(): void
    {
        $anna = $this->createUser('anna');
        $group = $this->createGroup($anna, 'Familie', $this->createUser('ben'));
        $id = $group->getId();
        $this->login('anna');

        $this->client->request('DELETE', '/api/groups/'.$id);
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/groups/'.$id);
        self::assertResponseStatusCodeSame(404);
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM group_member'), 'memberships go with the group');
        self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM app_user'), 'users stay');
    }

    public function testDrawnGroupCannotBeDeletedNotEvenByTheWichtlerAdmin(): void
    {
        $group = $this->createGroup($this->createUser('anna'));
        $this->createUser('chef', [User::ROLE_ADMIN]);
        $this->markAsDrawn($group);

        $this->login('anna');
        $this->client->request('DELETE', '/api/groups/'.$group->getId());
        self::assertResponseStatusCodeSame(409);

        $admin = $this->newBrowser();
        $this->login('chef', browser: $admin);
        $admin->request('DELETE', '/api/groups/'.$group->getId());
        self::assertSame(409, $admin->getResponse()->getStatusCode());
    }

    public function testDrawLocksTheRulesButNotTheTitleAndDate(): void
    {
        $group = $this->createGroup($this->createUser('anna'));
        $this->markAsDrawn($group);
        $this->login('anna');
        $unchangedRules = ['year' => 2026, 'budgetMin' => null, 'budgetMax' => null, 'currency' => 'CHF', 'minWishAmount' => 3];

        $this->client->jsonRequest('PATCH', '/api/groups/'.$group->getId(), ['title' => 'Neuer Titel', 'eventDate' => '2026-12-26'] + $unchangedRules);
        self::assertResponseIsSuccessful();
        self::assertSame('Neuer Titel', $this->json()['title']);
        self::assertSame('2026-12-26', $this->json()['eventDate']);

        foreach (['year' => 2027, 'budgetMax' => 50, 'currency' => 'EUR', 'minWishAmount' => 1] as $field => $value) {
            $this->client->jsonRequest('PATCH', '/api/groups/'.$group->getId(), [$field => $value, 'title' => 'x', 'eventDate' => '2026-12-26'] + $unchangedRules);
            self::assertResponseStatusCodeSame(409, $field);
        }
    }

    public function testGroupsRequireASession(): void
    {
        $this->client->request('GET', '/api/groups');

        self::assertResponseStatusCodeSame(401);
    }
}
