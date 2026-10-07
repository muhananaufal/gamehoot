<?php

declare(strict_types=1);

namespace App\Http\Views;

use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Games\GameEngines;
use App\Models\Event;
use App\Models\Game;
use App\Models\Question;
use App\Models\QuestionPack;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * D-9: data for the Games page of an event, laid out as the Question packs page: the games on
 * the left by status, the open game in the middle, the question form beside it.
 */
final readonly class GamesPage
{
    public function __construct(private GameEngines $engines) {}

    /**
     * @return array<string, mixed>
     */
    public function data(Event $event, User $user, ?Game $open = null, ?Question $editing = null, bool $creating = false): array
    {
        /** @var Collection<int, Game> $games */
        $games = $event->games()->orderBy('position')
            ->with('sourcePack:id,content_version,deleted_at')->withCount('questions')->get();

        $groups = [
            'up_next' => $games->filter(fn (Game $game): bool => $game->notStarted($event))->values(),
            'running' => $games->filter(fn (Game $game): bool => $game->id === $event->active_game_id && $game->status !== GameStatus::Finished)->values(),
            'played' => $games->filter(fn (Game $game): bool => $game->status === GameStatus::Finished)->values(),
        ];

        // The open game: the one asked for, else the first still to play, else the one running.
        $game = $open === null ? null : $games->firstWhere('id', $open->id);
        $game ??= $groups['up_next']->first() ?? $groups['running']->first() ?? $games->first();
        $editable = $game?->notStarted($event) ?? false;

        return [
            'event' => $event,
            'groups' => $groups,
            'count' => $games->count(),
            'game' => $game,
            'editable' => $editable,
            'questions' => $game?->questions()->orderBy('position')->with(['pentahoot', 'kata', 'gambar.questionImage.variants'])->get() ?? collect(),
            // D-2: only a game that has not started has a question form.
            'editing' => $editable ? $editing?->load(['pentahoot', 'kata']) : null,
            'creating' => $editable && $creating,
            'startedAt' => GameTimes::startedFor($event),
            // Add games: the pack list of Question packs, for the types that can be played live.
            'packs' => QuestionPack::query()
                ->whereBelongsTo($user, 'owner')
                ->whereIn('game_type', array_map(fn (GameType $type): string => $type->value, $this->engines->playableTypes()))
                ->withCount('questions')
                ->orderBy('title')
                ->get()
                ->groupBy(fn (QuestionPack $pack): string => $pack->game_type->value),
            'types' => $this->engines->playableTypes(),
        ];
    }
}
