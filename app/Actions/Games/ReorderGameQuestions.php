<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Exceptions\ActionRefused;
use App\Models\Event;
use App\Models\Game;
use App\Models\Question;
use Illuminate\Support\Facades\DB;

/**
 * D-2, D-9: the copied questions of a game that has not started take a new order.
 */
final readonly class ReorderGameQuestions
{
    use LocksUnstartedGame;

    /**
     * @param  list<string>  $order  every question id of the game, in the new order
     *
     * @throws ActionRefused
     */
    public function handle(Event $event, Game $game, array $order): void
    {
        DB::transaction(function () use ($event, $game, $order): void {
            $locked = $this->lockUnstarted($event, $game);

            foreach ($order as $index => $id) {
                Question::query()->where('game_id', $locked->id)->whereKey($id)->update(['position' => $index + 1]);
            }

            $locked->forceFill(['edited_at' => now()])->save();
        });
    }
}
