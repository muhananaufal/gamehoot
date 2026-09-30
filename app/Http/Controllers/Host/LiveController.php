<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Enums\GameType;
use App\Models\Event;
use App\Models\Game;
use App\Models\Person;
use Illuminate\Contracts\View\View;

/**
 * F20: the Live control page, following both event channels (F2). The prompts of the game on
 * screen are drawn here once; the snapshot only carries their statuses (F14). Tebak Kata lists
 * its questions in copy order (UUIDv7), which a Skip does not change, and gets the name list
 * for picking a winner.
 */
final class LiveController
{
    public function __invoke(Event $event): View
    {
        $game = $event->activeGame()->first();
        $questions = collect();
        $people = [];

        if ($game instanceof Game) {
            $questions = $game->questions()
                ->orderBy($game->type === GameType::TebakKata ? 'id' : 'position')
                ->with(['pentahoot', 'kata'])
                ->get();

            if ($game->type === GameType::TebakKata) {
                $people = $event->people()->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Person $person): array => ['id' => $person->id, 'name' => $person->name])
                    ->all();
            }
        }

        return view('host.events.live', [
            'event' => $event,
            'game' => $game,
            'questions' => $questions,
            'people' => $people,
        ]);
    }
}
