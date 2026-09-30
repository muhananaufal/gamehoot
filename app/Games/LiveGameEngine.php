<?php

declare(strict_types=1);

namespace App\Games;

use App\Enums\Audience;
use App\Exceptions\ActionRefused;
use App\Models\Event;
use App\Models\Game;
use App\Models\Question;
use App\Models\User;

/**
 * F13: the part of an engine that runs a game live. Separate from GameEngine so a game type
 * can have packs before it can be played; Pentahoot implements it in stage 3, the Tebak
 * games in stages 4 and 5.
 */
interface LiveGameEngine extends GameEngine
{
    /**
     * F1: the game part of the snapshot, cached per state_version (F15). Values that change
     * without a version bump (vote counters, F24) come from liveCounters().
     *
     * @return array<string, mixed>
     */
    public function snapshot(Game $game, Audience $audience): array;

    /**
     * F24: counters read fresh on every /state call, merged into the game part.
     *
     * @return array<string, mixed>
     */
    public function liveCounters(Game $game, Audience $audience): array;

    /**
     * The host actions this game type accepts on a question (POST /host/{event}/questions/{question}/{action}).
     *
     * @return list<string>
     */
    public function actions(): array;

    /**
     * Runs one host action. Called inside a transaction with the event row already locked
     * (RunQuestionAction bumps the version and publishes); the engine locks the game and
     * question rows it changes (G12).
     *
     * @throws ActionRefused when the question is not in the state the action expects (F6, C-3)
     */
    public function perform(string $action, Event $event, Question $question, User $actor): void;
}
