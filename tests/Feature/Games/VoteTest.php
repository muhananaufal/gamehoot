<?php

declare(strict_types=1);

use App\Actions\Votes\CastVote;
use App\Enums\Audience;
use App\Models\Event;
use App\Models\Person;
use App\Models\Question;
use App\Models\Vote;
use App\People\ClaimCookie;
use App\Realtime\AnswerCounted;
use App\Realtime\EventSnapshot;
use App\Realtime\StateChanged;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Pentahoot;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\travelTo;
use function Pest\Laravel\withCredentials;

uses(RefreshDatabase::class);

/**
 * Gives the person a fresh claim token and returns it, as the phone's cookie would hold it.
 */
function phoneOf(Person $person): string
{
    $token = ClaimCookie::newToken();
    $person->forceFill(['claim_token_hash' => ClaimCookie::hash($token), 'claimed_at' => now()])->save();

    return $token;
}

/**
 * @param  array<string, mixed>  $body
 * @return TestResponse<Response>
 */
function vote(Event $event, Question $question, ?string $token, array $body): TestResponse
{
    // A browser sends the cookie with a same-origin fetch; JSON test requests need withCredentials().
    $request = withCredentials();

    if ($token !== null) {
        $request->withCookie(ClaimCookie::name($event), $token);
    }

    return $request->postJson("/{$event->slug}/questions/{$question->id}/vote", $body);
}

/**
 * A running game whose first question is live until 10:00:20.000.
 *
 * @return array{Event, Question}
 */
function liveQuestion(): array
{
    travelTo(CarbonImmutable::parse('2026-10-15 10:00:00.000'));
    $event = Pentahoot::event(['Rita Wulandari', 'Budi Santoso', 'Ana Putri']);
    $question = Pentahoot::question(Pentahoot::running($event));
    actingAs($event->owner()->firstOrFail())->postJson("/host/{$event->id}/questions/{$question->id}/start")->assertNoContent();
    auth()->logout();

    return [$event, $question];
}

describe('casting a vote (F3, E3, G2, T1)', function (): void {
    it('records the vote, answers with the chosen name and broadcasts only the counter (F14, F24)', function (): void {
        [$event, $question] = liveQuestion();
        $version = $event->refresh()->state_version;
        $budi = Pentahoot::person($event, 'Budi Santoso');
        Events::fake([StateChanged::class, AnswerCounted::class]);

        vote($event, $question, phoneOf(Pentahoot::person($event, 'Rita Wulandari')), ['target' => $budi->id, 'attempt' => 1])
            ->assertCreated()
            ->assertExactJson(['target' => ['id' => $budi->id, 'name' => 'Budi Santoso']]);

        expect(Vote::query()->sole()->only(['target_person_id', 'attempt']))->toBe(['target_person_id' => $budi->id, 'attempt' => 1])
            ->and($event->refresh()->state_version)->toBe($version);
        Events::assertNotDispatched(StateChanged::class);
        Events::assertDispatched(AnswerCounted::class, fn (AnswerCounted $sent): bool => $sent->broadcastWith() === [
            'question_id' => $question->id,
            'attempt' => 1,
            'answered' => 1,
        ]);
    });

    it('accepts a vote until one second after the countdown, then closes (E3)', function (): void {
        [$event, $question] = liveQuestion();
        $target = ['target' => Pentahoot::person($event, 'Ana Putri')->id, 'attempt' => 1];

        travelTo(CarbonImmutable::parse('2026-10-15 10:00:21.000'));
        vote($event, $question, phoneOf(Pentahoot::person($event, 'Rita Wulandari')), $target)->assertCreated();

        travelTo(CarbonImmutable::parse('2026-10-15 10:00:21.001'));
        vote($event, $question, phoneOf(Pentahoot::person($event, 'Budi Santoso')), $target)
            ->assertConflict()
            ->assertExactJson(['code' => 'VOTE_CLOSED', 'message' => 'Voting for this question has closed.']);
    });

    it('takes one vote per person, and allows voting for yourself (G2, B-2)', function (): void {
        [$event, $question] = liveQuestion();
        $rita = Pentahoot::person($event, 'Rita Wulandari');
        $token = phoneOf($rita);

        vote($event, $question, $token, ['target' => $rita->id, 'attempt' => 1])->assertCreated();
        vote($event, $question, $token, ['target' => Pentahoot::person($event, 'Ana Putri')->id, 'attempt' => 1])
            ->assertConflict()
            ->assertJsonPath('code', 'ALREADY_VOTED');
    });

    it('refuses a vote from an earlier attempt after a reset (T1)', function (): void {
        [$event, $question] = liveQuestion();
        actingAs($event->owner()->firstOrFail())->postJson("/host/{$event->id}/questions/{$question->id}/reset")->assertNoContent();
        actingAs($event->owner()->firstOrFail())->postJson("/host/{$event->id}/questions/{$question->id}/start")->assertNoContent();
        auth()->logout();

        vote($event, $question, phoneOf(Pentahoot::person($event, 'Rita Wulandari')), ['target' => Pentahoot::person($event, 'Ana Putri')->id, 'attempt' => 1])
            ->assertConflict()
            ->assertJsonPath('code', 'STALE_ACTION');
    });

    it('refuses phones without a valid claim (B-6)', function (): void {
        [$event, $question] = liveQuestion();
        $target = ['target' => Pentahoot::person($event, 'Ana Putri')->id, 'attempt' => 1];

        vote($event, $question, null, $target)->assertUnauthorized()->assertJsonPath('code', 'NOT_CLAIMED');
        vote($event, $question, 'not-a-real-token', $target)->assertUnauthorized();
    });

    it('refuses targets from another event and questions that are not live', function (): void {
        [$event, $question] = liveQuestion();
        $stranger = Person::factory()->for(Pentahoot::event([], ['slug' => 'other-party']))->create();
        $token = phoneOf(Pentahoot::person($event, 'Rita Wulandari'));

        vote($event, $question, $token, ['target' => $stranger->id, 'attempt' => 1])->assertUnprocessable()->assertJsonValidationErrors('target');

        $second = Pentahoot::question($question->game()->firstOrFail(), 2);
        vote($event, $second, $token, ['target' => Pentahoot::person($event, 'Ana Putri')->id, 'attempt' => 1])
            ->assertConflict()
            ->assertJsonPath('code', 'VOTE_CLOSED');
    });

    it('limits votes per phone, not per IP, so a shared venue WiFi keeps working (F11)', function (): void {
        [$event, $question] = liveQuestion();
        $rita = phoneOf(Pentahoot::person($event, 'Rita Wulandari'));
        $body = ['target' => Pentahoot::person($event, 'Ana Putri')->id, 'attempt' => 1];

        foreach (range(1, 30) as $attempt) {
            vote($event, $question, $rita, $body);
        }

        vote($event, $question, $rita, $body)->assertTooManyRequests();
        vote($event, $question, phoneOf(Pentahoot::person($event, 'Budi Santoso')), $body)->assertCreated();
    });
});

describe('what the phone knows (F1, F9, E4)', function (): void {
    it('adds the phone\'s own vote to the public state, never to the cached snapshot', function (): void {
        [$event, $question] = liveQuestion();
        $rita = Pentahoot::person($event, 'Rita Wulandari');
        $token = phoneOf($rita);
        // Cast directly: the HTTP helper would leave the cookie on the test case.
        app(CastVote::class)->handle($event->refresh(), $question, $rita, Pentahoot::person($event, 'Ana Putri'), 1);

        // Without the cookie first: cookies set on the test case stay for later requests.
        getJson('/year-end-party/state')->assertJsonPath('me', null)->assertJsonPath('game.answered', 1);

        withCredentials()->withCookie(ClaimCookie::name($event), $token)->getJson('/year-end-party/state')
            ->assertJsonPath('me.name', 'Rita Wulandari')
            ->assertJsonPath('me.vote', ['question_id' => $question->id, 'attempt' => 1, 'target' => 'Ana Putri'])
            ->assertJsonPath('game.answered', 1);
        expect(app(EventSnapshot::class)->for($event->refresh(), Audience::Public))->not->toHaveKey('me');
    });

    it('gives claimed phones the whole name list to search offline (F9)', function (): void {
        [$event] = liveQuestion();

        getJson('/year-end-party/people')->assertUnauthorized()->assertJsonPath('code', 'NOT_CLAIMED');

        withCredentials()->withCookie(ClaimCookie::name($event), phoneOf(Pentahoot::person($event, 'Rita Wulandari')))->getJson('/year-end-party/people')
            ->assertOk()
            ->assertJsonPath('people.*.name', ['Ana Putri', 'Budi Santoso', 'Rita Wulandari']);
    });
});
