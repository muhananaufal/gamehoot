<?php

declare(strict_types=1);

namespace App\Games;

use App\Enums\GameType;
use App\Enums\QuestionStatus;
use App\Models\PackQuestion;
use App\Models\Question;
use Illuminate\Support\Arr;

final class PentahootEngine implements GameEngine
{
    public function type(): GameType
    {
        return GameType::Pentahoot;
    }

    public function questionRules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:500'],
            // T9
            'duration_seconds' => ['required', 'integer', 'between:5,300'],
        ];
    }

    public function detailAttributes(array $validated): array
    {
        return Arr::only($validated, ['prompt', 'duration_seconds']);
    }

    /**
     * E16: Pentahoot does not use points.
     */
    public function questionAttributes(array $validated): array
    {
        return [];
    }

    public function savePackDetail(PackQuestion $question, array $attributes): void
    {
        $question->pentahoot()->updateOrCreate([], $attributes);
    }

    public function copyDetail(PackQuestion $source, Question $copy): void
    {
        $detail = $source->pentahoot()->firstOrFail();

        $copy->pentahoot()->create([
            'prompt' => $detail->prompt,
            'duration_seconds' => $detail->duration_seconds,
        ]);
    }

    public function initialStatus(): QuestionStatus
    {
        return QuestionStatus::Ready;
    }
}
