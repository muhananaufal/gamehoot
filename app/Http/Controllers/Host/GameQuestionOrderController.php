<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Games\ReorderGameQuestions;
use App\Http\Requests\Host\ReorderGameQuestionsRequest;
use App\Models\Event;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;

/**
 * D-9: the order of a game's copied questions, before the game starts.
 */
final class GameQuestionOrderController
{
    public function store(ReorderGameQuestionsRequest $request, Event $event, Game $game, ReorderGameQuestions $reorder): RedirectResponse
    {
        $reorder->handle($event, $game, $request->order());

        return redirect()->route('host.events.games.show', [$event, $game])->with('status', __('games.questions_reordered'));
    }
}
