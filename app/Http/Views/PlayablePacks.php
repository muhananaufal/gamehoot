<?php

declare(strict_types=1);

namespace App\Http\Views;

use App\Enums\GameType;
use App\Games\GameEngines;
use App\Models\QuestionPack;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * D-9, U26: the host's packs that can be played live, grouped by game type, for the pack list
 * of Add games and of the New event games step.
 */
final readonly class PlayablePacks
{
    public function __construct(private GameEngines $engines) {}

    /**
     * @return array{packs: Collection<array-key, EloquentCollection<int, QuestionPack>>, types: list<GameType>}
     */
    public function for(User $user): array
    {
        $types = $this->engines->playableTypes();

        return [
            'packs' => QuestionPack::query()
                ->whereBelongsTo($user, 'owner')
                ->whereIn('game_type', array_map(fn (GameType $type): string => $type->value, $types))
                ->withCount('questions')
                ->orderBy('title')
                ->get()
                ->groupBy(fn (QuestionPack $pack): string => $pack->game_type->value)
                ->toBase(),
            'types' => $types,
        ];
    }
}
