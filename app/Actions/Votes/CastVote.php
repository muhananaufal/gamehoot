<?php

declare(strict_types=1);

namespace App\Actions\Votes;

use App\Enums\EventStatus;
use App\Enums\GameStatus;
use App\Enums\QuestionStatus;
use App\Exceptions\ActionRefused;
use App\Games\Pentahoot\VoteWindow;
use App\Models\Event;
use App\Models\Game;
use App\Models\Person;
use App\Models\Question;
use App\Models\QuestionPentahoot;
use App\Models\Vote;
use App\Realtime\StatePublisher;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * F3: a vote is a plain POST, checked on the server clock (E3) and against the attempt the
 * phone saw (T1). Shared locks on the game and question rows keep a vote from crossing a
 * host action (Reset, Finish) without making votes wait for each other or for the event row.
 * G2: the unique index keeps one vote per person per question.
 */
final readonly class CastVote
{
    public function __construct(private StatePublisher $publisher) {}

    /**
     * @throws ActionRefused
     */
    public function handle(Event $event, Question $question, Person $voter, Person $target, int $attempt): Vote
    {
        return DB::transaction(function () use ($event, $question, $voter, $target, $attempt): Vote {
            // The same row order as the host actions (game, question, detail), read-only.
            $game = Game::query()->sharedLock()->findOrFail($question->game_id);
            $locked = Question::query()->sharedLock()->findOrFail($question->id);
            $detail = QuestionPentahoot::query()->sharedLock()->findOrFail($question->id);

            if ($event->status !== EventStatus::Open
                || $event->active_game_id !== $game->id
                || $game->status === GameStatus::Finished
                || $game->current_question_id !== $locked->id
                || $locked->status !== QuestionStatus::Live) {
                throw ActionRefused::voteClosed();
            }

            if ($detail->attempt !== $attempt) {
                throw ActionRefused::stale();
            }

            if ($detail->ends_at === null || ! VoteWindow::accepts($detail->ends_at, CarbonImmutable::now())) {
                throw ActionRefused::voteClosed();
            }

            try {
                $vote = Vote::query()->create([
                    'question_id' => $locked->id,
                    'voter_person_id' => $voter->id,
                    'target_person_id' => $target->id,
                    'attempt' => $attempt,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ActionRefused::alreadyVoted();
            }

            $this->publisher->publishAnswered($event, $locked, $attempt);

            return $vote;
        });
    }
}
