<?php

declare(strict_types=1);

namespace App\Enum;

enum InvitedVia: string
{
    case Owner = 'owner';
    case Username = 'username';
    case Link = 'link';
}
