<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User;
use App\Entity\WichtelGroup;
use App\Enum\InvitedVia;
use App\Enum\MemberStatus;
use Doctrine\ORM\EntityManagerInterface;

final class InvitationTest extends ApiTestCase
{
    private User $anna;
    private User $carla;
    private WichtelGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->anna = $this->createUser('anna', firstName: 'Anna');
        $this->carla = $this->createUser('carla', firstName: 'Carla');
        $this->group = $this->createGroup($this->anna, 'Familie', $this->createUser('ben'));
    }

    private function inviteByUsername(User $user): int
    {
        $member = $this->group->addMember($user, MemberStatus::Invited, InvitedVia::Username);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        return (int) $member->getId();
    }

    private function link(string $suffix = ''): string
    {
        return '/api/invite-links/'.$this->group->getInviteToken().$suffix;
    }

    // --- invitation by username ---

    public function testInvitedUserSeesTheInvitationWithGroupDetails(): void
    {
        $memberId = $this->inviteByUsername($this->carla);
        $this->login('carla');

        $this->client->request('GET', '/api/invitations');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->json());
        $invitation = $this->json()[0];
        self::assertSame($memberId, $invitation['memberId']);
        self::assertSame('Familie', $invitation['title']);
        self::assertSame('2026-12-24', $invitation['eventDate']);
        self::assertSame('anna', $invitation['owner']['username']);
        self::assertSame(2, $invitation['participantCount']);
        self::assertFalse($invitation['alreadyJoined']);

        $this->client->request('GET', '/api/groups');
        self::assertSame([], $this->json(), 'not a group of hers until she accepts');
    }

    public function testAcceptingAnInvitationJoinsTheGroup(): void
    {
        $memberId = $this->inviteByUsername($this->carla);
        $this->login('carla');

        $this->client->request('POST', '/api/invitations/'.$memberId.'/accept');

        self::assertResponseIsSuccessful();
        self::assertSame(3, $this->json()['participantCount']);
        self::assertTrue($this->json()['isMember']);
        $this->client->request('GET', '/api/invitations');
        self::assertSame([], $this->json());
        $this->client->request('GET', '/api/groups/'.$this->group->getId());
        self::assertResponseIsSuccessful();
    }

    public function testDecliningAnInvitationRemovesIt(): void
    {
        $memberId = $this->inviteByUsername($this->carla);
        $this->login('carla');

        $this->client->request('POST', '/api/invitations/'.$memberId.'/decline');
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/invitations');
        self::assertSame([], $this->json());
        $this->client->request('POST', '/api/invitations/'.$memberId.'/accept');
        self::assertResponseStatusCodeSame(404);
    }

    public function testInvitationsOfOthersCannotBeAnswered(): void
    {
        $memberId = $this->inviteByUsername($this->carla);
        $this->createUser('dieb');
        $this->login('dieb');

        $this->client->request('POST', '/api/invitations/'.$memberId.'/accept');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/api/invitations/'.$memberId.'/decline');
        self::assertResponseStatusCodeSame(404);
    }

    public function testInvitationExpiresWithTheDraw(): void
    {
        $memberId = $this->inviteByUsername($this->carla);
        $this->markAsDrawn($this->group);
        $this->login('carla');

        $this->client->request('POST', '/api/invitations/'.$memberId.'/accept');

        self::assertResponseStatusCodeSame(404);
    }

    // --- share link ---

    public function testShareLinkIsOnlyRevealedToTheGroupAdmin(): void
    {
        $this->login('anna');

        $this->client->request('GET', '/api/groups/'.$this->group->getId().'/invite-link');

        self::assertResponseIsSuccessful();
        self::assertSame($this->group->getInviteToken(), $this->json()['token']);
        self::assertSame(43, \strlen($this->json()['token']));
    }

    public function testShareLinkPreviewRequiresASession(): void
    {
        $this->client->request('GET', $this->link());

        self::assertResponseStatusCodeSame(401);
        self::assertStringNotContainsString('Familie', (string) $this->client->getResponse()->getContent());
    }

    public function testSignedInUserPreviewsAndJoinsThroughTheShareLink(): void
    {
        $this->login('carla');

        $this->client->request('GET', $this->link());
        self::assertResponseIsSuccessful();
        self::assertSame('Familie', $this->json()['title']);
        self::assertFalse($this->json()['alreadyJoined']);
        self::assertNull($this->json()['memberId']);
        $this->client->request('GET', '/api/groups');
        self::assertSame([], $this->json(), 'looking at the link does not join');

        $this->client->request('POST', $this->link('/accept'));
        self::assertResponseIsSuccessful();
        self::assertSame($this->group->getId(), $this->json()['id']);
        self::assertSame(3, $this->json()['participantCount']);
    }

    public function testFreshlyRegisteredUserJoinsThroughTheShareLink(): void
    {
        $this->client->jsonRequest('POST', '/api/auth/register', [
            'firstName' => 'Dora', 'lastName' => 'Neu', 'username' => 'dora', 'password' => self::PASSWORD,
        ]);
        self::assertResponseStatusCodeSame(201);

        $this->client->request('POST', $this->link('/accept'));

        self::assertResponseIsSuccessful();
        self::assertTrue($this->json()['isMember']);
    }

    public function testJoiningTwiceThroughTheShareLinkIsHarmless(): void
    {
        $this->login('carla');
        $this->client->request('POST', $this->link('/accept'));
        $this->client->request('POST', $this->link('/accept'));

        self::assertResponseIsSuccessful();
        self::assertSame(3, $this->json()['participantCount']);

        $this->client->request('GET', $this->link());
        self::assertTrue($this->json()['alreadyJoined']);
    }

    public function testShareLinkAlsoConfirmsAPendingInvitationByUsername(): void
    {
        $this->inviteByUsername($this->carla);
        $this->login('carla');

        $this->client->request('GET', $this->link());
        self::assertNotNull($this->json()['memberId']);

        $this->client->request('POST', $this->link('/accept'));
        self::assertSame(3, $this->json()['participantCount']);
        $this->client->request('GET', '/api/invitations');
        self::assertSame([], $this->json());
    }

    public function testInactiveMemberStaysInactiveWhenUsingTheLinkAgain(): void
    {
        $member = $this->group->addMember($this->carla, MemberStatus::Inactive, InvitedVia::Link);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->login('carla');

        $this->client->request('POST', $this->link('/accept'));

        self::assertResponseIsSuccessful();
        self::assertSame(2, $this->json()['participantCount'], 'the link must not undo the group admin\'s decision');
        self::assertNotNull($member->getId());
    }

    public function testRegeneratingInvalidatesTheOldLink(): void
    {
        $oldLink = $this->link();
        $this->login('anna');
        $this->client->request('POST', '/api/groups/'.$this->group->getId().'/invite-link');
        self::assertResponseIsSuccessful();
        $newToken = $this->json()['token'];
        self::assertNotSame($this->group->getInviteToken(), $newToken);

        $carla = $this->newBrowser();
        $this->login('carla', browser: $carla);
        $carla->request('GET', $oldLink);
        self::assertSame(404, $carla->getResponse()->getStatusCode());
        $carla->request('POST', $oldLink.'/accept');
        self::assertSame(404, $carla->getResponse()->getStatusCode());
        $carla->request('GET', '/api/invite-links/'.$newToken);
        self::assertSame(200, $carla->getResponse()->getStatusCode());
    }

    public function testShareLinkExpiresWithTheDraw(): void
    {
        $this->markAsDrawn($this->group);
        $this->login('carla');

        $this->client->request('GET', $this->link());
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', $this->link('/accept'));
        self::assertResponseStatusCodeSame(404);
    }

    public function testUnknownShareLinkIsNotFound(): void
    {
        $this->login('carla');

        $this->client->request('GET', '/api/invite-links/gibt-es-nicht');

        self::assertResponseStatusCodeSame(404);
    }
}
