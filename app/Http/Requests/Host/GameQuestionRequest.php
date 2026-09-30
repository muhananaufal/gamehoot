<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Games\GameEngines;
use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;

/**
 * D-2: a copied question is validated with the same rules as its pack question (F13).
 */
final class GameQuestionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(GameEngines $engines): array
    {
        $game = $this->route('game');

        if (! $game instanceof Game) {
            abort(404);
        }

        return $engines->for($game->type)->questionRules(creating: false);
    }
}
