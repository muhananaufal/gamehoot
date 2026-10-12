<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Enums\ReplayKind;
use App\Exceptions\ActionRefused;
use App\Games\LiveState;
use App\Games\Replay\ReplayMoments;
use App\Models\Event;
use App\Models\Game;
use App\Models\Question;
use App\Realtime\StatePublisher;
use Illuminate\Support\Facades\DB;

/**
 * D-11: brings a past moment back to the Public View, and goes back to live. The event row is
 * locked first, like every other action (G12); the change moves state_version and is published
 * after commit (F15, F22). Nothing is logged: no data changes and Back to live undoes it.
 */
final readonly class ReplayOnScreen
{
    public function __construct(private ReplayMoments $moments, private StatePublisher $publisher) {}

    /**
     * @throws ActionRefused
     */
    public function show(Event $event, ReplayKind $kind, ?Game $game, ?Question $question): void
    {
        DB::transaction(function () use ($event, $kind, $game, $question): void {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);

            if (self::questionOnScreen($locked)) {
                throw ActionRefused::questionOnScreen();
            }

            if ($game === null || $this->moments->find($locked, $kind, $game, $question) === null) {
                throw ActionRefused::momentUnavailable();
            }

            $locked->forceFill(['replay_kind' => $kind, 'replay_game_id' => $game->id, 'replay_question_id' => $question?->id]);
            $locked->bumpStateVersion();
            $locked->save();
            $this->publisher->publish($locked);
        });
    }

    public function stop(Event $event): void
    {
        DB::transaction(function () use ($event): void {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);

            if ($locked->replay_kind === null) {
                return;
            }

            $locked->endReplay();
            $locked->bumpStateVersion();
            $locked->save();
            $this->publisher->publish($locked);
        });
    }

    /**
     * A Pentahoot question open for votes or waiting for its Reveal (live), or a Tebak question
     * being guessed (shown), of the game that runs.
     */
    public static function questionOnScreen(Event $event): bool
    {
        return LiveState::of($event)->questionOnScreen();
    }
}
