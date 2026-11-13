<?php

declare(strict_types=1);

namespace App\Enum;

enum MemberStatus: string
{
    /** Invited by username, not confirmed yet. Counts nowhere. */
    case Invited = 'invited';
    /** Takes part in the draw. */
    case Active = 'active';
    /** Deactivated by the group admin: still listed, but not drawn. */
    case Inactive = 'inactive';
}
