<?php

declare(strict_types=1);

namespace App\Realtime;

use App\Models\Event;
use App\Models\Game;
use App\Models\Person;
use App\Models\Question;
use App\Models\QuestionPentahoot;
use App\Models\Vote;

/**
 * F1, E4, E4b: what only this phone may see: its claimed name and its vote on the question
 * on screen. Added to GET /{event}/state per request, never cached or broadcast (F2).
 */
final class PhoneState
{
    /**
     * @return array{name: string, vote: array{question_id: string, attempt: int, target: string}|null}|null
     */
    public static function for(?Person $person, Event $event): ?array
    {
        if ($person === null) {
            return null;
        }

        return ['name' => $person->name, 'vote' => self::vote($person, $event)];
    }

    /**
     * @return array{question_id: string, attempt: int, target: string}|null
     */
    private static function vote(Person $person, Event $event): ?array
    {
        $game = $event->activeGame()->first();
        $question = $game instanceof Game ? $game->currentQuestion()->first() : null;

        if (! $question instanceof Question) {
            return null;
        }

        $attempt = QuestionPentahoot::query()->whereKey($question->id)->value('attempt');
        $vote = Vote::query()
            ->where('question_id', $question->id)
            ->where('voter_person_id', $person->id)
            ->where('attempt', $attempt)
            ->with('target:id,name')
            ->first();

        if (! $vote instanceof Vote || ! is_numeric($attempt)) {
            return null;
        }

        return ['question_id' => $question->id, 'attempt' => (int) $attempt, 'target' => $vote->target->name ?? ''];
    }
}
