<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Exceptions\ActionRefused;
use App\Models\Event;
use App\Models\Game;

/**
 * D-2, D-9: changes to a game run under the event lock (F6, the same order as Start game: event,
 * then game), and only while the game has not started.
 */
trait LocksUnstartedGame
{
    /**
     * @throws ActionRefused
     */
    private function lockUnstarted(Event $event, Game $game): Game
    {
        $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);
        $locked = Game::query()->lockForUpdate()->findOrFail($game->id);

        if (! $locked->notStarted($lockedEvent)) {
            throw ActionRefused::gameStarted();
        }

        return $locked;
    }
}
