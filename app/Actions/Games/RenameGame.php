<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Exceptions\ActionRefused;
use App\Models\Event;
use App\Models\Game;
use Illuminate\Support\Facades\DB;

/**
 * D-9: a game that has not started takes its own title; Reload brings the pack title back.
 */
final readonly class RenameGame
{
    use LocksUnstartedGame;

    /**
     * @throws ActionRefused
     */
    public function handle(Event $event, Game $game, string $title): void
    {
        DB::transaction(function () use ($event, $game, $title): void {
            $locked = $this->lockUnstarted($event, $game);

            if ($locked->title !== $title) {
                $locked->forceFill(['title' => $title, 'edited_at' => now()])->save();
            }
        });
    }
}
