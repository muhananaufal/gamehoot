<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Enums\GameStatus;
use App\Enums\LoggedAction;
use App\Enums\QuestionStatus;
use App\Exceptions\ActionRefused;
use App\Games\GameEngines;
use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use App\Realtime\StatePublisher;
use App\Support\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * D-8, E12: the host ends a game, early if needed. Unfinished questions do not count; ending
 * early is logged (E13). The game stays on screen with its results (G7). D-4: a finished game
 * is not played again.
 */
final readonly class FinishGame
{
    public function __construct(private StatePublisher $publisher, private AuditLog $auditLog, private GameEngines $engines) {}

    /**
     * @throws ActionRefused
     */
    public function handle(Event $event, Game $game, User $actor): void
    {
        DB::transaction(function () use ($event, $game, $actor): void {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);
            $lockedGame = Game::query()->lockForUpdate()->findOrFail($game->id);

            if ($locked->active_game_id !== $lockedGame->id || $lockedGame->status === GameStatus::Finished) {
                throw ActionRefused::stale();
            }

            $early = $lockedGame->questions()
                ->whereNotIn('status', [QuestionStatus::Done, QuestionStatus::Won, QuestionStatus::Surrendered])
                ->exists();

            // G10: the engine freezes what the results page reads.
            $this->engines->live($lockedGame->type)->finish($lockedGame, CarbonImmutable::now());

            // G7, E12: the game stays on screen with its final results until the next one starts.
            $lockedGame->forceFill(['status' => GameStatus::Finished, 'current_question_id' => null, 'leaderboard_at' => null])->save();
            $locked->bumpStateVersion();
            $locked->save();
            $this->publisher->publish($locked);

            if ($early) {
                $this->auditLog->record(LoggedAction::GameFinishedEarly, $actor, $locked, ['game_id' => $lockedGame->id]);
            }
        });
    }
}
