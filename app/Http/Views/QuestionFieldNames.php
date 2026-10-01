<?php

declare(strict_types=1);

namespace App\Http\Views;

use App\Enums\GameType;

/**
 * Validation messages name a question field the way its form labels it ("The answer image
 * could not be uploaded"), not by the input name ("answer_image").
 */
final class QuestionFieldNames
{
    /**
     * @return array<string, string>
     */
    public static function for(GameType $type): array
    {
        $labels = match ($type) {
            GameType::Pentahoot => [
                'prompt' => 'packs.pentahoot.prompt',
                'duration_seconds' => 'packs.pentahoot.duration',
            ],
            GameType::TebakKata => [
                'prompt' => 'packs.kata.prompt',
                'answer_text' => 'packs.kata.answer',
                'initial_open_indexes' => 'packs.kata.boxes',
                'points' => 'packs.points',
            ],
            GameType::TebakGambar => [
                'title' => 'packs.gambar.title',
                'answer_text' => 'packs.gambar.answer',
                'points' => 'packs.points',
                'question_image' => 'packs.gambar.question_image',
                'question_image_small' => 'packs.gambar.question_image',
                'answer_image' => 'packs.gambar.answer_image',
                'answer_image_small' => 'packs.gambar.answer_image',
            ],
        };

        return array_map(function (string $key): string {
            $label = __($key);

            return mb_strtolower(is_string($label) ? $label : $key);
        }, $labels);
    }
}
