<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Games\FinishGame;
use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use App\Support\SeenVersion;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * D-8: finish the running game, early if needed. G15: refused as stale when another host
 * changed the screen since this host last saw it.
 */
final class GameFinishController
{
    public function __invoke(Request $request, Event $event, Game $game, FinishGame $finishGame, #[CurrentUser] User $user): RedirectResponse
    {
        $finishGame->handle($event, $game, $user, SeenVersion::from($request));

        return redirect()->route('host.events.live', $event)->with('status', __('games.finished'));
    }
}
