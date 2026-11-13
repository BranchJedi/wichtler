<?php

declare(strict_types=1);

namespace App\Draw;

use Random\Randomizer;

/**
 * Picks one assignment uniformly at random from all valid ones.
 *
 * Rejection sampling: shuffle everyone, throw the result away as soon as somebody would draw
 * themselves or an excluded person, repeat. Every permutation is equally likely to come out of
 * the shuffle, so every valid one is equally likely to be the first that survives.
 *
 * There is deliberately no greedy shortcut and no fallback to one. Assigning people one by one
 * favours some outcomes over others, and participants could draw conclusions from that bias.
 */
final readonly class AssignmentSampler
{
    public function __construct(
        private BipartiteMatcher $matcher = new BipartiteMatcher(),
        private float $timeBudgetSeconds = 5.0,
    ) {
    }

    /**
     * @param int               $size      number of participants, indexed 0..$size-1
     * @param list<list<int>>   $forbidden for every giver the receivers they must not draw (self is always forbidden)
     *
     * @return list<int> receiver index for every giver index
     *
     * @throws DrawFailed when no valid assignment exists, or none was found within the time budget
     */
    public function sample(int $size, array $forbidden, Randomizer $randomizer): array
    {
        $allowed = [];
        $adjacency = [];
        for ($giver = 0; $giver < $size; ++$giver) {
            $blocked = array_fill_keys($forbidden[$giver] ?? [], true);
            $blocked[$giver] = true;
            $allowed[$giver] = [];
            $adjacency[$giver] = [];
            for ($receiver = 0; $receiver < $size; ++$receiver) {
                if (!isset($blocked[$receiver])) {
                    $allowed[$giver][$receiver] = true;
                    $adjacency[$giver][] = $receiver;
                }
            }
        }

        // Without this check an impossible input would only show up as a timeout.
        if ($size < 2 || !$this->matcher->hasPerfectMatching($adjacency)) {
            throw DrawFailed::noValidAssignment();
        }

        $identity = range(0, $size - 1);
        $deadline = microtime(true) + $this->timeBudgetSeconds;
        $attempt = 0;

        while (true) {
            $candidate = $randomizer->shuffleArray($identity);

            $valid = true;
            foreach ($candidate as $giver => $receiver) {
                if (!isset($allowed[$giver][$receiver])) {
                    $valid = false;
                    break;
                }
            }
            if ($valid) {
                return $candidate;
            }

            // Reading the clock on every attempt would dominate the loop.
            if (0 === ++$attempt % 1024 && microtime(true) > $deadline) {
                throw DrawFailed::timedOut();
            }
        }
    }
}
