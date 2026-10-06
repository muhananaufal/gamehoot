<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * D-10: the phase Live control shows. Derived from the stored status, the game on screen and
 * the games of the event, never stored itself (G7: one marker per fact).
 */
enum EventPhase: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Playing = 'playing';
    case AllPlayed = 'all_played';
    case Finished = 'finished';

    /**
     * @param  GameStatus|null  $onScreen  the status of the game on screen (events.active_game_id), if any
     * @param  int  $unplayed  games that have not started yet
     */
    public static function of(EventStatus $status, ?GameStatus $onScreen, int $games, int $unplayed): self
    {
        return match (true) {
            $status === EventStatus::Draft => self::Draft,
            $status === EventStatus::Finished => self::Finished,
            $onScreen !== null && $onScreen !== GameStatus::Finished => self::Playing,
            $games > 0 && $unplayed === 0 => self::AllPlayed,
            default => self::Open,
        };
    }
}
