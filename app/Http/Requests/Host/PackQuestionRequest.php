<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Games\GameEngine;
use App\Games\GameEngines;
use App\Models\QuestionPack;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F13: the form rules come from the engine of the pack's game type; no branching here.
 */
final class PackQuestionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->engine()->questionRules();
    }

    public function engine(): GameEngine
    {
        return app(GameEngines::class)->for($this->pack()->game_type);
    }

    public function pack(): QuestionPack
    {
        $pack = $this->route('pack');

        if (! $pack instanceof QuestionPack) {
            abort(404);
        }

        return $pack;
    }
}
