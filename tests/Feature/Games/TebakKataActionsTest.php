<?php

declare(strict_types=1);

use App\Enums\Audience;
use App\Enums\GameStatus;
use App\Enums\LoggedAction;
use App\Enums\QuestionStatus;
use App\Games\GameEngines;
use App\Models\ActionLog;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameResult;
use App\Models\Question;
use App\Models\User;
use App\Realtime\EventSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Pentahoot;
use Tests\Support\TebakKata;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $input
 * @return TestResponse<Response>
 */
function kataAction(Event $event, Question $question, string $action, array $input = []): TestResponse
{
    return actingAs($event->owner()->firstOrFail())->postJson("/host/{$event->id}/questions/{$question->id}/{$action}", $input);
}

function kataQuestion(Game $game, int $position): Question
{
    return $game->questions()->where('position', $position)->firstOrFail();
}

/**
 * @return array<string, mixed>
 */
function publicSnapshot(Event $event): array
{
    return app(EventSnapshot::class)->for($event->refresh(), Audience::Public);
}

describe('show, hint and the public channel (E5, F2, D-5)', function (): void {
    it('shows a queued question with only its open boxes lettered on the public channel', function (): void {
        $event = Pentahoot::event();
        $game = TebakKata::running($event, [['Capital of France?', 'PARIS', [0]]]);
        $question = kataQuestion($game, 1);

        kataAction($event, $question, 'show')->assertNoContent();

        expect($question->refresh()->status)->toBe(QuestionStatus::Shown)
            ->and($game->refresh()->current_question_id)->toBe($question->id);

        $public = publicSnapshot($event);
        expect(data_get($public, 'game.question.boxes'))->toBe([[
            ['c' => 'P', 'b' => 0, 'o' => 'i'],
            ['c' => null, 'b' => 1, 'o' => null],
            ['c' => null, 'b' => 2, 'o' => null],
            ['c' => null, 'b' => 3, 'o' => null],
            ['c' => null, 'b' => 4, 'o' => null],
        ]]);
        // The answer never travels on the public channel while the question is open.
        expect(json_encode($public, JSON_THROW_ON_ERROR))->not->toContain('PARIS');
        expect(app(EventSnapshot::class)->for($event, Audience::Host))->toHaveKey('game.question.answer', 'PARIS');
    });

    it('opens a box as a hint, but always leaves one closed (E5)', function (): void {
        $event = Pentahoot::event();
        $game = TebakKata::running($event, [['Two letters?', 'OK', []]]);
        $question = kataQuestion($game, 1);
        kataAction($event, $question, 'show');

        kataAction($event, $question, 'hint', ['box' => 1])->assertNoContent();
        expect(data_get(publicSnapshot($event), 'game.question.boxes.0.1'))->toBe(['c' => 'K', 'b' => 1, 'o' => 'h']);

        kataAction($event, $question, 'hint', ['box' => 1])->assertConflict()->assertJsonPath('code', 'STALE_ACTION');
        kataAction($event, $question, 'hint', ['box' => 0])->assertConflict();
        kataAction($event, $question, 'hint', ['box' => 7])->assertConflict();
        kataAction($event, $question, 'hint', ['box' => 'x'])->assertUnprocessable();
    });

    it('shows one question at a time, and any that is still queued (D-5, G7)', function (): void {
        $event = Pentahoot::event();
        $game = TebakKata::running($event);

        kataAction($event, kataQuestion($game, 2), 'show')->assertNoContent();
        kataAction($event, kataQuestion($game, 1), 'show')->assertConflict();
    });
});

describe('skip (E6, D-5)', function (): void {
    it('moves the question to the end of the queue once', function (): void {
        $event = Pentahoot::event();
        $game = TebakKata::running($event, [['A?', 'AB'], ['B?', 'CD'], ['C?', 'EF']]);
        $first = kataQuestion($game, 1);
        kataAction($event, $first, 'show');

        kataAction($event, $first, 'skip')->assertNoContent();

        expect($first->refresh()->only(['status', 'skip_used', 'position']))->toBe(['status' => QuestionStatus::Queued, 'skip_used' => true, 'position' => 4])
            ->and($game->refresh()->current_question_id)->toBeNull();

        kataAction($event, $first, 'show')->assertNoContent();
        kataAction($event, $first, 'skip')->assertConflict();
    });

    it('cannot skip when nothing else is left in the queue (E6)', function (): void {
        $event = Pentahoot::event();
        $game = TebakKata::running($event, [['Only?', 'AB']]);
        $only = kataQuestion($game, 1);
        kataAction($event, $only, 'show');

        kataAction($event, $only, 'skip')->assertConflict();
    });
});

describe('winner and surrender (D-6, E10, E13, E16)', function (): void {
    it('gives the question to the winner with its points, logs it and reveals the answer', function (): void {
        travelTo(CarbonImmutable::parse('2026-10-15 10:00:00.123'));
        $event = Pentahoot::event();
        $game = TebakKata::running($event, [['Capital of France?', 'PARIS', [0], 2]]);
        $question = kataQuestion($game, 1);
        $rita = Pentahoot::person($event, 'Rita Wulandari');
        kataAction($event, $question, 'show');

        kataAction($event, $question, 'winner', ['person' => $rita->id])->assertNoContent();

        expect($question->refresh()->status)->toBe(QuestionStatus::Won)
            ->and($question->winner_person_id)->toBe($rita->id)
            // E10: the tie-break needs the milliseconds.
            ->and($question->resolved_at?->format('H:i:s.v'))->toBe('10:00:00.123')
            ->and(ActionLog::query()->where('action', LoggedAction::WinnerPicked)->value('payload'))->toEqual(['question_id' => $question->id, 'person_id' => $rita->id]);

        $public = publicSnapshot($event);
        expect($public)->toHaveKey('game.question.winner', 'Rita Wulandari')
            ->toHaveKey('game.question.answer', 'PARIS');
        kataAction($event, $question, 'winner', ['person' => $rita->id])->assertConflict();
    });

    it('refuses a winner who is not on this event\'s list', function (): void {
        $event = Pentahoot::event();
        $question = kataQuestion(TebakKata::running($event), 1);
        $stranger = Pentahoot::person(Pentahoot::event(['Someone Else'], ['slug' => 'other-party']), 'Someone Else');
        kataAction($event, $question, 'show');

        kataAction($event, $question, 'winner', ['person' => $stranger->id])->assertUnprocessable()->assertJsonValidationErrors('person');
    });

    it('surrenders a question without a winner, logs it and reveals the answer', function (): void {
        $event = Pentahoot::event();
        $question = kataQuestion(TebakKata::running($event), 1);
        kataAction($event, $question, 'show');

        kataAction($event, $question, 'surrender')->assertNoContent();

        expect($question->refresh()->status)->toBe(QuestionStatus::Surrendered)
            ->and($question->winner_person_id)->toBeNull()
            ->and(ActionLog::query()->where('action', LoggedAction::QuestionSurrendered)->count())->toBe(1)
            ->and(publicSnapshot($event))->toHaveKey('game.question.answer', 'PARIS');
        kataAction($event, $question, 'skip')->assertConflict();
    });
});

describe('leaderboard and final results (E11, E12, E17, G10)', function (): void {
    it('shows the leaderboard after a win until the next question is shown', function (): void {
        $event = Pentahoot::event();
        $game = TebakKata::running($event);
        $first = kataQuestion($game, 1);
        kataAction($event, $first, 'leaderboard')->assertConflict();
        kataAction($event, $first, 'show');
        kataAction($event, $first, 'winner', ['person' => Pentahoot::person($event, 'Budi Santoso')->id]);

        kataAction($event, $first, 'leaderboard')->assertNoContent();
        expect(publicSnapshot($event))->toHaveKey('game.leaderboard', [
            ['rank' => 1, 'name' => 'Budi Santoso', 'points' => 1, 'movement' => null],
        ]);

        kataAction($event, kataQuestion($game, 2), 'show')->assertNoContent();
        expect(publicSnapshot($event))->toHaveKey('game.leaderboard', null);
    });

    it('freezes the final top 5 when the game finishes, with the tie-break (E10, G10)', function (): void {
        travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $event = Pentahoot::event();
        $game = TebakKata::running($event, [['A?', 'AB'], ['B?', 'CD']]);
        foreach ([1 => 'Rita Wulandari', 2 => 'Budi Santoso'] as $position => $name) {
            kataAction($event, kataQuestion($game, $position), 'show');
            kataAction($event, kataQuestion($game, $position), 'winner', ['person' => Pentahoot::person($event, $name)->id]);
            travelTo(now()->addSecond());
        }

        actingAs($event->owner()->firstOrFail())->post("/host/{$event->id}/games/{$game->id}/finish")->assertRedirect();

        expect($game->refresh()->status)->toBe(GameStatus::Finished)
            ->and(GameResult::query()->orderBy('rank')->get()->map(fn (GameResult $row): string => "{$row->rank} {$row->person?->name} {$row->points}")->all())
            ->toBe(['1 Rita Wulandari 1', '2 Budi Santoso 1']);
        // E17: the final podium stays on screen (G7).
        expect(publicSnapshot($event))->toHaveKey('game.status', 'finished')
            ->toHaveKey('game.final.0.name', 'Rita Wulandari');
    });
});

describe('guards (C-2, C-3)', function (): void {
    it('keeps other hosts out and refuses actions of another game type', function (): void {
        $event = Pentahoot::event();
        $question = kataQuestion(TebakKata::running($event), 1);

        actingAs(User::factory()->create())->postJson("/host/{$event->id}/questions/{$question->id}/show")->assertForbidden();
        kataAction($event, $question, 'reveal')->assertNotFound();
    });

    it('locks a question that has been on screen against editing (D-2)', function (): void {
        $event = Pentahoot::event();
        $game = TebakKata::running($event);
        $engine = app(GameEngines::class)->live($game->type);
        $question = kataQuestion($game, 1);

        expect($engine->wasShown($question))->toBeFalse();
        kataAction($event, $question, 'show');
        expect($engine->wasShown($question->refresh()))->toBeTrue();
    });
});
