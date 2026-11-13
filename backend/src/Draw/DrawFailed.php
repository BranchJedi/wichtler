<?php

declare(strict_types=1);

namespace App\Draw;

/** A draw could not be completed. The message is meant for the group admin. */
final class DrawFailed extends \RuntimeException
{
    public static function noValidAssignment(): self
    {
        return new self('Mit den aktuellen Ausschlüssen gibt es keine gültige Zuteilung.');
    }

    public static function timedOut(): self
    {
        return new self('Zu viele Ausschlüsse für diese Gruppengrösse: Die Auslosung hat keine Zuteilung gefunden. Bitte versuche es erneut oder reduziert die Ausschlüsse.');
    }
}
