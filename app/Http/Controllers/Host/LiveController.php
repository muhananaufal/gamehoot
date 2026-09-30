<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Models\Event;
use App\Models\Game;
use Illuminate\Contracts\View\View;

/**
 * F20: the Live control page, following both event channels (F2). The prompts of the running
 * game are drawn here once; the snapshot only carries their statuses (F14).
 */
final class LiveController
{
    public function __invoke(Event $event): View
    {
        $game = $event->activeGame()->first();

        return view('host.events.live', [
            'event' => $event,
            'game' => $game,
            'questions' => $game instanceof Game ? $game->questions()->orderBy('position')->with('pentahoot')->get() : collect(),
        ]);
    }
}
