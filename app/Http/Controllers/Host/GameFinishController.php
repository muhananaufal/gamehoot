<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Games\FinishGame;
use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/**
 * D-8: finish the running game, early if needed.
 */
final class GameFinishController
{
    public function __invoke(Event $event, Game $game, FinishGame $finishGame, #[CurrentUser] User $user): RedirectResponse
    {
        $finishGame->handle($event, $game, $user);

        return redirect()->route('host.events.live', $event)->with('status', __('games.finished'));
    }
}
