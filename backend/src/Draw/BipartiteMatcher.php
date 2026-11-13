<?php

declare(strict_types=1);

namespace App\Draw;

/**
 * Maximum bipartite matching with the Hopcroft-Karp algorithm.
 *
 * Used as the safety net before a draw: givers on the left, receivers on the right, an edge
 * wherever a giver may draw a receiver. A draw exists exactly when all givers can be matched.
 */
final class BipartiteMatcher
{
    private const int UNMATCHED = -1;
    private const int INFINITE = \PHP_INT_MAX;

    /**
     * @param list<list<int>> $adjacency for every left vertex the right vertices it may be matched with
     * @param int             $rightSize number of right vertices
     *
     * @return int size of a maximum matching
     */
    public function maximumMatchingSize(array $adjacency, int $rightSize): int
    {
        $leftSize = \count($adjacency);
        $matchLeft = array_fill(0, $leftSize, self::UNMATCHED);
        $matchRight = $rightSize > 0 ? array_fill(0, $rightSize, self::UNMATCHED) : [];
        $size = 0;

        while (null !== $layers = $this->buildLayers($adjacency, $matchLeft, $matchRight)) {
            for ($left = 0; $left < $leftSize; ++$left) {
                if (self::UNMATCHED === $matchLeft[$left] && $this->augment($left, $adjacency, $matchLeft, $matchRight, $layers)) {
                    ++$size;
                }
            }
        }

        return $size;
    }

    /** @param list<list<int>> $adjacency */
    public function hasPerfectMatching(array $adjacency): bool
    {
        $size = \count($adjacency);

        return $size === $this->maximumMatchingSize($adjacency, $size);
    }

    /**
     * Breadth-first search from all unmatched left vertices. Returns the layer of every left
     * vertex, or null when no augmenting path is left.
     *
     * @param list<list<int>> $adjacency
     * @param list<int>       $matchLeft
     * @param list<int>       $matchRight
     *
     * @return list<int>|null
     */
    private function buildLayers(array $adjacency, array $matchLeft, array $matchRight): ?array
    {
        $layers = [];
        $queue = [];
        foreach ($matchLeft as $left => $right) {
            $layers[$left] = self::UNMATCHED === $right ? 0 : self::INFINITE;
            if (self::UNMATCHED === $right) {
                $queue[] = $left;
            }
        }

        $reachesFreeRight = false;
        for ($head = 0; $head < \count($queue); ++$head) {
            $left = $queue[$head];
            foreach ($adjacency[$left] as $right) {
                $next = $matchRight[$right];
                if (self::UNMATCHED === $next) {
                    $reachesFreeRight = true;
                } elseif (self::INFINITE === $layers[$next]) {
                    $layers[$next] = $layers[$left] + 1;
                    $queue[] = $next;
                }
            }
        }

        return $reachesFreeRight ? $layers : null;
    }

    /**
     * Depth-first search along the layers for one augmenting path starting at $left.
     *
     * @param list<list<int>> $adjacency
     * @param list<int>       $matchLeft
     * @param list<int>       $matchRight
     * @param list<int>       $layers
     */
    private function augment(int $left, array $adjacency, array &$matchLeft, array &$matchRight, array &$layers): bool
    {
        foreach ($adjacency[$left] as $right) {
            $next = $matchRight[$right];
            if (self::UNMATCHED === $next
                || ($layers[$next] === $layers[$left] + 1 && $this->augment($next, $adjacency, $matchLeft, $matchRight, $layers))
            ) {
                $matchLeft[$left] = $right;
                $matchRight[$right] = $left;

                return true;
            }
        }

        // Dead end: do not try this vertex again in the current phase.
        $layers[$left] = self::INFINITE;

        return false;
    }
}
