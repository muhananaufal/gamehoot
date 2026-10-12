<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Exceptions\ActionRefused;
use App\Games\GameEngines;
use App\Media\QuestionImages;
use App\Models\Event;
use App\Models\Game;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * D-2: a question for this event only, at the end of a game that has not started. It is not in
 * the pack, so a Reload from the pack takes it away like the other edits.
 */
final readonly class AddGameQuestion
{
    use LocksUnstartedGame;

    public function __construct(private GameEngines $engines, private QuestionImages $images) {}

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws ActionRefused
     */
    public function handle(Event $event, Game $game, array $validated, User $actor): Question
    {
        return DB::transaction(function () use ($event, $game, $validated, $actor): Question {
            $locked = $this->lockUnstarted($event, $game);
            $engine = $this->engines->live($locked->type);
            $last = $locked->questions()->max('position');

            // G11: files written here are deleted again if the transaction rolls back.
            $validated = $this->images->store($engine, $validated, $actor);

            $question = new Question([
                ...$engine->questionAttributes($validated),
                'position' => (is_numeric($last) ? (int) $last : 0) + 1,
                'status' => $this->engines->for($locked->type)->initialStatus(),
            ]);
            $question->game()->associate($locked);
            $question->save();
            $engine->saveCopyDetail($question, $engine->detailAttributes($validated));

            $locked->forceFill(['edited_at' => now()])->save();

            return $question;
        });
    }
}
