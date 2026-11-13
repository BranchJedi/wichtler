<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User;
use App\Entity\WichtelGroup;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class ExclusionTest extends ApiTestCase
{
    /** @var array<string, User> */
    private array $users = [];
    private WichtelGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['anna', 'ben', 'carla', 'dora', 'emil', 'frida'] as $name) {
            $this->users[$name] = $this->createUser($name, firstName: ucfirst($name));
        }
    }

    /** A group owned by anna with the given further participants. */
    private function group(string ...$participants): void
    {
        $this->group = $this->createGroup($this->users['anna'], 'Familie', ...array_map(fn (string $name) => $this->users[$name], $participants));
    }

    private function path(string $suffix = ''): string
    {
        return '/api/groups/'.$this->group->getId().'/exclusions'.$suffix;
    }

    private function id(string $name): int
    {
        return $this->memberIdOf($this->group, $this->users[$name]);
    }

    private function as(string $name): KernelBrowser
    {
        $browser = $this->newBrowser();
        $this->login($name, browser: $browser);

        return $browser;
    }

    /** @return list<string> usernames the given user currently excludes */
    private function excludedBy(string $name): array
    {
        $browser = $this->as($name);
        $browser->request('GET', $this->path());
        self::assertSame(200, $browser->getResponse()->getStatusCode());

        return array_values(array_column(array_filter($this->json($browser)['candidates'], static fn (array $c) => $c['excluded']), 'username'));
    }

    private function exclude(string $who, string $whom): int
    {
        $browser = $this->as($who);
        $browser->request('PUT', $this->path('/'.$this->id($whom)));

        return $browser->getResponse()->getStatusCode();
    }

    private function removeFromGroup(string $name): void
    {
        $anna = $this->as('anna');
        $anna->request('DELETE', '/api/groups/'.$this->group->getId().'/members/'.$this->id($name));
        self::assertSame(204, $anna->getResponse()->getStatusCode());
    }

    public function testStateListsTheOtherParticipantsAndTheLimit(): void
    {
        $this->group('ben', 'carla', 'dora');
        $ben = $this->as('ben');

        $ben->request('GET', $this->path());

        $state = $this->json($ben);
        self::assertSame([1, 1, 4], [$state['limit'], $state['remaining'], $state['participantCount']]);
        self::assertTrue($state['editable']);
        self::assertFalse($state['prunedNotice']);
        self::assertSame(['anna', 'carla', 'dora'], array_column($state['candidates'], 'username'), 'everybody but oneself');
        self::assertSame([false, false, false], array_column($state['candidates'], 'excluded'));
    }

    public function testNoExclusionsWithThreeParticipants(): void
    {
        $this->group('ben', 'carla');

        self::assertSame(422, $this->exclude('ben', 'carla'));
        self::assertSame([], $this->excludedBy('ben'));
    }

    public function testRuleAOwnLimit(): void
    {
        $this->group('ben', 'carla', 'dora');

        self::assertSame(200, $this->exclude('ben', 'carla'));
        self::assertSame(200, $this->exclude('ben', 'carla'), 'repeating is harmless');
        self::assertSame(422, $this->exclude('ben', 'dora'), 'limit of 1 reached');
        self::assertSame(['carla'], $this->excludedBy('ben'));
    }

    /** Without this rule the draw can fail already at N = 1: everybody excludes the same person. */
    public function testRuleBNobodyIsExcludedTooOften(): void
    {
        $this->group('ben', 'carla', 'dora');
        self::assertSame(200, $this->exclude('ben', 'carla'));

        $dora = $this->as('dora');
        $dora->request('PUT', $this->path('/'.$this->id('carla')));

        self::assertSame(422, $dora->getResponse()->getStatusCode());
        self::assertSame('Diese Person kann nicht weiter ausgeschlossen werden.', $this->json($dora)['detail'], 'no hint at who or how many');
        self::assertSame(200, $this->exclude('dora', 'ben'), 'another person is still possible');
    }

    public function testLimitGrowsWithTheGroup(): void
    {
        $this->group('ben', 'carla', 'dora', 'emil', 'frida');

        self::assertSame(200, $this->exclude('ben', 'carla'));
        self::assertSame(200, $this->exclude('ben', 'dora'));
        self::assertSame(422, $this->exclude('ben', 'emil'), 'N = 2 with six participants');
    }

    public function testExclusionCanBeTakenBack(): void
    {
        $this->group('ben', 'carla', 'dora');
        $this->exclude('ben', 'carla');

        $ben = $this->as('ben');
        $ben->request('DELETE', $this->path('/'.$this->id('carla')));

        self::assertSame(200, $ben->getResponse()->getStatusCode());
        self::assertSame(1, $this->json($ben)['remaining']);
        self::assertSame(200, $this->exclude('ben', 'dora'));
    }

    public function testOnlyActiveParticipantsOfTheSameGroupCanBeExcluded(): void
    {
        $this->group('ben', 'carla', 'dora', 'emil');
        $other = $this->createGroup($this->users['anna'], 'Büro', $this->users['frida']);
        $anna = $this->as('anna');
        $anna->jsonRequest('PATCH', '/api/groups/'.$this->group->getId().'/members/'.$this->id('emil'), ['status' => 'inactive']);
        $ben = $this->as('ben');

        $ben->request('PUT', $this->path('/'.$this->id('ben')));
        self::assertSame(404, $ben->getResponse()->getStatusCode(), 'oneself');
        $ben->request('PUT', $this->path('/'.$this->id('emil')));
        self::assertSame(404, $ben->getResponse()->getStatusCode(), 'inactive');
        $ben->request('PUT', $this->path('/'.$this->memberIdOf($other, $this->users['frida'])));
        self::assertSame(404, $ben->getResponse()->getStatusCode(), 'member of another group');
    }

    public function testExclusionsAreSecret(): void
    {
        $this->group('ben', 'carla', 'dora');
        $this->exclude('ben', 'carla');

        self::assertSame([], $this->excludedBy('anna'), 'the group admin sees only her own');
        self::assertSame([], $this->excludedBy('carla'), 'the excluded person does not learn of it');

        $anna = $this->as('anna');
        $anna->request('GET', '/api/groups/'.$this->group->getId().'/draw/status');
        self::assertTrue($this->json($anna)['hasExclusions'], 'the group admin only learns that there are some');
        foreach (['/draw/status', '/members', ''] as $suffix) {
            $anna->request('GET', '/api/groups/'.$this->group->getId().$suffix);
            self::assertStringNotContainsString('excluded', (string) $anna->getResponse()->getContent());
        }
    }

    public function testOnlyParticipantsHaveExclusions(): void
    {
        $this->group('ben', 'carla', 'dora');
        $this->createUser('fremd');
        $this->createUser('chef', [User::ROLE_ADMIN]);
        $anna = $this->as('anna');
        $anna->jsonRequest('PATCH', '/api/groups/'.$this->group->getId().'/members/'.$this->id('dora'), ['status' => 'inactive']);

        foreach (['fremd' => 403, 'chef' => 409, 'dora' => 409] as $name => $expected) {
            $browser = $this->as($name);
            $browser->request('GET', $this->path());
            self::assertSame($expected, $browser->getResponse()->getStatusCode(), $name);
        }
    }

    // --- the group shrinks ---

    public function testLimitDropsAlreadyWhenSixBecomeFive(): void
    {
        $this->group('ben', 'carla', 'dora', 'emil', 'frida');
        $this->exclude('ben', 'carla');
        $this->exclude('ben', 'dora');

        $this->removeFromGroup('emil');

        self::assertSame(['carla'], $this->excludedBy('ben'), 'five participants allow one exclusion, the newer one is dropped');
    }

    public function testExclusionsWithinTheNewLimitSurviveAShrink(): void
    {
        $this->group('ben', 'carla', 'dora', 'emil', 'frida');
        $this->exclude('ben', 'carla');
        $this->exclude('dora', 'anna');

        $this->removeFromGroup('emil');
        $this->removeFromGroup('frida');

        self::assertSame(['carla'], $this->excludedBy('ben'));
        self::assertSame(['anna'], $this->excludedBy('dora'));
        $ben = $this->as('ben');
        $ben->request('GET', $this->path());
        self::assertFalse($this->json($ben)['prunedNotice']);
    }

    public function testShrinkingBelowTheLimitRemovesTheNewestExclusionAndTellsTheOwner(): void
    {
        $this->group('ben', 'carla', 'dora', 'emil', 'frida');
        $this->exclude('ben', 'carla');
        $this->exclude('ben', 'dora');
        $this->removeFromGroup('emil');
        $this->removeFromGroup('frida');

        $ben = $this->as('ben');
        $ben->request('GET', $this->path());
        $state = $this->json($ben);
        self::assertSame(1, $state['limit']);
        self::assertTrue($state['prunedNotice']);
        self::assertSame(['carla'], $this->excludedBy('ben'), 'the older exclusion is kept');

        $ben->request('DELETE', $this->path('/notice'));
        self::assertFalse($this->json($ben)['prunedNotice']);
        $ben->request('GET', $this->path());
        self::assertFalse($this->json($ben)['prunedNotice'], 'acknowledged for good');
    }

    public function testRuleBIsRestoredToo(): void
    {
        $this->group('ben', 'carla', 'dora', 'emil', 'frida');
        $this->exclude('ben', 'carla');
        $this->exclude('dora', 'carla');

        $this->removeFromGroup('emil');
        $this->removeFromGroup('frida');

        self::assertSame(['carla'], $this->excludedBy('ben'));
        self::assertSame([], $this->excludedBy('dora'), 'the newer of the two exclusions of carla is gone');
        $dora = $this->as('dora');
        $dora->request('GET', $this->path());
        self::assertTrue($this->json($dora)['prunedNotice']);
        $ben = $this->as('ben');
        $ben->request('GET', $this->path());
        self::assertFalse($this->json($ben)['prunedNotice']);
    }

    public function testExclusionOfSomeoneWhoLeavesDisappearsSilently(): void
    {
        $this->group('ben', 'carla', 'dora', 'emil');
        $this->exclude('ben', 'carla');
        $carla = $this->as('carla');
        $carla->request('DELETE', '/api/groups/'.$this->group->getId().'/members/me');
        self::assertSame(204, $carla->getResponse()->getStatusCode());

        $ben = $this->as('ben');
        $ben->request('GET', $this->path());
        self::assertSame(1, $this->json($ben)['remaining']);
        self::assertFalse($this->json($ben)['prunedNotice'], 'nothing to explain: the person is simply gone');
    }

    public function testDeactivatedParticipantLosesTheExclusions(): void
    {
        $this->group('ben', 'carla', 'dora', 'emil');
        $this->exclude('ben', 'carla');
        $this->exclude('dora', 'ben');
        $anna = $this->as('anna');
        $anna->jsonRequest('PATCH', '/api/groups/'.$this->group->getId().'/members/'.$this->id('ben'), ['status' => 'inactive']);
        $anna->jsonRequest('PATCH', '/api/groups/'.$this->group->getId().'/members/'.$this->id('ben'), ['status' => 'active']);

        self::assertSame([], $this->excludedBy('ben'));
        self::assertSame([], $this->excludedBy('dora'), 'exclusions of a deactivated person are dropped as well');
        $ben = $this->as('ben');
        $ben->request('GET', $this->path());
        self::assertTrue($this->json($ben)['prunedNotice']);
    }

    public function testDrawLocksExclusions(): void
    {
        $this->group('ben', 'carla', 'dora');
        $this->exclude('ben', 'carla');
        $this->markAsDrawn($this->group);
        $ben = $this->as('ben');

        $ben->request('GET', $this->path());
        self::assertFalse($this->json($ben)['editable']);
        $ben->request('DELETE', $this->path('/'.$this->id('carla')));
        self::assertSame(409, $ben->getResponse()->getStatusCode());
        self::assertSame(409, $this->exclude('dora', 'ben'));
    }
}
