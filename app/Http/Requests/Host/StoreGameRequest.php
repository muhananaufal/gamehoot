<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Enums\GameType;
use App\Games\GameEngines;
use App\Models\QuestionPack;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * D-9: a game is made from one of the host's own packs, of a type that can be played live.
 */
final class StoreGameRequest extends FormRequest
{
    /**
     * @return array<string, list<string|ValidationRule|Exists>>
     */
    public function rules(GameEngines $engines): array
    {
        $user = $this->user();

        return [
            'pack_id' => [
                'required',
                'uuid',
                Rule::exists('question_packs', 'id')
                    ->where('owner_id', $user instanceof User ? $user->id : null)
                    ->whereIn('game_type', array_map(fn (GameType $type): string => $type->value, $engines->playableTypes()))
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['pack_id' => __('games.pack')];
    }

    public function pack(): QuestionPack
    {
        return QuestionPack::query()->findOrFail($this->string('pack_id')->toString());
    }
}
