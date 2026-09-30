<?php

declare(strict_types=1);

namespace App\Games\Pentahoot;

use Carbon\CarbonImmutable;

/**
 * E3, F4: the server clock decides. Votes still on their way are accepted for one second
 * after ends_at; phones lock their Submit button at exactly zero. The tolerance is a
 * design choice, to be tuned after the venue test (stage 7).
 */
final class VoteWindow
{
    public const int TOLERANCE_MS = 1_000;

    public static function accepts(CarbonImmutable $endsAt, CarbonImmutable $now): bool
    {
        return $now->lessThanOrEqualTo($endsAt->addMilliseconds(self::TOLERANCE_MS));
    }

    /**
     * "closed" is never stored: a live question is closed once no vote can arrive anymore.
     */
    public static function isClosed(?CarbonImmutable $endsAt, CarbonImmutable $now): bool
    {
        return $endsAt !== null && ! self::accepts($endsAt, $now);
    }
}
