<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Games\EditGameQuestion;
use App\Enums\GameStatus;
use App\Games\GameEngines;
use App\Http\Requests\Host\GameQuestionRequest;
use App\Models\Event;
use App\Models\Game;
use App\Models\Question;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * D-2: the copied questions of a game, editable until they have been on screen.
 */
final class GameQuestionController
{
    public function index(Event $event, Game $game, GameEngines $engines): View
    {
        return $this->page($event, $game, $engines, null);
    }

    public function edit(Event $event, Game $game, Question $question, GameEngines $engines): View
    {
        return $this->page($event, $game, $engines, $question);
    }

    public function update(GameQuestionRequest $request, Event $event, Game $game, Question $question, EditGameQuestion $editQuestion, #[CurrentUser] User $actor): RedirectResponse
    {
        $editQuestion->handle($game, $question, $request->validated(), $actor);

        return redirect()->route('host.events.games.questions.index', [$event, $game])->with('status', __('games.question_saved'));
    }

    private function page(Event $event, Game $game, GameEngines $engines, ?Question $editing): View
    {
        $engine = $engines->live($game->type);
        $questions = $game->questions()->orderBy('position')->with(['pentahoot', 'kata'])->get();

        return view('host.games.questions', [
            'event' => $event,
            'game' => $game,
            'questions' => $questions,
            'locked' => $questions->mapWithKeys(fn (Question $question): array => [$question->id => $game->status === GameStatus::Finished || $engine->wasShown($question)])->all(),
            'editing' => $editing?->load(['pentahoot', 'kata']),
        ]);
    }
}
