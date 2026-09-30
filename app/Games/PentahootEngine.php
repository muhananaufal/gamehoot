<?php

declare(strict_types=1);

namespace App\Games;

use App\Enums\Audience;
use App\Enums\GameType;
use App\Enums\QuestionStatus;
use App\Games\Pentahoot\PentahootActions;
use App\Models\Event;
use App\Models\Game;
use App\Models\PackQuestion;
use App\Models\Question;
use App\Models\QuestionResult;
use App\Models\User;
use Illuminate\Support\Arr;

final class PentahootEngine implements LiveGameEngine
{
    /** Rows of the running tally shown to hosts only (F2). */
    private const int HOST_TALLY_ROWS = 10;

    public function __construct(private PentahootActions $actions) {}

    public function type(): GameType
    {
        return GameType::Pentahoot;
    }

    public function questionRules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:500'],
            // T9
            'duration_seconds' => ['required', 'integer', 'between:5,300'],
        ];
    }

    public function detailAttributes(array $validated): array
    {
        return Arr::only($validated, ['prompt', 'duration_seconds']);
    }

    /**
     * E16: Pentahoot does not use points.
     */
    public function questionAttributes(array $validated): array
    {
        return [];
    }

    public function savePackDetail(PackQuestion $question, array $attributes): void
    {
        $question->pentahoot()->updateOrCreate([], $attributes);
    }

    public function copyDetail(PackQuestion $source, Question $copy): void
    {
        $detail = $source->pentahoot()->firstOrFail();

        $copy->pentahoot()->create([
            'prompt' => $detail->prompt,
            'duration_seconds' => $detail->duration_seconds,
        ]);
    }

    public function initialStatus(): QuestionStatus
    {
        return QuestionStatus::Ready;
    }

    /**
     * F1: the question on screen. "closed" is never sent: clients derive it from ends_at and
     * the server clock (F4), so the cached snapshot stays right while the countdown runs.
     */
    public function snapshot(Game $game, Audience $audience): array
    {
        $questions = $game->questions()->orderBy('position')->get(['id', 'position', 'status']);
        $current = $game->currentQuestion()->with('pentahoot')->first();

        $snapshot = [
            'id' => $game->id,
            'type' => $game->type->value,
            'title' => $game->title,
            'count' => $questions->count(),
            'question' => $current === null ? null : $this->questionPart($current),
        ];

        if ($audience === Audience::Host) {
            // F14: statuses only, in position order; the prompts are on the host page already.
            $snapshot['statuses'] = $questions->map(fn (Question $question): string => $question->status->value)->all();
        }

        return $snapshot;
    }

    public function actions(): array
    {
        return PentahootActions::ACTIONS;
    }

    public function perform(string $action, Event $event, Question $question, User $actor): void
    {
        $this->actions->perform($action, $event, $question, $actor);
    }

    public function liveCounters(Game $game, Audience $audience): array
    {
        $current = $game->currentQuestion()->with('pentahoot')->first();

        if ($current === null || $current->status !== QuestionStatus::Live) {
            return [];
        }

        $attempt = $current->pentahoot()->firstOrFail()->attempt;
        $counters = ['answered' => $current->votes()->where('attempt', $attempt)->count()];

        if ($audience === Audience::Host) {
            $counters['tally'] = $current->votes()
                ->where('attempt', $attempt)
                ->join('people', 'people.id', '=', 'votes.target_person_id')
                ->selectRaw('people.name as name, count(*) as votes')
                ->groupBy('people.id', 'people.name')
                ->orderByDesc('votes')
                ->orderBy('people.name')
                ->limit(self::HOST_TALLY_ROWS)
                // G2: names are unique within an event, so the name can key the counts.
                ->pluck('votes', 'name')
                ->map(fn (mixed $votes, int|string $name): array => ['name' => (string) $name, 'votes' => is_numeric($votes) ? (int) $votes : 0])
                ->values()
                ->all();
        }

        return $counters;
    }

    /**
     * @return array<string, mixed>
     */
    private function questionPart(Question $question): array
    {
        $detail = $question->pentahoot()->firstOrFail();
        $revealed = in_array($question->status, [QuestionStatus::Revealed, QuestionStatus::Done], true);

        return [
            'id' => $question->id,
            'number' => $question->position,
            'prompt' => $detail->prompt,
            'status' => $question->status->value,
            'duration' => $detail->duration_seconds,
            'ends_at' => $detail->ends_at?->getTimestampMs(),
            'attempt' => $detail->attempt,
            // E2, E4b, G10: the frozen top 5 of this attempt, the same on every screen.
            'results' => $revealed ? $this->frozenResults($question, $detail->attempt) : null,
            'total_votes' => $revealed ? $question->votes()->where('attempt', $detail->attempt)->count() : null,
        ];
    }

    /**
     * @return list<array{rank: int, name: string, votes: int}>
     */
    private function frozenResults(Question $question, int $attempt): array
    {
        return array_values($question->results()
            ->where('attempt', $attempt)
            ->with('person:id,name')
            ->orderBy('rank')
            ->get()
            ->sortBy(fn (QuestionResult $row): string => sprintf('%05d %s', $row->rank, mb_strtolower($row->person->name ?? '')))
            ->map(fn (QuestionResult $row): array => ['rank' => $row->rank, 'name' => $row->person->name ?? '', 'votes' => $row->votes])
            ->all());
    }
}
