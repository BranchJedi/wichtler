<?php

declare(strict_types=1);

namespace App\Tests\Draw;

use App\Draw\BipartiteMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BipartiteMatcherTest extends TestCase
{
    /** @param list<list<int>> $adjacency */
    #[DataProvider('graphs')]
    public function testMaximumMatchingSize(array $adjacency, int $rightSize, int $expected): void
    {
        self::assertSame($expected, new BipartiteMatcher()->maximumMatchingSize($adjacency, $rightSize));
    }

    /** @return iterable<string, array{list<list<int>>, int, int}> */
    public static function graphs(): iterable
    {
        yield 'empty' => [[], 0, 0];
        yield 'no edges' => [[[], []], 2, 0];
        yield 'complete 3x3' => [[[0, 1, 2], [0, 1, 2], [0, 1, 2]], 3, 3];
        yield 'needs an augmenting path' => [[[0, 1], [0]], 2, 2];
        yield 'two givers compete for one receiver' => [[[0], [0], [1, 2]], 3, 2];
        yield 'long augmenting chain' => [[[0], [0, 1], [1, 2], [2, 3]], 4, 4];
        yield 'everybody excludes the same person' => [[[1, 2], [0, 2], [0, 1], [0, 1, 2]], 4, 3];
        yield 'derangement graph of 3' => [[[1, 2], [0, 2], [0, 1]], 3, 3];
    }

    public function testAgreesWithBruteForceOnRandomGraphs(): void
    {
        mt_srand(20261224);
        $matcher = new BipartiteMatcher();

        for ($round = 0; $round < 300; ++$round) {
            $size = mt_rand(1, 6);
            $adjacency = [];
            for ($left = 0; $left < $size; ++$left) {
                $adjacency[$left] = [];
                for ($right = 0; $right < $size; ++$right) {
                    if (mt_rand(0, 99) < 45) {
                        $adjacency[$left][] = $right;
                    }
                }
            }

            self::assertSame(self::bruteForce($adjacency, 0, []), $matcher->maximumMatchingSize($adjacency, $size), json_encode($adjacency));
        }
    }

    /**
     * @param list<list<int>>  $adjacency
     * @param array<int, true> $used
     */
    private static function bruteForce(array $adjacency, int $left, array $used): int
    {
        if ($left === \count($adjacency)) {
            return 0;
        }

        $best = self::bruteForce($adjacency, $left + 1, $used);
        foreach ($adjacency[$left] as $right) {
            if (!isset($used[$right])) {
                $best = max($best, 1 + self::bruteForce($adjacency, $left + 1, $used + [$right => true]));
            }
        }

        return $best;
    }
}
