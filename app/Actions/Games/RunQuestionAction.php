<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Exceptions\ActionRefused;
use App\Games\LiveGameEngine;
use App\Models\Event;
use App\Models\Question;
use App\Models\User;
use App\Realtime\StatePublisher;
use App\Support\SeenVersion;
use Illuminate\Support\Facades\DB;

/**
 * F13, G12, F15: runs a question action of any game type. The event row is locked first
 * (the same order as every other action), the engine changes the game, then the version
 * goes up and the new state is published after commit (F22).
 */
final readonly class RunQuestionAction
{
    public function __construct(private StatePublisher $publisher) {}

    /**
     * @param  array<string, mixed>  $input  validated by the engine's actionRules()
     * @param  int|null  $seenVersion  G15: the version the host saw, for the engine's versioned actions
     *
     * @throws ActionRefused
     */
    public function handle(LiveGameEngine $engine, string $action, Event $event, Question $question, User $actor, array $input = [], ?int $seenVersion = null): void
    {
        DB::transaction(function () use ($engine, $action, $event, $question, $actor, $input, $seenVersion): void {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);
            SeenVersion::check($locked, $seenVersion);

            $engine->perform($action, $locked, $question, $actor, $input);

            // D-11: play, closing and reopening always end a replay.
            $locked->endReplay();
            $locked->bumpStateVersion();
            $locked->save();
            $this->publisher->publish($locked);
        });
    }
}
