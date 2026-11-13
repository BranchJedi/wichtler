<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User;
use App\Entity\WichtelGroup;
use App\Enum\InvitedVia;
use App\Enum\MemberStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class WishTest extends ApiTestCase
{
    private User $anna;
    private User $ben;
    private User $carla;
    private WichtelGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->anna = $this->createUser('anna', firstName: 'Anna');
        $this->ben = $this->createUser('ben', firstName: 'Ben');
        $this->carla = $this->createUser('carla', firstName: 'Carla');
        // minWishAmount is 3
        $this->group = $this->createGroup($this->anna, 'Familie', $this->ben, $this->carla);
    }

    private function path(string $suffix = ''): string
    {
        return '/api/groups/'.$this->group->getId().'/wishes'.$suffix;
    }

    private function addWish(string $title, ?KernelBrowser $browser = null): int
    {
        $browser ??= $this->client;
        $browser->jsonRequest('POST', $this->path(), ['title' => $title]);
        self::assertSame(201, $browser->getResponse()->getStatusCode());

        return $this->json($browser)['id'];
    }

    /** Gives a user wishes without going through the API. */
    private function giveWishes(User $user, int $count): void
    {
        $member = $this->group->getMembership($user);
        for ($i = 1; $i <= $count; ++$i) {
            $member->addWish($user->getUsername().' wish '.$i, null, null);
        }
        self::getContainer()->get(EntityManagerInterface::class)->flush();
    }

    public function testParticipantManagesTheOwnWishList(): void
    {
        $this->login('ben');

        $this->client->jsonRequest('POST', $this->path(), ['title' => ' Wollsocken ', 'description' => 'Grösse 43', 'url' => 'https://example.com/socken']);
        self::assertResponseStatusCodeSame(201);
        $wish = $this->json();
        self::assertSame(['Wollsocken', 'Grösse 43', 'https://example.com/socken'], [$wish['title'], $wish['description'], $wish['url']]);
        $this->addWish('Buch');

        $this->client->request('GET', $this->path());
        self::assertSame(['Wollsocken', 'Buch'], array_column($this->json(), 'title'), 'oldest first');

        $this->client->jsonRequest('PATCH', $this->path('/'.$wish['id']), ['title' => 'Dicke Wollsocken', 'description' => ' ', 'url' => '']);
        self::assertResponseIsSuccessful();
        self::assertSame(['Dicke Wollsocken', null, null], [$this->json()['title'], $this->json()['description'], $this->json()['url']]);

        $this->client->request('DELETE', $this->path('/'.$wish['id']));
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', $this->path());
        self::assertSame(['Buch'], array_column($this->json(), 'title'));
    }

    public function testWishIsValidated(): void
    {
        $this->login('ben');

        $this->client->jsonRequest('POST', $this->path(), ['title' => '  ', 'url' => 'javascript:alert(1)']);
        self::assertResponseStatusCodeSame(422);
        self::assertEqualsCanonicalizing(['title', 'url'], $this->violationPaths());

        $this->client->jsonRequest('POST', $this->path(), ['title' => 'Buch', 'url' => 'ftp://example.com/buch']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['url'], $this->violationPaths());
    }

    public function testWishListsAreSeparatePerParticipant(): void
    {
        $this->login('ben');
        $bensWish = $this->addWish('Bens Geheimnis');

        $carla = $this->newBrowser();
        $this->login('carla', browser: $carla);
        $carla->request('GET', $this->path());
        self::assertSame([], $this->json($carla), 'nobody sees the wishes of others');

        $carla->jsonRequest('PATCH', $this->path('/'.$bensWish), ['title' => 'Gekapert']);
        self::assertSame(404, $carla->getResponse()->getStatusCode());
        $carla->request('DELETE', $this->path('/'.$bensWish));
        self::assertSame(404, $carla->getResponse()->getStatusCode());

        $this->client->request('GET', $this->path());
        self::assertSame(['Bens Geheimnis'], array_column($this->json(), 'title'));
    }

    public function testGroupAdminCannotReadWishesOfParticipants(): void
    {
        $this->giveWishes($this->ben, 2);
        $this->login('anna');

        $this->client->request('GET', $this->path());
        self::assertSame([], $this->json(), 'only her own, empty, list');

        $this->client->request('GET', '/api/groups/'.$this->group->getId().'/members');
        self::assertStringNotContainsString('wish', (string) $this->client->getResponse()->getContent());
        $this->client->request('GET', '/api/groups/'.$this->group->getId().'/draw/status');
        self::assertStringNotContainsString('ben wish', (string) $this->client->getResponse()->getContent(), 'the checklist holds counts, not wishes');
    }

    public function testWishListsAreSeparatePerGroup(): void
    {
        $other = $this->createGroup($this->anna, 'Büro', $this->ben);
        $this->login('ben');
        $this->addWish('Für die Familie');

        $this->client->request('GET', '/api/groups/'.$other->getId().'/wishes');

        self::assertSame([], $this->json());
    }

    public function testOutsidersHaveNoWishList(): void
    {
        $this->createUser('fremd');
        $this->login('fremd');
        $this->client->request('GET', $this->path());
        self::assertResponseStatusCodeSame(403);

        $this->createUser('chef', [User::ROLE_ADMIN]);
        $admin = $this->newBrowser();
        $this->login('chef', browser: $admin);
        $admin->request('GET', $this->path());
        self::assertSame(409, $admin->getResponse()->getStatusCode(), 'the Wichtler admin may view the group but is not a participant');
        $admin->jsonRequest('POST', $this->path(), ['title' => 'Nichts']);
        self::assertSame(409, $admin->getResponse()->getStatusCode());
    }

    public function testWishListIsLimited(): void
    {
        $this->giveWishes($this->ben, 30);
        $this->login('ben');

        $this->client->jsonRequest('POST', $this->path(), ['title' => 'Nummer 31']);

        self::assertResponseStatusCodeSame(409);
    }

    public function testWishesAreDeletedWithTheMembership(): void
    {
        $this->giveWishes($this->ben, 2);
        $this->login('ben');
        $this->client->request('DELETE', '/api/groups/'.$this->group->getId().'/members/me');
        self::assertResponseStatusCodeSame(204);

        self::assertSame(0, (int) self::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchOne('SELECT COUNT(*) FROM wish'));
    }

    // --- progress and drawability ---

    public function testGroupAdminSeesWhoStillLacksWishes(): void
    {
        $this->giveWishes($this->anna, 3);
        $this->giveWishes($this->ben, 1);
        $this->login('anna');

        $this->client->request('GET', '/api/groups/'.$this->group->getId().'/draw/status');

        self::assertResponseIsSuccessful();
        $status = $this->json();
        self::assertFalse($status['drawable']);
        self::assertSame([3, 3, 3], [$status['participantCount'], $status['minParticipants'], $status['minWishAmount']]);
        self::assertEqualsCanonicalizing(['ben' => 1, 'carla' => 0], array_column($status['missingWishes'], 'wishCount', 'username'));
    }

    public function testParticipantsDoNotSeeTheProgressOfOthers(): void
    {
        $this->login('ben');

        $this->client->request('GET', '/api/groups/'.$this->group->getId().'/draw/status');

        self::assertResponseStatusCodeSame(403);
    }

    public function testGroupBecomesDrawableWhenEveryoneHasEnoughWishes(): void
    {
        $this->giveWishes($this->anna, 3);
        $this->giveWishes($this->ben, 3);
        $this->giveWishes($this->carla, 2);
        $this->login('carla');

        $this->client->request('GET', '/api/groups/'.$this->group->getId());
        self::assertFalse($this->json()['drawable']);

        $this->addWish('Der dritte Wunsch');
        $this->client->request('GET', '/api/groups/'.$this->group->getId());
        self::assertTrue($this->json()['drawable']);

        $anna = $this->newBrowser();
        $this->login('anna', browser: $anna);
        $anna->request('GET', '/api/groups/'.$this->group->getId().'/draw/status');
        self::assertTrue($this->json($anna)['drawable']);
        self::assertSame([], $this->json($anna)['missingWishes']);
    }

    public function testInactiveAndInvitedMembersDoNotBlockTheDraw(): void
    {
        $this->giveWishes($this->anna, 3);
        $this->giveWishes($this->ben, 3);
        $this->giveWishes($this->carla, 3);
        $this->group->addMember($this->createUser('dora'), MemberStatus::Inactive, InvitedVia::Link);
        $this->group->addMember($this->createUser('emil'), MemberStatus::Invited, InvitedVia::Username);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->login('anna');

        $this->client->request('GET', '/api/groups/'.$this->group->getId().'/draw/status');

        self::assertTrue($this->json()['drawable']);
        self::assertSame(3, $this->json()['participantCount']);
        self::assertSame(1, $this->json()['pendingInvitations']);
    }

    public function testTwoParticipantsAreNotEnough(): void
    {
        $this->giveWishes($this->anna, 3);
        $this->giveWishes($this->ben, 3);
        $this->login('anna');
        $this->client->request('DELETE', '/api/groups/'.$this->group->getId().'/members/'.$this->group->getMembership($this->carla)->getId());

        $this->client->request('GET', '/api/groups/'.$this->group->getId().'/draw/status');

        self::assertFalse($this->json()['drawable']);
        self::assertSame([], $this->json()['missingWishes']);
        self::assertSame(2, $this->json()['participantCount']);
    }

    // --- after the draw ---

    public function testAfterTheDrawWishesCanChangeButNotDropBelowTheMinimum(): void
    {
        $this->giveWishes($this->ben, 3);
        $this->markAsDrawn($this->group);
        $this->login('ben');
        $this->client->request('GET', $this->path());
        $first = $this->json()[0]['id'];

        $this->client->jsonRequest('PATCH', $this->path('/'.$first), ['title' => 'Umformuliert']);
        self::assertResponseIsSuccessful();

        $this->client->request('DELETE', $this->path('/'.$first));
        self::assertResponseStatusCodeSame(409, 'three wishes are required, three exist');

        $this->addWish('Ein vierter');
        $this->client->request('DELETE', $this->path('/'.$first));
        self::assertResponseStatusCodeSame(204, 'with four, one may go');
    }
}
