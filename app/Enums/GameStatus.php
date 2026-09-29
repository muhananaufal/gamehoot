<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * G7: the active game is marked by events.active_game_id, so a game is only draft or finished.
 */
enum GameStatus: string
{
    case Draft = 'draft';
    case Finished = 'finished';
}
