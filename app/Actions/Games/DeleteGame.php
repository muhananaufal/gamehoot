<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Exceptions\ActionRefused;
use App\Games\GameEngines;
use App\Models\Event;
use App\Models\Game;
use Illuminate\Support\Facades\DB;

/**
 * T8: only games that never ran can be deleted, so results are never lost.
 */
final readonly class DeleteGame
{
    public function __construct(private GameEngines $engines) {}

    /**
     * @throws ActionRefused
     */
    public function handle(Event $event, Game $game): void
    {
        DB::transaction(function () use ($event, $game): void {
            $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);

            if ($locked->wasPlayed($lockedEvent, $this->engines->for($locked->type)->initialStatus())) {
                throw ActionRefused::gamePlayed();
            }

            $locked->delete();
        });
    }
}
