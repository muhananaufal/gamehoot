<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Enums\EventStatus;
use App\Enums\GameStatus;
use App\Exceptions\ActionRefused;
use App\Models\Event;
use App\Models\Game;
use App\Realtime\StatePublisher;
use Illuminate\Support\Facades\DB;

/**
 * D-1: one active game per event; a finished game on screen gives way to the next (G7). B-3: starting the first game locks the name list
 * (claims stay possible, B-5). T7: a game without questions cannot start.
 */
final readonly class StartGame
{
    public function __construct(private StatePublisher $publisher) {}

    /**
     * @throws ActionRefused
     */
    public function handle(Event $event, Game $game): void
    {
        DB::transaction(function () use ($event, $game): void {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);
            $lockedGame = Game::query()->lockForUpdate()->findOrFail($game->id);

            if ($locked->status !== EventStatus::Open) {
                throw ActionRefused::eventNotOpen();
            }

            if ($lockedGame->status === GameStatus::Finished) {
                throw ActionRefused::gameFinished();
            }

            if ($locked->active_game_id === $lockedGame->id) {
                throw ActionRefused::stale();
            }

            // G7: a finished game only shows its results and gives way to the next one.
            $onScreen = $locked->active_game_id === null ? null : Game::query()->find($locked->active_game_id);

            if ($onScreen !== null && $onScreen->status !== GameStatus::Finished) {
                throw ActionRefused::gameRunning();
            }

            if (! $lockedGame->questions()->exists()) {
                throw ActionRefused::noQuestions();
            }

            $locked->active_game_id = $lockedGame->id;
            $locked->names_locked_at ??= now();
            $locked->bumpStateVersion();
            $locked->save();
            $this->publisher->publish($locked);
        });
    }
}
