<?php

declare(strict_types=1);

use App\Enums\Audience;
use App\Enums\LoggedAction;
use App\Enums\QuestionStatus;
use App\Models\ActionLog;
use App\Models\Event;
use App\Models\Game;
use App\Models\Question;
use App\Models\QuestionResult;
use App\Models\User;
use App\Models\Vote;
use App\Realtime\EventSnapshot;
use App\Realtime\StateChanged;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Pentahoot;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

uses(RefreshDatabase::class);

/**
 * @return TestResponse<Response>
 */
function hostAction(Event $event, Question $question, string $action): TestResponse
{
    return actingAs($event->owner()->firstOrFail())->postJson("/host/{$event->id}/questions/{$question->id}/{$action}");
}

function castVote(Event $event, Question $question, string $voter, string $target, int $attempt = 1): void
{
    Vote::query()->create([
        'question_id' => $question->id,
        'voter_person_id' => Pentahoot::person($event, $voter)->id,
        'target_person_id' => Pentahoot::person($event, $target)->id,
        'attempt' => $attempt,
    ]);
}

beforeEach(function (): void {
    travelTo(CarbonImmutable::parse('2026-10-15 10:00:00.250'));
});

describe('start and stop (F4, D-5)', function (): void {
    it('opens a question with a countdown on the server clock, to the millisecond', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::running($event);
        $question = Pentahoot::question($game);
        Events::fake([StateChanged::class]);

        hostAction($event, $question, 'start')->assertNoContent();

        $detail = $question->pentahoot()->firstOrFail();
        expect($question->refresh()->status)->toBe(QuestionStatus::Live)
            ->and($detail->ends_at?->format('Y-m-d H:i:s.v'))->toBe('2026-10-15 10:00:20.250')
            ->and($game->refresh()->current_question_id)->toBe($question->id);
        Events::assertDispatched(StateChanged::class, fn (StateChanged $sent): bool => data_get($sent->snapshot, 'game.question.status') === 'live'
            && data_get($sent->snapshot, 'game.question.ends_at') === CarbonImmutable::parse('2026-10-15 10:00:20.250')->getTimestampMs());
    });

    it('stops the countdown at once, and refuses a second stop', function (): void {
        $event = Pentahoot::event();
        $question = Pentahoot::question(Pentahoot::running($event));
        hostAction($event, $question, 'start');

        travelTo(now()->addSeconds(5));
        hostAction($event, $question, 'stop')->assertNoContent();
        expect($question->pentahoot()->firstOrFail()->ends_at?->format('H:i:s.v'))->toBe('10:00:05.250');

        travelTo(now()->addSeconds(2));
        hostAction($event, $question, 'stop')->assertConflict()->assertExactJson([
            'code' => 'STALE_ACTION',
            'message' => 'The screen was out of date. It has been refreshed, try again.',
        ]);
    });

    it('opens any question that is not done, but only one at a time (D-5, G7)', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::running($event, ['First?', 'Second?', 'Third?']);

        hostAction($event, Pentahoot::question($game, 3), 'start')->assertNoContent();
        hostAction($event, Pentahoot::question($game, 1), 'start')->assertConflict();

        Pentahoot::question($game, 3)->forceFill(['status' => QuestionStatus::Done])->save();
        $game->forceFill(['current_question_id' => null])->save();
        hostAction($event, Pentahoot::question($game, 3), 'start')->assertConflict();
        hostAction($event, Pentahoot::question($game, 1), 'start')->assertNoContent();
    });
});

describe('reveal and next (E1, E2, E3, E4, G10)', function (): void {
    it('waits for votes still on their way before revealing (E3)', function (): void {
        $event = Pentahoot::event();
        $question = Pentahoot::question(Pentahoot::running($event));
        hostAction($event, $question, 'start');

        travelTo(now()->addSeconds(20)->addMilliseconds(1_000));
        hostAction($event, $question, 'reveal')->assertConflict()->assertJsonPath('code', 'STALE_ACTION');

        travelTo(now()->addMilliseconds(1));
        hostAction($event, $question, 'reveal')->assertNoContent();
    });

    it('freezes the ranked top 5 of this attempt and shows it on every screen', function (): void {
        $event = Pentahoot::event(['Rita Wulandari', 'Budi Santoso', 'Ana Putri', 'Dewi Lestari']);
        $question = Pentahoot::question(Pentahoot::running($event));
        hostAction($event, $question, 'start');
        castVote($event, $question, 'Rita Wulandari', 'Budi Santoso');
        castVote($event, $question, 'Ana Putri', 'Budi Santoso');
        castVote($event, $question, 'Budi Santoso', 'Rita Wulandari');
        castVote($event, $question, 'Dewi Lestari', 'Ana Putri');
        hostAction($event, $question, 'stop');
        travelTo(now()->addSeconds(2));
        Events::fake([StateChanged::class]);

        hostAction($event, $question, 'reveal')->assertNoContent();

        expect($question->refresh()->status)->toBe(QuestionStatus::Revealed)
            ->and(QuestionResult::query()->orderBy('rank')->get(['rank', 'votes', 'attempt'])->toArray())->toBe([
                ['rank' => 1, 'votes' => 2, 'attempt' => 1],
                ['rank' => 2, 'votes' => 1, 'attempt' => 1],
                ['rank' => 2, 'votes' => 1, 'attempt' => 1],
            ]);
        Events::assertDispatched(StateChanged::class, fn (StateChanged $sent): bool => $sent->audience->value === 'public'
            && data_get($sent->snapshot, 'game.question.results') === [
                ['rank' => 1, 'name' => 'Budi Santoso', 'votes' => 2],
                ['rank' => 2, 'name' => 'Ana Putri', 'votes' => 1],
                ['rank' => 2, 'name' => 'Rita Wulandari', 'votes' => 1],
            ]
            && data_get($sent->snapshot, 'game.question.total_votes') === 4);
    });

    it('reveals a question nobody voted on as an empty result (E4)', function (): void {
        $event = Pentahoot::event();
        $question = Pentahoot::question(Pentahoot::running($event));
        hostAction($event, $question, 'start');
        hostAction($event, $question, 'stop');
        travelTo(now()->addSeconds(2));

        hostAction($event, $question, 'reveal')->assertNoContent();

        expect(QuestionResult::query()->count())->toBe(0)
            ->and(app(EventSnapshot::class)->for($event->refresh(), Audience::Public))
            ->toHaveKey('game.question.results', []);
    });

    it('moves on after the reveal and locks the question (D-5)', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::running($event);
        $question = Pentahoot::question($game);
        hostAction($event, $question, 'next')->assertConflict();
        hostAction($event, $question, 'start');
        hostAction($event, $question, 'stop');
        travelTo(now()->addSeconds(2));
        hostAction($event, $question, 'reveal');

        hostAction($event, $question, 'next')->assertNoContent();

        expect($question->refresh()->status)->toBe(QuestionStatus::Done)
            ->and($game->refresh()->current_question_id)->toBeNull();
        hostAction($event, $question, 'reset')->assertConflict();
    });
});

describe('reset (T1, E13)', function (): void {
    it('deletes the votes and the frozen results, starts a new attempt and logs it', function (): void {
        $event = Pentahoot::event();
        $question = Pentahoot::question(Pentahoot::running($event));
        hostAction($event, $question, 'start');
        castVote($event, $question, 'Rita Wulandari', 'Budi Santoso');
        hostAction($event, $question, 'stop');
        travelTo(now()->addSeconds(2));
        hostAction($event, $question, 'reveal');

        hostAction($event, $question, 'reset')->assertNoContent();

        $detail = $question->pentahoot()->firstOrFail();
        expect($question->refresh()->status)->toBe(QuestionStatus::Ready)
            ->and($detail->attempt)->toBe(2)
            ->and($detail->ends_at)->toBeNull()
            ->and(Vote::query()->count())->toBe(0)
            ->and(QuestionResult::query()->count())->toBe(0)
            ->and(ActionLog::query()->where('action', LoggedAction::QuestionReset)->value('payload'))
            ->toEqual(['question_id' => $question->id, 'attempt' => 1]);
    });

    it('does nothing for a question that has not started', function (): void {
        $event = Pentahoot::event();
        $question = Pentahoot::question(Pentahoot::running($event));

        hostAction($event, $question, 'reset')->assertConflict();
        expect(ActionLog::query()->count())->toBe(0);
    });
});

describe('guards (F6, C-2, C-3)', function (): void {
    it('refuses unknown actions, questions of other events and games that are not running', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::running($event);
        $question = Pentahoot::question($game);
        $other = Pentahoot::event([], ['slug' => 'other-party']);
        $foreign = Pentahoot::question(Pentahoot::running($other));
        $idle = Pentahoot::question(Pentahoot::game($event));

        hostAction($event, $question, 'explode')->assertNotFound();
        hostAction($event, $foreign, 'start')->assertNotFound();
        hostAction($event, $idle, 'start')->assertConflict()->assertJsonPath('code', 'STALE_ACTION');
    });

    it('lets co-hosts run questions and keeps other hosts out', function (): void {
        $event = Pentahoot::event();
        $question = Pentahoot::question(Pentahoot::running($event));
        $cohost = User::factory()->create();
        $event->cohosts()->attach($cohost);

        actingAs($cohost)->postJson("/host/{$event->id}/questions/{$question->id}/start")->assertNoContent();
        actingAs(User::factory()->create())->postJson("/host/{$event->id}/questions/{$question->id}/stop")->assertForbidden();
    });

    it('does not open a question twice when two hosts click at once', function (): void {
        $event = Pentahoot::event();
        $question = Pentahoot::question(Pentahoot::running($event));

        hostAction($event, $question, 'start')->assertNoContent();
        hostAction($event, $question, 'start')->assertConflict();
        expect(Game::query()->firstOrFail()->current_question_id)->toBe($question->id);
    });
});
