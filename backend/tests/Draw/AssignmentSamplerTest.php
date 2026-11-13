<?php

declare(strict_types=1);

namespace App\Tests\Draw;

use App\Draw\AssignmentSampler;
use App\Draw\BipartiteMatcher;
use App\Draw\DrawFailed;
use App\Draw\ExclusionLimit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class AssignmentSamplerTest extends TestCase
{
    private function randomizer(int $seed = 20261224): Randomizer
    {
        return new Randomizer(new Mt19937($seed));
    }

    /**
     * @param list<int>       $assignment
     * @param list<list<int>> $forbidden
     */
    private static function assertValid(array $assignment, array $forbidden): void
    {
        $size = \count($assignment);
        $receivers = $assignment;
        sort($receivers);
        self::assertSame(range(0, $size - 1), $receivers, 'everybody is drawn exactly once');
        foreach ($assignment as $giver => $receiver) {
            self::assertNotSame($giver, $receiver, 'nobody draws themselves');
            self::assertNotContains($receiver, $forbidden[$giver] ?? [], 'nobody draws an excluded person');
        }
    }

    public function testNobodyDrawsThemselves(): void
    {
        $sampler = new AssignmentSampler();
        $randomizer = $this->randomizer();

        foreach ([2, 3, 4, 7, 12, 25] as $size) {
            for ($round = 0; $round < 50; ++$round) {
                self::assertValid($sampler->sample($size, [], $randomizer), []);
            }
        }
    }

    public function testExclusionsAreRespectedAtTheLimit(): void
    {
        $sampler = new AssignmentSampler();
        $randomizer = $this->randomizer();

        foreach ([4, 5, 6, 8, 11, 14] as $size) {
            // Everybody excludes their next N neighbours: both limits are exactly exhausted.
            $limit = ExclusionLimit::for($size);
            $forbidden = [];
            for ($giver = 0; $giver < $size; ++$giver) {
                $forbidden[$giver] = [];
                for ($step = 1; $step <= $limit; ++$step) {
                    $forbidden[$giver][] = ($giver + $step) % $size;
                }
            }

            for ($round = 0; $round < 30; ++$round) {
                self::assertValid($sampler->sample($size, $forbidden, $randomizer), $forbidden);
            }
        }
    }

    /** With three people there are exactly two assignments: the two directions of the circle. */
    public function testThreeParticipantsHaveTwoEquallyLikelyAssignments(): void
    {
        $counts = $this->distribution(3, [], 4000);

        self::assertEqualsCanonicalizing(['1,2,0', '2,0,1'], array_keys($counts));
        self::assertChiSquareBelow($counts, 4000, 10.83); // 1 degree of freedom, p = 0.001
    }

    /** Four people without exclusions: the 9 derangements, each equally likely. */
    public function testDistributionIsUniformOverAllDerangements(): void
    {
        $counts = $this->distribution(4, [], 18000);

        self::assertCount(9, $counts);
        self::assertChiSquareBelow($counts, 18000, 26.12); // 8 degrees of freedom, p = 0.001
    }

    /** The interesting case: exclusions make a greedy approach biased, rejection sampling stays uniform. */
    public function testDistributionIsUniformWithExclusions(): void
    {
        $forbidden = [[1], [2], [], [], [0]];
        $expected = self::enumerate(5, $forbidden);
        self::assertGreaterThan(5, \count($expected));

        $samples = 2000 * \count($expected);
        $counts = $this->distribution(5, $forbidden, $samples);

        self::assertEqualsCanonicalizing($expected, array_keys($counts), 'every valid assignment occurs, no invalid one does');
        // generous bound: p = 0.001 for the given degrees of freedom is below 2 * df + 30 for df < 60
        self::assertChiSquareBelow($counts, $samples, 2.0 * (\count($expected) - 1) + 30.0);
    }

    public function testImpossibleInputIsRejectedImmediately(): void
    {
        // Everybody excludes person 0: nobody is left to give to them.
        $forbidden = [[], [0], [0], [0]];
        $started = microtime(true);

        try {
            new AssignmentSampler()->sample(4, $forbidden, $this->randomizer());
            self::fail('expected the draw to fail');
        } catch (DrawFailed $failure) {
            self::assertStringContainsString('keine gültige Zuteilung', $failure->getMessage());
        }

        self::assertLessThan(1.0, microtime(true) - $started, 'detected by the matching check, not by a timeout');
    }

    /** @return iterable<string, array{int}> */
    public static function tooSmall(): iterable
    {
        yield 'nobody' => [0];
        yield 'alone' => [1];
    }

    #[DataProvider('tooSmall')]
    public function testTooFewParticipantsCannotBeDrawn(int $size): void
    {
        $this->expectException(DrawFailed::class);

        new AssignmentSampler()->sample($size, [], $this->randomizer());
    }

    public function testGivesUpWhenTheTimeBudgetIsSpent(): void
    {
        // A valid assignment exists, but with a zero budget and this seed the first 1024 shuffles miss it.
        $forbidden = [];
        for ($giver = 0; $giver < 16; ++$giver) {
            $forbidden[$giver] = array_map(static fn (int $step) => ($giver + $step) % 16, range(1, 7));
        }

        $this->expectException(DrawFailed::class);
        $this->expectExceptionMessage('Zu viele Ausschlüsse');

        new AssignmentSampler(new BipartiteMatcher(), 0.0)->sample(16, $forbidden, $this->randomizer());
    }

    public function testExclusionLimit(): void
    {
        self::assertSame(
            [3 => 0, 4 => 1, 5 => 1, 6 => 2, 7 => 2, 8 => 3, 10 => 4, 12 => 5],
            array_combine([3, 4, 5, 6, 7, 8, 10, 12], array_map(ExclusionLimit::for(...), [3, 4, 5, 6, 7, 8, 10, 12])),
        );
        self::assertSame(0, ExclusionLimit::for(0));
        self::assertSame(0, ExclusionLimit::for(1));
    }

    /**
     * Hall's theorem in practice: whatever people exclude within both limits, a draw exists.
     */
    public function testAnyExclusionsWithinTheLimitsLeaveAValidAssignment(): void
    {
        mt_srand(4711);
        $matcher = new BipartiteMatcher();

        for ($round = 0; $round < 400; ++$round) {
            $size = mt_rand(3, 14);
            $limit = ExclusionLimit::for($size);
            $out = array_fill(0, $size, 0);
            $in = array_fill(0, $size, 0);
            $blocked = [];

            // Add random exclusions for as long as both limits allow.
            for ($try = 0; $try < $size * $size * 3; ++$try) {
                $giver = mt_rand(0, $size - 1);
                $receiver = mt_rand(0, $size - 1);
                if ($giver === $receiver || isset($blocked[$giver][$receiver]) || $out[$giver] >= $limit || $in[$receiver] >= $limit) {
                    continue;
                }
                $blocked[$giver][$receiver] = true;
                ++$out[$giver];
                ++$in[$receiver];
            }

            $adjacency = [];
            for ($giver = 0; $giver < $size; ++$giver) {
                $adjacency[$giver] = array_values(array_filter(
                    range(0, $size - 1),
                    static fn (int $receiver) => $receiver !== $giver && !isset($blocked[$giver][$receiver]),
                ));
            }

            self::assertTrue($matcher->hasPerfectMatching($adjacency), "n = $size, exclusions: ".json_encode($blocked));
        }
    }

    /**
     * @param list<list<int>> $forbidden
     *
     * @return array<string, int>
     */
    private function distribution(int $size, array $forbidden, int $samples): array
    {
        $sampler = new AssignmentSampler();
        $randomizer = $this->randomizer();
        $counts = [];
        for ($i = 0; $i < $samples; ++$i) {
            $key = implode(',', $sampler->sample($size, $forbidden, $randomizer));
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /** @param array<string, int> $counts */
    private static function assertChiSquareBelow(array $counts, int $samples, float $critical): void
    {
        $expected = $samples / \count($counts);
        $chiSquare = 0.0;
        foreach ($counts as $count) {
            $chiSquare += ($count - $expected) ** 2 / $expected;
        }

        self::assertLessThan($critical, $chiSquare, 'distribution over assignments is not uniform: '.json_encode($counts));
    }

    /**
     * All valid assignments by brute force.
     *
     * @param list<list<int>> $forbidden
     *
     * @return list<string>
     */
    private static function enumerate(int $size, array $forbidden): array
    {
        $found = [];
        $walk = static function (array $prefix) use (&$walk, &$found, $size, $forbidden): void {
            $giver = \count($prefix);
            if ($giver === $size) {
                $found[] = implode(',', $prefix);

                return;
            }
            for ($receiver = 0; $receiver < $size; ++$receiver) {
                if ($receiver !== $giver && !\in_array($receiver, $prefix, true) && !\in_array($receiver, $forbidden[$giver] ?? [], true)) {
                    $walk([...$prefix, $receiver]);
                }
            }
        };
        $walk([]);

        return $found;
    }
}
