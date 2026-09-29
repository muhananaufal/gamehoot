<?php

declare(strict_types=1);

namespace App\Games;

use App\Enums\GameType;
use App\Enums\QuestionStatus;
use App\Models\PackQuestion;
use App\Models\Question;
use App\Rules\KataAnswer;
use App\Rules\KataOpenIndexes;
use Illuminate\Support\Arr;

final class TebakKataEngine implements GameEngine
{
    public function type(): GameType
    {
        return GameType::TebakKata;
    }

    public function questionRules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:500'],
            // T9, E5
            'answer_text' => ['required', 'string', 'max:40', new KataAnswer],
            // Absent when no box is opened: the form sends no inputs for an empty selection.
            'initial_open_indexes' => ['nullable', 'array', new KataOpenIndexes('answer_text')],
            'initial_open_indexes.*' => ['integer', 'distinct'],
            // E16
            'points' => ['required', 'integer', 'between:0,2'],
        ];
    }

    public function detailAttributes(array $validated): array
    {
        $attributes = Arr::only($validated, ['prompt', 'answer_text']);
        $attributes['initial_open_indexes'] = $validated['initial_open_indexes'] ?? [];

        if (isset($attributes['answer_text']) && is_string($attributes['answer_text'])) {
            $attributes['answer_text'] = trim($attributes['answer_text']);
        }

        if (is_array($attributes['initial_open_indexes'])) {
            // Form input arrives as numeric strings; questionRules() already rejected anything else.
            $indexes = [];
            foreach ($attributes['initial_open_indexes'] as $index) {
                if (is_numeric($index)) {
                    $indexes[] = (int) $index;
                }
            }
            sort($indexes);
            $attributes['initial_open_indexes'] = array_values(array_unique($indexes));
        }

        return $attributes;
    }

    public function questionAttributes(array $validated): array
    {
        return Arr::only($validated, ['points']);
    }

    public function savePackDetail(PackQuestion $question, array $attributes): void
    {
        $question->kata()->updateOrCreate([], $attributes);
    }

    public function copyDetail(PackQuestion $source, Question $copy): void
    {
        $detail = $source->kata()->firstOrFail();

        $copy->kata()->forceCreate([
            'prompt' => $detail->prompt,
            'answer_text' => $detail->answer_text,
            'initial_open_indexes' => $detail->initial_open_indexes,
            'opened_indexes' => $detail->initial_open_indexes,
        ]);
    }

    public function initialStatus(): QuestionStatus
    {
        return QuestionStatus::Queued;
    }
}
