<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Enums\GameStatus;
use App\Exceptions\ActionRefused;
use App\Games\GameEngines;
use App\Media\QuestionImages;
use App\Models\Game;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * D-2: a copied question can be edited with the same form as the pack until it has been on
 * screen; the pack is not changed. G12: the game and question rows are locked, so an edit
 * cannot cross the host showing the question.
 */
final readonly class EditGameQuestion
{
    public function __construct(private GameEngines $engines, private QuestionImages $images) {}

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws ActionRefused
     */
    public function handle(Game $game, Question $question, array $validated, User $actor): void
    {
        DB::transaction(function () use ($game, $question, $validated, $actor): void {
            $lockedGame = Game::query()->lockForUpdate()->findOrFail($game->id);
            $locked = Question::query()->lockForUpdate()->with(['pentahoot', 'kata'])->findOrFail($question->id);
            $engine = $this->engines->live($lockedGame->type);

            if ($lockedGame->status === GameStatus::Finished || $engine->wasShown($locked)) {
                throw ActionRefused::questionLocked();
            }

            // D-9: a new image is a new file; the pack keeps the one it had.
            $validated = $this->images->store($engine, $validated, $actor);

            $locked->fill($engine->questionAttributes($validated))->save();
            $engine->saveCopyDetail($locked, $engine->detailAttributes($validated));
        });
    }
}
