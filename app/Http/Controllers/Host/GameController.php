<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Games\CreateGame;
use App\Actions\Games\DeleteGame;
use App\Actions\Games\RenameGame;
use App\Http\Requests\Host\RenameGameRequest;
use App\Http\Requests\Host\StoreGameRequest;
use App\Http\Views\GamesPage;
use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * D-9, T8: the games of an event, made from the host's packs.
 */
final class GameController
{
    public function index(Event $event, GamesPage $page, #[CurrentUser] User $user): View
    {
        return view('host.games.show', $page->data($event, $user));
    }

    public function store(StoreGameRequest $request, Event $event, CreateGame $createGame): RedirectResponse
    {
        $createGame->handleMany($event, $request->packs());

        return redirect()->route('host.events.games.index', $event)->with('status', __('games.added'));
    }

    public function update(RenameGameRequest $request, Event $event, Game $game, RenameGame $rename): RedirectResponse
    {
        $rename->handle($event, $game, $request->string('title')->trim()->toString());

        return redirect()->route('host.events.games.show', [$event, $game])->with('status', __('games.renamed'));
    }

    public function destroy(Event $event, Game $game, DeleteGame $deleteGame, #[CurrentUser] User $user): RedirectResponse
    {
        $deleteGame->handle($event, $game, $user);

        return redirect()->route('host.events.games.index', $event)->with('status', __('games.deleted'));
    }
}
