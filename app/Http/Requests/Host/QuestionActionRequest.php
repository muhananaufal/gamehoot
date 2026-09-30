<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Games\GameEngines;
use App\Models\Game;
use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F13: the input of a question action (the box to open, the winner, ...) is validated by the
 * rules of the engine of the game type. Unknown actions are answered by the controller (404).
 */
final class QuestionActionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(GameEngines $engines): array
    {
        $question = $this->route('question');
        $action = $this->route('action');
        $game = $question instanceof Question ? $question->game()->first() : null;

        if (! $game instanceof Game || ! is_string($action)) {
            return [];
        }

        $engine = $engines->live($game->type);

        return in_array($action, $engine->actions(), true) ? $engine->actionRules($action) : [];
    }
}
