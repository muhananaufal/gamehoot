<?php

declare(strict_types=1);

namespace App\Actions\Packs;

use App\Games\GameEngines;
use App\Models\PackQuestion;
use App\Models\QuestionPack;
use Illuminate\Support\Facades\DB;

/**
 * D-9: questions are written in the pack; games get copies. New questions go to the end.
 */
final readonly class SavePackQuestion
{
    public function __construct(private GameEngines $engines) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(QuestionPack $pack, ?PackQuestion $question, array $validated): PackQuestion
    {
        $engine = $this->engines->for($pack->game_type);

        return DB::transaction(function () use ($pack, $question, $validated, $engine): PackQuestion {
            if ($question === null) {
                QuestionPack::query()->lockForUpdate()->findOrFail($pack->id);
                $last = $pack->questions()->max('position');
                $question = new PackQuestion(['position' => (is_numeric($last) ? (int) $last : 0) + 1]);
                $question->pack()->associate($pack);
            }

            $question->fill($engine->questionAttributes($validated))->save();
            $engine->savePackDetail($question, $engine->detailAttributes($validated));

            return $question;
        });
    }
}
