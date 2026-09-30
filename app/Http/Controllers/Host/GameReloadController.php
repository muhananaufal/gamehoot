<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Games\ReloadGame;
use App\Models\Event;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;

/**
 * D-9: take the latest questions of the pack before the game starts.
 */
final class GameReloadController
{
    public function __invoke(Event $event, Game $game, ReloadGame $reloadGame): RedirectResponse
    {
        $reloadGame->handle($event, $game);

        return redirect()->route('host.events.games.index', $event)->with('status', __('games.reloaded'));
    }
}
