<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Exceptions\ActionRefused;
use App\Models\Event;
use App\Models\Game;
use App\Models\Question;
use Illuminate\Support\Facades\DB;

/**
 * D-2, D-9: a copied question leaves a game that has not started; the pack keeps its own.
 * The questions after it close the gap, so the numbers stay 1, 2, 3.
 */
final readonly class DeleteGameQuestion
{
    use LocksUnstartedGame;

    /**
     * @throws ActionRefused
     */
    public function handle(Event $event, Game $game, Question $question): void
    {
        DB::transaction(function () use ($event, $game, $question): void {
            $locked = $this->lockUnstarted($event, $game);

            Question::query()->where('game_id', $locked->id)->whereKey($question->id)->delete();
            $locked->questions()->orderBy('position')->get()
                ->each(fn (Question $copy, int $index) => $copy->forceFill(['position' => $index + 1])->save());

            $locked->forceFill(['edited_at' => now()])->save();
        });
    }
}
