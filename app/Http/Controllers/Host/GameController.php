<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Games\CreateGame;
use App\Actions\Games\DeleteGame;
use App\Enums\GameType;
use App\Games\GameEngines;
use App\Http\Requests\Host\StoreGameRequest;
use App\Http\Views\GameTimes;
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
            'games' => $event->games()->orderBy('position')->with('sourcePack:id,content_version,deleted_at')->withCount('questions')->get(),
            'startedAt' => GameTimes::startedFor($event),
            // U23: the pack list of Question packs, grouped by type, for the playable types.
            'packs' => QuestionPack::query()
                ->whereBelongsTo($user, 'owner')
                ->whereIn('game_type', array_map(fn (GameType $type): string => $type->value, $engines->playableTypes()))
                ->withCount('questions')
                ->orderBy('title')
                ->get()
                ->groupBy(fn (QuestionPack $pack): string => $pack->game_type->value),
            'types' => $engines->playableTypes(),
        ]);
    }

    public function store(StoreGameRequest $request, Event $event, CreateGame $createGame): RedirectResponse
    {
        $createGame->handle($event, $request->pack());

        return redirect()->route('host.events.games.index', $event)->with('status', __('games.added'));
    }

    public function destroy(Event $event, Game $game, DeleteGame $deleteGame, #[CurrentUser] User $user): RedirectResponse
    {
        $deleteGame->handle($event, $game, $user);

        return redirect()->route('host.events.games.index', $event)->with('status', __('games.deleted'));
    }
}
