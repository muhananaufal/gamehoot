<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Games\StartGame;
use App\Models\Event;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;

/**
 * D-1: start a game, then run it from Live control.
 */
final class GameStartController
{
    public function __invoke(Event $event, Game $game, StartGame $startGame): RedirectResponse
    {
        $startGame->handle($event, $game);

        return redirect()->route('host.events.live', $event);
    }
}
