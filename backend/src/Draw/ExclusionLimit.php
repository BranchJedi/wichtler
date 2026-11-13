<?php

declare(strict_types=1);

namespace App\Draw;

/**
 * With n participants everyone may exclude at most N = floor(n/2) - 1 others, and nobody may be
 * excluded by more than N others.
 *
 * Both limits together mean everybody may draw at least n/2 people and can be drawn by at least
 * n/2 people, which by Hall's marriage theorem guarantees that a valid assignment exists.
 * The second limit is essential: without it the draw can fail already at N = 1, when
 * everybody excludes the same person.
 */
final class ExclusionLimit
{
    public static function for(int $participants): int
    {
        return max(0, intdiv($participants, 2) - 1);
    }
}
