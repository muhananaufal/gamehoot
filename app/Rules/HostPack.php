<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\GameType;
use App\Games\GameEngines;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * D-9, U26: a game is made from the host's own pack, not deleted, of a type that can be
 * played live. Add games and the New event games step check the same.
 */
final class HostPack
{
    public static function of(?User $user, GameEngines $engines): Exists
    {
        return Rule::exists('question_packs', 'id')
            ->where('owner_id', $user?->id)
            ->whereIn('game_type', array_map(fn (GameType $type): string => $type->value, $engines->playableTypes()))
            ->whereNull('deleted_at');
    }
}
