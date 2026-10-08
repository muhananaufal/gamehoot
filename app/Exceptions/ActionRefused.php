<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * K4: a host or player action the current state does not allow. Answers with
 * {code, message} to fetch requests and with a form error to page forms.
 */
final class ActionRefused extends RuntimeException
{
    private function __construct(public readonly string $errorCode, public readonly int $status)
    {
        parent::__construct(__("errors.{$errorCode}"));
    }

    /**
     * F6, C-3: the action no longer matches the state (double click, two hosts, old attempt).
     */
    public static function stale(): self
    {
        return new self('STALE_ACTION', 409);
    }

    /**
     * K4: an action the state never allows (Skip with nothing else queued, a second Skip, an
     * action the game type does not have), as opposed to a screen that fell behind.
     */
    public static function unavailable(): self
    {
        return new self('ACTION_UNAVAILABLE', 409);
    }

    /**
     * D-1: one active game per event.
     */
    public static function gameRunning(): self
    {
        return new self('GAME_RUNNING', 409);
    }

    /**
     * D-4: a finished game is not played again.
     */
    public static function gameFinished(): self
    {
        return new self('GAME_FINISHED', 409);
    }

    /**
     * T7
     */
    public static function noQuestions(): self
    {
        return new self('NO_QUESTIONS', 409);
    }

    /**
     * T8
     */
    public static function gamePlayed(): self
    {
        return new self('GAME_PLAYED', 409);
    }

    /**
     * D-2: a copied question that has been on screen, or belongs to a finished game.
     */
    public static function questionLocked(): self
    {
        return new self('QUESTION_LOCKED', 409);
    }

    /**
     * D-2, D-9: a game that has started (running or finished) can only be looked at.
     */
    public static function gameStarted(): self
    {
        return new self('GAME_STARTED', 409);
    }

    public static function eventNotOpen(): self
    {
        return new self('EVENT_NOT_OPEN', 409);
    }

    /**
     * E3
     */
    public static function voteClosed(): self
    {
        return new self('VOTE_CLOSED', 409);
    }

    /**
     * G2
     */
    public static function alreadyVoted(): self
    {
        return new self('ALREADY_VOTED', 409);
    }

    public static function notClaimed(): self
    {
        return new self('NOT_CLAIMED', 401);
    }

    /**
     * D-11: a replay waits until no question is on screen, so play always wins.
     */
    public static function questionOnScreen(): self
    {
        return new self('QUESTION_ON_SCREEN', 409);
    }

    /**
     * D-11: the moment was never on screen (or is not of this event), so it cannot come back.
     */
    public static function momentUnavailable(): self
    {
        return new self('MOMENT_UNAVAILABLE', 409);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['code' => $this->errorCode, 'message' => $this->getMessage()], $this->status);
        }

        return back()->withErrors(['action' => $this->getMessage()]);
    }
}
