<?php

declare(strict_types=1);

namespace App\Games;

use App\Models\Game;
use App\Models\PackQuestion;
use App\Models\Question;
use App\Models\QuestionPack;

/**
 * D-9, G9: a game plays copies of pack questions, so editing or deleting the pack never
 * changes the results of a past session. Callers wrap the copy of a whole pack in one transaction.
 */
final readonly class QuestionCopier
{
    public function __construct(private GameEngines $engines) {}

    /**
     * Copies every question of the pack, in pack order.
     */
    public function copyPack(QuestionPack $pack, Game $game): void
    {
        foreach ($pack->questions()->get() as $index => $source) {
            $this->copy($source, $game, $index + 1);
        }
    }

    public function copy(PackQuestion $source, Game $game, int $position): Question
    {
        $engine = $this->engines->for($game->type);

        $copy = new Question([
            'position' => $position,
            'points' => $source->points,
            'status' => $engine->initialStatus(),
        ]);
        $copy->game()->associate($game);
        $copy->sourcePackQuestion()->associate($source);
        $copy->save();

        $engine->copyDetail($source, $copy);

        return $copy;
    }
}
