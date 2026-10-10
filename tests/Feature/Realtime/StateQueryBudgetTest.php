<?php

declare(strict_types=1);

use App\People\ClaimCookie;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Pentahoot;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;
use function Pest\Laravel\withCredentials;

uses(RefreshDatabase::class);

/**
 * Audit PERF-3: GET /{event}/state is what hundreds of phones call after a reconnect (F17) and
 * while polling (T2). The game and question on screen are read once (LiveState), not once per
 * part of the answer.
 */
it('answers a phone during a live Pentahoot question with a small, fixed number of queries', function (): void {
    travelTo(CarbonImmutable::parse('2026-10-15 10:00:00.000'));
    $event = Pentahoot::event(['Rita Wulandari', 'Budi Santoso', 'Ana Putri']);
    $question = Pentahoot::question(Pentahoot::running($event));
    actingAs($event->owner()->firstOrFail())->postJson("/host/{$event->id}/questions/{$question->id}/start")->assertNoContent();
    auth()->logout();

    $token = ClaimCookie::newToken();
    $rita = Pentahoot::person($event, 'Rita Wulandari');
    $rita->forceFill(['claim_token_hash' => ClaimCookie::hash($token), 'claimed_at' => now()])->save();
    withCredentials()->withCookie(ClaimCookie::name($event), $token)
        ->postJson("/{$event->slug}/questions/{$question->id}/vote", ['target' => Pentahoot::person($event, 'Ana Putri')->id, 'attempt' => 1])
        ->assertCreated();

    // The body is cached after the first call (F15); count a phone asking again.
    $phone = withCredentials()->withCookie(ClaimCookie::name($event), $token);
    $phone->getJson("/{$event->slug}/state")->assertOk();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    $phone->getJson("/{$event->slug}/state")->assertOk()->assertJsonPath('me.vote.target', 'Ana Putri');

    expect($queries)->toBeLessThanOrEqual(STATE_QUERY_BUDGET);
});

// Measured 10 October 2026: 12 queries before LiveState, 9 after.
const STATE_QUERY_BUDGET = 9;
