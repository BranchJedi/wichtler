<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User;
use App\Entity\WichtelGroup;
use App\Enum\InvitedVia;
use App\Enum\MemberStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class DrawTest extends ApiTestCase
{
    /** @var array<string, User> */
    private array $users = [];
    private WichtelGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['anna', 'ben', 'carla', 'dora'] as $name) {
            $this->users[$name] = $this->createUser($name, firstName: ucfirst($name));
        }
        $this->group = $this->createGroup($this->users['anna'], 'Familie', $this->users['ben'], $this->users['carla'], $this->users['dora']);
        $this->fillWishes($this->group);
    }

    private function path(string $suffix = ''): string
    {
        return '/api/groups/'.$this->group->getId().'/draw'.$suffix;
    }

    private function as(string $name): KernelBrowser
    {
        $browser = $this->newBrowser();
        $this->login($name, browser: $browser);

        return $browser;
    }

    private function draw(string $as = 'anna'): KernelBrowser
    {
        $browser = $this->as($as);
        $browser->request('POST', $this->path());

        return $browser;
    }

    public function testGroupAdminDrawsTheGroup(): void
    {
        $anna = $this->draw();

        self::assertSame(200, $anna->getResponse()->getStatusCode());
        $group = $this->json($anna);
        self::assertTrue($group['isDrawed']);
        self::assertFalse($group['drawable']);
        self::assertNotNull($group['drawnAt']);
        foreach (['ben', 'carla', 'dora'] as $name) {
            self::assertStringNotContainsString($name, (string) $anna->getResponse()->getContent(), 'the response reveals nothing about the result');
        }

        $assignments = $this->storedAssignments($this->group);
        self::assertSame(['anna', 'ben', 'carla', 'dora'], array_keys($assignments), 'everybody gives');
        self::assertEqualsCanonicalizing(['anna', 'ben', 'carla', 'dora'], array_values($assignments), 'everybody receives exactly once');
        foreach ($assignments as $giver => $receiver) {
            self::assertNotSame($giver, $receiver, 'nobody draws themselves');
        }
    }

    public function testDrawNeedsEnoughWishesAndParticipants(): void
    {
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $connection->executeStatement('DELETE FROM wish WHERE member_id = ? LIMIT 1', [$this->memberIdOf($this->group, $this->users['dora'])]);

        $anna = $this->draw();
        self::assertSame(409, $anna->getResponse()->getStatusCode(), 'dora lacks a wish');
        self::assertSame([], $this->storedAssignments($this->group));

        $anna->request('DELETE', '/api/groups/'.$this->group->getId().'/members/'.$this->memberIdOf($this->group, $this->users['dora']));
        $anna->request('DELETE', '/api/groups/'.$this->group->getId().'/members/'.$this->memberIdOf($this->group, $this->users['carla']));
        $anna->request('POST', $this->path());
        self::assertSame(409, $anna->getResponse()->getStatusCode(), 'two participants are not enough');
    }

    public function testGroupCannotBeDrawnTwice(): void
    {
        $anna = $this->draw();
        $first = $this->storedAssignments($this->group);

        $anna->request('POST', $this->path());

        self::assertSame(409, $anna->getResponse()->getStatusCode());
        self::assertSame($first, $this->storedAssignments($this->group), 'the existing draw is untouched');
    }

    public function testOnlyGroupAdminAndWichtlerAdminMayDraw(): void
    {
        self::assertSame(403, $this->draw('ben')->getResponse()->getStatusCode());
        self::assertSame([], $this->storedAssignments($this->group));

        $this->createUser('chef', [User::ROLE_ADMIN]);
        self::assertSame(200, $this->draw('chef')->getResponse()->getStatusCode());
    }

    public function testThreeParticipantsFormACircle(): void
    {
        $anna = $this->as('anna');
        $anna->request('DELETE', '/api/groups/'.$this->group->getId().'/members/'.$this->memberIdOf($this->group, $this->users['dora']));
        $anna->request('POST', $this->path());
        self::assertSame(200, $anna->getResponse()->getStatusCode());

        $assignments = $this->storedAssignments($this->group);
        self::assertCount(3, $assignments);
        self::assertSame('anna', $assignments[$assignments[$assignments['anna']]], 'following the chain three times returns to the start');
    }

    public function testExclusionsAreRespectedInEveryDraw(): void
    {
        $ben = $this->as('ben');
        $ben->request('PUT', '/api/groups/'.$this->group->getId().'/exclusions/'.$this->memberIdOf($this->group, $this->users['carla']));
        self::assertSame(200, $ben->getResponse()->getStatusCode());
        $anna = $this->as('anna');

        $seen = [];
        for ($round = 0; $round < 30; ++$round) {
            $anna->request('POST', $this->path());
            self::assertSame(200, $anna->getResponse()->getStatusCode());
            $assignments = $this->storedAssignments($this->group);
            self::assertNotSame('carla', $assignments['ben']);
            $seen[$assignments['ben']] = true;
            $anna->request('DELETE', $this->path());
            self::assertSame(200, $anna->getResponse()->getStatusCode());
        }

        self::assertEqualsCanonicalizing(['anna', 'dora'], array_keys($seen), 'both remaining people do get drawn by ben');
    }

    public function testDrawDropsPendingInvitationsAndSkipsInactiveMembers(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->group->addMember($this->createUser('emil'), MemberStatus::Invited, InvitedVia::Username);
        $this->group->addMember($this->createUser('frida'), MemberStatus::Inactive, InvitedVia::Link);
        $entityManager->flush();
        $oldLink = '/api/invite-links/'.$this->group->getInviteToken();

        $anna = $this->draw();
        self::assertSame(200, $anna->getResponse()->getStatusCode());

        self::assertCount(4, $this->storedAssignments($this->group));
        $anna->request('GET', '/api/groups/'.$this->group->getId().'/members');
        self::assertSame(['anna', 'ben', 'carla', 'dora', 'frida'], array_column($this->json($anna), 'username'), 'the invitation is gone, the inactive member stays listed');

        $frida = $this->as('frida');
        $frida->request('GET', $this->path('/my-assignment'));
        self::assertSame(409, $frida->getResponse()->getStatusCode(), 'inactive members were not drawn');

        $emil = $this->as('emil');
        $emil->request('GET', '/api/invitations');
        self::assertSame([], $this->json($emil));
        $emil->request('GET', $oldLink);
        self::assertSame(404, $emil->getResponse()->getStatusCode(), 'the share link died with the draw');
    }

    // --- who sees what ---

    public function testParticipantSeesOnlyTheOwnAssignmentWithTheWishes(): void
    {
        $ben = $this->as('ben');
        $ben->request('GET', $this->path('/my-assignment'));
        self::assertSame(409, $ben->getResponse()->getStatusCode(), 'nothing before the draw');

        $this->draw();
        $receiver = $this->storedAssignments($this->group)['ben'];

        $ben->request('GET', $this->path('/my-assignment'));
        self::assertSame(200, $ben->getResponse()->getStatusCode());
        $assignment = $this->json($ben);
        self::assertSame($receiver, $assignment['receiverUsername']);
        self::assertSame(ucfirst($receiver), $assignment['receiverFirstName']);
        self::assertSame(["Wunsch 1 von $receiver", "Wunsch 2 von $receiver", "Wunsch 3 von $receiver"], array_column($assignment['wishes'], 'title'));
        self::assertStringContainsString('no-store', (string) $ben->getResponse()->headers->get('Cache-Control'));

        $others = array_diff(['anna', 'carla', 'dora'], [$receiver]);
        foreach ($others as $other) {
            self::assertStringNotContainsString("von $other", (string) $ben->getResponse()->getContent(), 'no wishes of anybody else');
        }
    }

    public function testGroupAdminCannotSeeAssignmentsOfOthers(): void
    {
        $anna = $this->draw();
        $own = $this->storedAssignments($this->group)['anna'];

        foreach (['ben', 'carla', 'dora'] as $name) {
            $anna->request('GET', $this->path('/assignments/'.$this->memberIdOf($this->group, $this->users[$name])));
            self::assertSame(403, $anna->getResponse()->getStatusCode(), $name);
        }
        $anna->request('GET', $this->path('/assignments/'.$this->memberIdOf($this->group, $this->users['anna'])));
        self::assertSame(403, $anna->getResponse()->getStatusCode(), 'not even through this endpoint for herself');

        // Everything else the group admin can load after the draw mentions no receiver.
        foreach (['', '/members', '/draw/status', '/wishes', '/exclusions'] as $suffix) {
            $anna->request('GET', '/api/groups/'.$this->group->getId().$suffix);
            self::assertSame(200, $anna->getResponse()->getStatusCode(), $suffix);
            self::assertStringNotContainsString('receiver', (string) $anna->getResponse()->getContent(), $suffix);
        }
        $anna->request('GET', '/api/groups');
        self::assertStringNotContainsString('receiver', (string) $anna->getResponse()->getContent());

        $anna->request('GET', $this->path('/my-assignment'));
        self::assertSame($own, $this->json($anna)['receiverUsername'], 'like every participant she sees her own');
    }

    public function testParticipantsCannotSeeAssignmentsOfOthers(): void
    {
        $this->draw();
        $ben = $this->as('ben');

        $ben->request('GET', $this->path('/assignments/'.$this->memberIdOf($this->group, $this->users['carla'])));

        self::assertSame(403, $ben->getResponse()->getStatusCode());
    }

    public function testWichtlerAdminSeesEveryAssignmentOneByOne(): void
    {
        $this->createUser('chef', [User::ROLE_ADMIN]);
        $chef = $this->as('chef');
        $chef->request('GET', $this->path('/assignments/'.$this->memberIdOf($this->group, $this->users['ben'])));
        self::assertSame(409, $chef->getResponse()->getStatusCode(), 'nothing before the draw');

        $this->draw();
        $stored = $this->storedAssignments($this->group);

        foreach ($stored as $giver => $receiver) {
            $chef->request('GET', $this->path('/assignments/'.$this->memberIdOf($this->group, $this->users[$giver])));
            self::assertSame(200, $chef->getResponse()->getStatusCode());
            self::assertSame($receiver, $this->json($chef)['receiverUsername']);
            self::assertCount(3, $this->json($chef)['wishes']);
            self::assertStringContainsString('no-store', (string) $chef->getResponse()->headers->get('Cache-Control'));
        }

        $chef->request('GET', $this->path('/assignments/999999'));
        self::assertSame(404, $chef->getResponse()->getStatusCode());
        $chef->request('GET', $this->path('/my-assignment'));
        self::assertSame(409, $chef->getResponse()->getStatusCode(), 'he does not take part himself');
    }

    public function testStrangersGetNothing(): void
    {
        $this->draw();
        $this->createUser('fremd');
        $stranger = $this->as('fremd');

        foreach (['/my-assignment', '/assignments/'.$this->memberIdOf($this->group, $this->users['ben']), '/status'] as $suffix) {
            $stranger->request('GET', $this->path($suffix));
            self::assertSame(403, $stranger->getResponse()->getStatusCode(), $suffix);
        }
    }

    // --- deleting the draw ---

    public function testDeletingTheDrawUnlocksTheGroup(): void
    {
        $anna = $this->draw();

        $anna->request('DELETE', $this->path());
        self::assertSame(200, $anna->getResponse()->getStatusCode());
        self::assertFalse($this->json($anna)['isDrawed']);
        self::assertNull($this->json($anna)['drawnAt']);
        self::assertTrue($this->json($anna)['drawable']);
        self::assertSame([], $this->storedAssignments($this->group));

        $anna->request('GET', $this->path('/my-assignment'));
        self::assertSame(409, $anna->getResponse()->getStatusCode());
        $anna->request('GET', '/api/groups/'.$this->group->getId().'/invite-link');
        self::assertSame(200, $anna->getResponse()->getStatusCode(), 'there is a share link again');
        $anna->jsonRequest('PATCH', '/api/groups/'.$this->group->getId().'/members/'.$this->memberIdOf($this->group, $this->users['dora']), ['status' => 'inactive']);
        self::assertSame(200, $anna->getResponse()->getStatusCode(), 'members can be changed again');
    }

    public function testGroupCanBeDrawnAgainAndDeletedAfterwards(): void
    {
        $anna = $this->draw();
        $anna->request('DELETE', $this->path());
        $anna->request('POST', $this->path());
        self::assertSame(200, $anna->getResponse()->getStatusCode());
        self::assertCount(4, $this->storedAssignments($this->group));

        $anna->request('DELETE', '/api/groups/'.$this->group->getId());
        self::assertSame(409, $anna->getResponse()->getStatusCode(), 'drawn groups cannot be deleted');
        $anna->request('DELETE', $this->path());
        $anna->request('DELETE', '/api/groups/'.$this->group->getId());
        self::assertSame(204, $anna->getResponse()->getStatusCode());
    }

    public function testOnlyGroupAdminMayDeleteTheDraw(): void
    {
        $this->draw();
        $ben = $this->as('ben');

        $ben->request('DELETE', $this->path());

        self::assertSame(403, $ben->getResponse()->getStatusCode());
        self::assertCount(4, $this->storedAssignments($this->group));
    }

    public function testDeletingAMissingDrawIsRefused(): void
    {
        $anna = $this->as('anna');

        $anna->request('DELETE', $this->path());

        self::assertSame(409, $anna->getResponse()->getStatusCode());
    }
}
