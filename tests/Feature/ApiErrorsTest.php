<?php

declare(strict_types=1);

use App\Exceptions\ActionRefused;
use App\Models\Event;
use App\Models\Question;
use App\Models\User;
use App\People\ClaimCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\Pentahoot;
use Tests\Support\TebakKata;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withCredentials;

uses(RefreshDatabase::class);

/**
 * A phone with a fresh claim on the first name of the event votes for the second name.
 *
 * @return TestResponse<Response>
 */
function strayVote(Event $event, string $questionId): TestResponse
{
    $voter = Pentahoot::person($event, 'Rita Wulandari');
    $token = ClaimCookie::newToken();
    $voter->forceFill(['claim_token_hash' => ClaimCookie::hash($token), 'claimed_at' => now()])->save();

    return withCredentials()->withCookie(ClaimCookie::name($event), $token)
        ->postJson("/{$event->slug}/questions/{$questionId}/vote", ['target' => Pentahoot::person($event, 'Budi Santoso')->id, 'attempt' => 1]);
}

describe('votes that cannot count answer VOTE_CLOSED (K4)', function (): void {
    it('refuses a vote for a question that does not exist', function (): void {
        $event = Pentahoot::event();
        Pentahoot::running($event);

        strayVote($event, (string) Str::uuid7())->assertConflict()->assertJsonPath('code', 'VOTE_CLOSED');
    });

    it('refuses a vote for a question of another event', function (): void {
        $event = Pentahoot::event();
        Pentahoot::running($event);
        $other = Pentahoot::question(Pentahoot::running(Pentahoot::event([], ['slug' => 'other-party'])));

        strayVote($event, $other->id)->assertConflict()->assertJsonPath('code', 'VOTE_CLOSED');
    });

    it('refuses a vote for a Tebak question', function (): void {
        $event = Pentahoot::event();
        $kata = TebakKata::running($event);
        $question = $kata->questions()->orderBy('position')->firstOrFail();

        strayVote($event, $question->id)->assertConflict()->assertJsonPath('code', 'VOTE_CLOSED');
    });
});

describe('host actions the state never allows answer ACTION_UNAVAILABLE (K4)', function (): void {
    it('refuses an action the game type does not have', function (): void {
        $event = Pentahoot::event();
        $question = Pentahoot::question(Pentahoot::running($event));

        actingAs($event->owner()->firstOrFail())->postJson("/host/{$event->id}/questions/{$question->id}/hint", ['box' => 1])
            ->assertConflict()
            ->assertExactJson(['code' => 'ACTION_UNAVAILABLE', 'message' => __('errors.ACTION_UNAVAILABLE')]);
    });

    it('refuses to skip the last question in the queue (E6)', function (): void {
        $event = Pentahoot::event();
        $game = TebakKata::running($event, [['Only?', 'AB']]);
        $only = $game->questions()->sole();
        $host = $event->owner()->firstOrFail();
        actingAs($host)->postJson("/host/{$event->id}/questions/{$only->id}/show")->assertNoContent();

        actingAs($host)->postJson("/host/{$event->id}/questions/{$only->id}/skip")
            ->assertConflict()
            ->assertJsonPath('code', 'ACTION_UNAVAILABLE');
    });
});

describe('framework errors on JSON requests use {code, message} (K4)', function (): void {
    it('answers an unknown question of a host route with NOT_FOUND', function (): void {
        $event = Pentahoot::event();

        actingAs($event->owner()->firstOrFail())->postJson("/host/{$event->id}/questions/".Str::uuid7().'/start')
            ->assertNotFound()
            ->assertExactJson(['code' => 'NOT_FOUND', 'message' => __('errors.NOT_FOUND')]);
    });

    it('answers a host without access with FORBIDDEN', function (): void {
        $event = Pentahoot::event();
        $question = Pentahoot::question(Pentahoot::running($event));

        actingAs(User::factory()->create())->postJson("/host/{$event->id}/questions/{$question->id}/start")
            ->assertForbidden()
            ->assertExactJson(['code' => 'FORBIDDEN', 'message' => __('errors.FORBIDDEN')]);
    });

    it('answers a signed-out request with UNAUTHENTICATED', function (): void {
        $event = Pentahoot::event();
        $question = Pentahoot::question(Pentahoot::running($event));

        postJson("/host/{$event->id}/questions/{$question->id}/start")
            ->assertUnauthorized()
            ->assertExactJson(['code' => 'UNAUTHENTICATED', 'message' => __('errors.UNAUTHENTICATED')]);
    });

    it('adds VALIDATION_FAILED to validation errors and keeps the field errors', function (): void {
        $event = Pentahoot::event();
        $question = Pentahoot::question(Pentahoot::running($event));

        strayVoteWithoutTarget($event, $question)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['code', 'message', 'errors' => ['target']]);
    });

    it('maps an expired session and the rate limit to fixed codes', function (int $status, string $code): void {
        Route::post('/_test/http/{status}', fn (int $status) => throw new HttpException($status));

        postJson("/_test/http/{$status}")->assertStatus($status)->assertExactJson(['code' => $code, 'message' => __("errors.{$code}")]);
    })->with([
        [419, 'SESSION_EXPIRED'],
        [429, 'TOO_MANY_REQUESTS'],
        [503, 'SERVICE_UNAVAILABLE'],
    ]);

    it('hides server errors behind SERVER_ERROR when debug is off', function (): void {
        config(['app.debug' => false]);
        Route::post('/_test/boom', fn () => throw new RuntimeException('secret detail'));

        postJson('/_test/boom')->assertStatus(500)->assertExactJson(['code' => 'SERVER_ERROR', 'message' => __('errors.SERVER_ERROR')]);
    });
});

it('does not report domain refusals as errors (K5)', function (): void {
    Exceptions::fake();
    $event = Pentahoot::event();
    $question = Pentahoot::question(Pentahoot::running($event));

    strayVote($event, $question->id)->assertConflict();

    Exceptions::assertNotReported(ActionRefused::class);
});

/**
 * @return TestResponse<Response>
 */
function strayVoteWithoutTarget(Event $event, Question $question): TestResponse
{
    $voter = Pentahoot::person($event, 'Rita Wulandari');
    $token = ClaimCookie::newToken();
    $voter->forceFill(['claim_token_hash' => ClaimCookie::hash($token), 'claimed_at' => now()])->save();

    return withCredentials()->withCookie(ClaimCookie::name($event), $token)
        ->postJson("/{$event->slug}/questions/{$question->id}/vote", ['attempt' => 1]);
}
