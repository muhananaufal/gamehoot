<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Games\GameEngines;
use App\Http\Concerns\LiveValidation;
use App\Http\Views\QuestionFieldNames;
use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;

/**
 * D-2: a copied question is validated with the same rules as its pack question (F13).
 */
final class GameQuestionRequest extends FormRequest
{
    use LiveValidation;

    /**
     * @return array<string, mixed>
     */
    public function rules(GameEngines $engines): array
    {
        $game = $this->route('game');

        if (! $game instanceof Game) {
            abort(404);
        }

        return $engines->for($game->type)->questionRules(creating: $this->route('question') === null);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $game = $this->route('game');

        return $game instanceof Game ? QuestionFieldNames::for($game->type) : [];
    }
}
