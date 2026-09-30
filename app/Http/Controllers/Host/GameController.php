<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Games\CreateGame;
use App\Actions\Games\DeleteGame;
use App\Enums\GameType;
use App\Games\GameEngines;
use App\Http\Requests\Host\StoreGameRequest;
use App\Models\Event;
use App\Models\Game;
use App\Models\QuestionPack;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * D-9, T8: the games of an event, made from the host's packs.
 */
final class GameController
{
    public function index(Event $event, GameEngines $engines, #[CurrentUser] User $user): View
    {
        return view('host.games.index', [
            'event' => $event,
            'games' => $event->games()->orderBy('position')->withCount('questions')->get(),
            'packs' => QuestionPack::query()
                ->whereBelongsTo($user, 'owner')
                ->whereIn('game_type', array_map(fn (GameType $type): string => $type->value, $engines->playableTypes()))
                ->withCount('questions')
                ->orderBy('title')
                ->get(),
        ]);
    }

    public function store(StoreGameRequest $request, Event $event, CreateGame $createGame): RedirectResponse
    {
        $createGame->handle($event, $request->pack());

        return redirect()->route('host.events.games.index', $event)->with('status', __('games.added'));
    }

    public function destroy(Event $event, Game $game, DeleteGame $deleteGame): RedirectResponse
    {
        $deleteGame->handle($event, $game);

        return redirect()->route('host.events.games.index', $event)->with('status', __('games.deleted'));
    }
}
