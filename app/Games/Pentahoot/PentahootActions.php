<?php

declare(strict_types=1);

namespace App\Games\Pentahoot;

use App\Enums\LoggedAction;
use App\Enums\QuestionStatus;
use App\Exceptions\ActionRefused;
use App\Models\Event;
use App\Models\Game;
use App\Models\Question;
use App\Models\QuestionPentahoot;
use App\Models\QuestionResult;
use App\Models\User;
use App\Models\Vote;
use App\Support\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The host actions of a Pentahoot question: ready -> live -> (closed) -> revealed -> done,
 * with Reset back to ready (spec section 05). G12: every action locks the event, game and
 * question rows and checks the state it expects (F6); anything else is STALE_ACTION (C-3).
 * The caller (RunQuestionAction) holds the transaction and the event lock and publishes.
 */
final readonly class PentahootActions
{
    public const array ACTIONS = ['start', 'stop', 'reveal', 'next', 'reset'];

    public function __construct(private AuditLog $auditLog) {}

    /**
     * @throws ActionRefused
     */
    public function perform(string $action, Event $event, Question $question, User $actor): void
    {
        // Lock order after the event (locked by the caller): game, then question.
        $game = Game::query()->lockForUpdate()->findOrFail($question->game_id);
        $locked = Question::query()->lockForUpdate()->findOrFail($question->id);
        $detail = QuestionPentahoot::query()->lockForUpdate()->findOrFail($question->id);

        if ($event->active_game_id !== $game->id) {
            throw ActionRefused::stale();
        }

        $now = CarbonImmutable::now();

        match ($action) {
            'start' => $this->start($game, $locked, $detail, $now),
            'stop' => $this->stop($game, $locked, $detail, $now),
            'reveal' => $this->reveal($game, $locked, $detail, $now),
            'next' => $this->next($game, $locked),
            'reset' => $this->reset($event, $game, $locked, $detail, $actor),
            default => throw ActionRefused::stale(),
        };
    }

    /**
     * D-5: any question that is not done can be opened, while no other question is live or
     * waiting to be revealed (G7: one question on screen).
     */
    private function start(Game $game, Question $question, QuestionPentahoot $detail, CarbonImmutable $now): void
    {
        if ($question->status !== QuestionStatus::Ready) {
            throw ActionRefused::stale();
        }

        if ($game->current_question_id !== null && $game->current_question_id !== $question->id) {
            $current = Question::query()->findOrFail($game->current_question_id);

            if (in_array($current->status, [QuestionStatus::Live, QuestionStatus::Revealed], true)) {
                throw ActionRefused::stale();
            }
        }

        $question->forceFill(['status' => QuestionStatus::Live])->save();
        $detail->forceFill(['ends_at' => $now->addSeconds($detail->duration_seconds)])->save();
        $game->forceFill(['current_question_id' => $question->id])->save();
    }

    /**
     * F4: Stop ends the countdown now. Only while it is still running, so it never extends it.
     */
    private function stop(Game $game, Question $question, QuestionPentahoot $detail, CarbonImmutable $now): void
    {
        $this->expectCurrent($game, $question, QuestionStatus::Live);

        if ($detail->ends_at === null || ! $now->lessThan($detail->ends_at)) {
            throw ActionRefused::stale();
        }

        $detail->forceFill(['ends_at' => $now])->save();
    }

    /**
     * E1, E3, G10: reveal once no vote can arrive anymore, and freeze the top 5 of this attempt.
     */
    private function reveal(Game $game, Question $question, QuestionPentahoot $detail, CarbonImmutable $now): void
    {
        $this->expectCurrent($game, $question, QuestionStatus::Live);

        if (! VoteWindow::isClosed($detail->ends_at, $now)) {
            throw ActionRefused::stale();
        }

        foreach (CompetitionRanking::top($this->tally($question, $detail->attempt)) as $row) {
            QuestionResult::query()->create([
                'question_id' => $question->id,
                'attempt' => $detail->attempt,
                'person_id' => $row['person_id'],
                'rank' => $row['rank'],
                'votes' => $row['votes'],
                'frozen_at' => $now,
            ]);
        }

        $question->forceFill(['status' => QuestionStatus::Revealed])->save();
    }

    private function next(Game $game, Question $question): void
    {
        $this->expectCurrent($game, $question, QuestionStatus::Revealed);

        $question->forceFill(['status' => QuestionStatus::Done])->save();
        $game->forceFill(['current_question_id' => null])->save();
    }

    /**
     * T1: votes of the old attempt that are still on their way carry the old number and are
     * refused. G10: the frozen results of the attempt go too. E13: logged.
     */
    private function reset(Event $event, Game $game, Question $question, QuestionPentahoot $detail, User $actor): void
    {
        if ($game->current_question_id !== $question->id
            || ! in_array($question->status, [QuestionStatus::Live, QuestionStatus::Revealed], true)) {
            throw ActionRefused::stale();
        }

        Vote::query()->where('question_id', $question->id)->delete();
        QuestionResult::query()->where('question_id', $question->id)->where('attempt', $detail->attempt)->delete();

        $this->auditLog->record(LoggedAction::QuestionReset, $actor, $event, ['question_id' => $question->id, 'attempt' => $detail->attempt]);

        $detail->forceFill(['attempt' => $detail->attempt + 1, 'ends_at' => null])->save();
        $question->forceFill(['status' => QuestionStatus::Ready])->save();
    }

    private function expectCurrent(Game $game, Question $question, QuestionStatus $status): void
    {
        if ($game->current_question_id !== $question->id || $question->status !== $status) {
            throw ActionRefused::stale();
        }
    }

    /**
     * @return list<array{person_id: string, name: string, votes: int}>
     */
    private function tally(Question $question, int $attempt): array
    {
        $rows = Vote::query()
            ->where('question_id', $question->id)
            ->where('attempt', $attempt)
            ->join('people', 'people.id', '=', 'votes.target_person_id')
            ->groupBy('people.id', 'people.name')
            ->toBase()
            ->get(['people.id as person_id', 'people.name as name', DB::raw('count(*) as votes')]);

        $tally = [];

        foreach ($rows as $row) {
            $tally[] = [
                'person_id' => is_string($row->person_id) ? $row->person_id : '',
                'name' => is_string($row->name) ? $row->name : '',
                'votes' => is_numeric($row->votes) ? (int) $row->votes : 0,
            ];
        }

        return $tally;
    }
}
