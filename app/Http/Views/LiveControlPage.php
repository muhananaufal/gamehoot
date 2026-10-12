<?php

declare(strict_types=1);

namespace App\Http\Views;

use App\Actions\Games\ReplayOnScreen;
use App\Enums\EventPhase;
use App\Enums\GameStatus;
use App\Games\GameEngines;
use App\Games\Replay\Moment;
use App\Games\Replay\ReplayMoments;
use App\Models\Event;
use App\Models\Game;
use App\Models\Person;
use Illuminate\Support\Collection;

/**
 * D-10: data for Live control. The phase decides what the control column offers; the steps
 * run from preparing to closing; the game on screen brings its own control (F13).
 */
final readonly class LiveControlPage
{
    public function __construct(private GameEngines $engines, private ReplayMoments $replays) {}

    /**
     * @return array<string, mixed>
     */
    public function data(Event $event): array
    {
        /** @var Collection<int, Game> $games */
        $games = $event->games()->orderBy('position')->withCount('questions')->get();
        $onScreen = $games->firstWhere('id', $event->active_game_id);
        $unplayed = $games->filter(fn (Game $game): bool => $game->status !== GameStatus::Finished && $game->id !== $event->active_game_id)->values();
        $phase = EventPhase::of($event->status, $onScreen?->status, $games->count(), $unplayed->count());

        $questions = collect();
        $people = [];

        if ($onScreen instanceof Game) {
            $engine = $this->engines->live($onScreen->type);
            $questions = $engine->liveQuestions($onScreen);

            if ($engine->picksWinners()) {
                $people = $event->people()->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Person $person): array => ['id' => $person->id, 'name' => $person->name])
                    ->all();
            }
        }

        $next = $unplayed->first();

        return [
            'event' => $event,
            'phase' => $phase,
            'games' => $games,
            'game' => $onScreen,
            'running' => $phase === EventPhase::Playing ? $onScreen : null,
            'next' => $next,
            'names' => $event->people()->count(),
            'questions' => $questions,
            'people' => $people,
            'steps' => $this->steps($phase, $games, $onScreen, $next, $event->people()->count()),
            'replay' => $this->replay($event),
        ];
    }

    /**
     * D-11: the Replay card: the moments of each game, the one on screen, and whether a question
     * blocks a replay now. No game is picked first unless one is replayed (the card's view).
     *
     * @return array{groups: list<array{game: Game, moments: list<Moment>}>, current: ?array{kind: string, game: string, question: ?string}, busy: bool}
     */
    private function replay(Event $event): array
    {
        $groups = $this->replays->forEvent($event);
        $current = $event->replay_kind === null ? null : [
            'kind' => $event->replay_kind->value,
            'game' => (string) $event->replay_game_id,
            'question' => $event->replay_question_id,
        ];

        return [
            'groups' => $groups,
            'current' => $current,
            'busy' => ReplayOnScreen::questionOnScreen($event),
        ];
    }

    /**
     * The timeline from preparing to closing (F20: one vertical timeline).
     *
     * @param  Collection<int, Game>  $games
     * @return list<array{label: string, hint: string, state: string, click?: string}>
     */
    private function steps(EventPhase $phase, Collection $games, ?Game $onScreen, ?Game $next, int $names): array
    {
        $draft = $phase === EventPhase::Draft;
        $steps = [
            [
                'label' => __('live.steps.prepare'),
                'hint' => trans_choice('live.names', $names, ['count' => $names]).' · '.trans_choice('live.games', $games->count(), ['count' => $games->count()]),
                'state' => $draft ? 'current' : 'done',
            ],
            ['label' => __('live.steps.open'), 'hint' => '', 'state' => $draft ? 'todo' : 'done'],
        ];

        foreach ($games as $game) {
            $running = $phase === EventPhase::Playing && $onScreen?->is($game);
            $upNext = $phase === EventPhase::Open && $next?->is($game);
            $status = match (true) {
                $game->status === GameStatus::Finished => 'finished',
                $running => 'running',
                $upNext => 'next',
                default => 'waiting',
            };
            $step = [
                'label' => $game->position.'. '.$game->title,
                'hint' => __('live.game_status.'.$status).' · '.trans_choice('games.questions', $game->questions_count ?? 0),
                'state' => match ($status) {
                    'finished' => 'done',
                    'running', 'next' => 'current',
                    default => 'todo',
                },
            ];

            // D-10: while no game runs, a later game can start out of order, after a confirmation.
            if ($phase === EventPhase::Open && $status === 'waiting') {
                $step['click'] = "document.getElementById('start-{$game->id}').showModal()";
            }

            $steps[] = $step;
        }

        $steps[] = [
            'label' => __('live.steps.close'),
            'hint' => '',
            'state' => match ($phase) {
                EventPhase::Finished => 'done',
                EventPhase::AllPlayed => 'current',
                default => 'todo',
            },
        ];

        return $steps;
    }
}
