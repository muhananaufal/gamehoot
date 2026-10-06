<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Enums\GameType;
use App\Games\GameEngines;
use App\Http\Concerns\LiveValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePackRequest extends FormRequest
{
    use LiveValidation;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(GameEngines $engines): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            // F13: only types with an engine can hold questions.
            'game_type' => ['required', Rule::enum(GameType::class)->only($engines->supportedTypes())],
        ];
    }

    public function gameType(): GameType
    {
        return GameType::from($this->string('game_type')->toString());
    }
}
