<?php

declare(strict_types=1);

namespace App\Realtime;

use App\Games\LiveState;
use App\Models\Event;
use App\Models\Person;
use App\Models\Question;
use App\Models\Vote;

/**
 * F1, E4, E4b: what only this phone may see: its claimed name and its vote on the question
 * on screen. Added to GET /{event}/state per request, never cached or broadcast (F2).
 * Audit PERF-3: reads the question on screen from the live state the snapshot also uses.
 */
final class PhoneState
{
    /**
     * @return array{name: string, vote: array{question_id: string, attempt: int, target: string}|null}|null
     */
    public static function for(?Person $person, Event $event, ?LiveState $live = null): ?array
    {
        if ($person === null) {
            return null;
        }

        return ['name' => $person->name, 'vote' => self::vote($person, $live ?? LiveState::of($event))];
    }

    /**
     * @return array{question_id: string, attempt: int, target: string}|null
     */
    private static function vote(Person $person, LiveState $live): ?array
    {
        $question = $live->question;
        $attempt = $question?->pentahoot?->attempt;

        if (! $question instanceof Question || $attempt === null) {
            return null;
        }

        $vote = Vote::query()
            ->where('question_id', $question->id)
            ->where('voter_person_id', $person->id)
            ->where('attempt', $attempt)
            ->with('target:id,name')
            ->first();

        if (! $vote instanceof Vote) {
            return null;
        }

        return ['question_id' => $question->id, 'attempt' => $attempt, 'target' => $vote->target->name ?? ''];
    }
}
