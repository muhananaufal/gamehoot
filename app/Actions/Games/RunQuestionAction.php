<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Exceptions\ActionRefused;
use App\Games\LiveGameEngine;
use App\Models\Event;
use App\Models\Question;
use App\Models\User;
use App\Realtime\StatePublisher;
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
     *
     * @throws ActionRefused
     */
    public function handle(LiveGameEngine $engine, string $action, Event $event, Question $question, User $actor, array $input = []): void
    {
        DB::transaction(function () use ($engine, $action, $event, $question, $actor, $input): void {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);

            $engine->perform($action, $locked, $question, $actor, $input);

            $locked->bumpStateVersion();
            $locked->save();
            $this->publisher->publish($locked);
        });
    }
}
