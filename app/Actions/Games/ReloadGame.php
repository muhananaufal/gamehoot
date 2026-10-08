<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Enums\LoggedAction;
use App\Exceptions\ActionRefused;
use App\Games\GameEngines;
use App\Games\QuestionCopier;
use App\Models\Event;
use App\Models\Game;
use App\Models\QuestionPack;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * D-9: until a game starts, the host can take the latest version of its pack.
 */
final readonly class ReloadGame
{
    public function __construct(private QuestionCopier $copier, private GameEngines $engines, private AuditLog $auditLog) {}

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

            $pack = QuestionPack::query()->find($locked->source_pack_id);

            if ($pack === null) {
                throw ActionRefused::stale();
            }

            $locked->questions()->delete();
            $locked->forceFill(['title' => $pack->title])->save();
            $this->copier->copyPack($pack, $locked);
            // E13: the edits of the copies are gone.
            $this->auditLog->record(LoggedAction::GameReloaded, $actor, $lockedEvent, ['game_id' => $locked->id]);
        });
    }
}
