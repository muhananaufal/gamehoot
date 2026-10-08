<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Games\AddGameQuestion;
use App\Actions\Games\DeleteGameQuestion;
use App\Actions\Games\EditGameQuestion;
use App\Http\Requests\Host\GameQuestionRequest;
use App\Http\Views\GamesPage;
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
    /**
     * D-9: the Games page with this game open.
     */
    public function index(Event $event, Game $game, GamesPage $page, #[CurrentUser] User $user): View
    {
        return view('host.games.show', $page->data($event, $user, $game));
    }

    /**
     * D-2, D-9: the Games page with the question form beside the list.
     */
    public function edit(Event $event, Game $game, Question $question, GamesPage $page, #[CurrentUser] User $user): View
    {
        return view('host.games.show', $page->data($event, $user, $game, $question));
    }

    /**
     * D-2: the Games page with an empty question form beside the list.
     */
    public function create(Event $event, Game $game, GamesPage $page, #[CurrentUser] User $user): View
    {
        return view('host.games.show', $page->data($event, $user, $game, creating: true));
    }

    public function store(GameQuestionRequest $request, Event $event, Game $game, AddGameQuestion $addQuestion, #[CurrentUser] User $actor): RedirectResponse
    {
        $addQuestion->handle($event, $game, $request->validated(), $actor);

        return redirect()->route('host.events.games.show', [$event, $game])->with('status', __('games.question_added'));
    }

    public function update(GameQuestionRequest $request, Event $event, Game $game, Question $question, EditGameQuestion $editQuestion, #[CurrentUser] User $actor): RedirectResponse
    {
        $editQuestion->handle($game, $question, $request->validated(), $actor);

        return redirect()->route('host.events.games.show', [$event, $game])->with('status', __('games.question_saved'));
    }

    public function destroy(Event $event, Game $game, Question $question, DeleteGameQuestion $delete, #[CurrentUser] User $user): RedirectResponse
    {
        $delete->handle($event, $game, $question, $user);

        return redirect()->route('host.events.games.show', [$event, $game])->with('status', __('games.question_deleted'));
    }
}
