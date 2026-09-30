<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Games\GameEngines;
use App\Models\Event;
use App\Models\Game;
use App\Models\Person;
use Illuminate\Contracts\View\View;

/**
 * F20: the Live control page, following both event channels (F2). The questions of the game on
 * screen are drawn here once, in the order its engine lists them; the snapshot only carries
 * their statuses (F14). Games that pick winners get the name list (D-6). F13: no branching per
 * game type here.
 */
final class LiveController
{
    public function __invoke(Event $event, GameEngines $engines): View
    {
        $game = $event->activeGame()->first();
        $questions = collect();
        $people = [];

        if ($game instanceof Game) {
            $engine = $engines->live($game->type);
            $questions = $engine->liveQuestions($game);

            if ($engine->picksWinners()) {
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
