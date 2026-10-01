<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Enums\LoggedAction;
use App\Exceptions\ActionRefused;
use App\Games\GameEngines;
use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * T8: only games that never ran can be deleted, so results are never lost.
 */
final readonly class DeleteGame
{
    public function __construct(private GameEngines $engines, private AuditLog $auditLog) {}

    /**
     * @throws ActionRefused
     */
    public function handle(Event $event, Game $game, User $actor): void
    {
        DB::transaction(function () use ($event, $game, $actor): void {
            $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);

            if ($locked->wasPlayed($lockedEvent, $this->engines->for($locked->type)->initialStatus())) {
                throw ActionRefused::gamePlayed();
            }

            $locked->delete();
            // E13: a deleted game is gone for good; the log keeps its title.
            $this->auditLog->record(LoggedAction::GameDeleted, $actor, $lockedEvent, ['title' => $locked->title]);
        });
    }
}
