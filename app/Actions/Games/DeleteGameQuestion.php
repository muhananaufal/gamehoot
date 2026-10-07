<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Enums\LoggedAction;
use App\Exceptions\ActionRefused;
use App\Models\Event;
use App\Models\Game;
use App\Models\Question;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * D-2, D-9: a copied question leaves a game that has not started; the pack keeps its own.
 * The questions after it close the gap, so the numbers stay 1, 2, 3.
 */
final readonly class DeleteGameQuestion
{
    use LocksUnstartedGame;

    public function __construct(private AuditLog $auditLog) {}

    /**
     * @throws ActionRefused
     */
    public function handle(Event $event, Game $game, Question $question, User $actor): void
    {
        DB::transaction(function () use ($event, $game, $question, $actor): void {
            $locked = $this->lockUnstarted($event, $game);
            // E13: the question is gone for good; its text keeps the entry readable.
            $copy = $question->loadMissing(['pentahoot', 'kata', 'gambar']);
            $this->auditLog->record(LoggedAction::QuestionDeleted, $actor, $event, [
                'game_id' => $locked->id,
                'title' => (string) ($copy->pentahoot->prompt ?? $copy->kata->prompt ?? $copy->gambar->title ?? ''),
            ]);

            Question::query()->where('game_id', $locked->id)->whereKey($question->id)->delete();
            $locked->questions()->orderBy('position')->get()
                ->each(fn (Question $copy, int $index) => $copy->forceFill(['position' => $index + 1])->save());

            $locked->forceFill(['edited_at' => now()])->save();
        });
    }
}
