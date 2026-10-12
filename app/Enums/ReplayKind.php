<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * D-11: the kinds of past moments the Public View can replay.
 */
enum ReplayKind: string
{
    /** A Pentahoot category as revealed, or a Tebak question with its answer and winner. */
    case Result = 'result';

    /** The Tebak leaderboard as it stood after a won question (E11). */
    case Leaderboard = 'leaderboard';

    /** The final podium of a finished Tebak game (E12, E17). */
    case Final = 'final';
}
