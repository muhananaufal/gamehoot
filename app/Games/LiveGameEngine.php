<?php

declare(strict_types=1);

namespace App\Games;

use App\Enums\Audience;
use App\Exceptions\ActionRefused;
use App\Models\Event;
use App\Models\Game;
use App\Models\Question;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * F13: the part of an engine that runs a game live. Separate from GameEngine so a game type
 * can have packs before it can be played; Pentahoot implements it in stage 3, Tebak Kata in
 * stage 4, Tebak Gambar in stage 5.
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
     * Validation rules for the input of one action, e.g. the box to open or the winner.
     *
     * @return array<string, mixed>
     */
    public function actionRules(string $action): array;

    /**
     * Runs one host action. Called inside a transaction with the event row already locked
     * (RunQuestionAction bumps the version and publishes); the engine locks the game and
     * question rows it changes (G12).
     *
     * @param  array<string, mixed>  $input  validated by actionRules()
     *
     * @throws ActionRefused when the question is not in the state the action expects (F6, C-3)
     */
    public function perform(string $action, Event $event, Question $question, User $actor, array $input): void;

    /**
     * G10: freezes what the results page reads once the game finishes (E12, D-8). Called
     * inside the transaction of FinishGame.
     */
    public function finish(Game $game, CarbonImmutable $at): void;

    /**
     * D-2: a copied question that has been on screen is locked and can no longer be edited.
     */
    public function wasShown(Question $question): bool;
}
