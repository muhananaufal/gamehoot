<?php

declare(strict_types=1);

use App\Actions\Events\CloseEvent;
use App\Actions\People\ClaimName;
use App\Enums\Audience;
use App\Models\Event;
use App\Models\Person;
use App\People\ClaimCookie;
use App\Realtime\AnswerCounted;
use App\Realtime\ClientConfig;
use App\Realtime\EventSnapshot;
use App\Realtime\JoinedCounted;
use App\Realtime\StateChanged;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as Events;
use Tests\Support\Pentahoot;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;
use function Pest\Laravel\withCredentials;

uses(RefreshDatabase::class);

/**
 * F25 (audit WS-1, WS-2, PERF-4): every message goes only to the screens that read it. These
 * counts are the contract the F19 load test measures on staging.
 */
describe('realtime message budget (F25)', function (): void {
    it('sends a vote only as a counter on the screen channel, never to the phones', function (): void {
        travelTo(CarbonImmutable::parse('2026-10-15 10:00:00.000'));
        $event = Pentahoot::event(['Rita Wulandari', 'Budi Santoso', 'Ana Putri']);
        $question = Pentahoot::question(Pentahoot::running($event));
        actingAs($event->owner()->firstOrFail())->postJson("/host/{$event->id}/questions/{$question->id}/start")->assertNoContent();
        auth()->logout();

        $token = ClaimCookie::newToken();
        Pentahoot::person($event, 'Rita Wulandari')->forceFill(['claim_token_hash' => ClaimCookie::hash($token), 'claimed_at' => now()])->save();
        Events::fake([StateChanged::class, AnswerCounted::class]);

        withCredentials()->withCookie(ClaimCookie::name($event), $token)
            ->postJson("/{$event->slug}/questions/{$question->id}/vote", ['target' => Pentahoot::person($event, 'Ana Putri')->id, 'attempt' => 1])
            ->assertCreated();

        Events::assertNotDispatched(StateChanged::class);
        Events::assertDispatchedTimes(AnswerCounted::class, 1);
        Events::assertDispatched(AnswerCounted::class, fn (AnswerCounted $sent): bool => $sent->broadcastOn()->name === "event.{$event->id}.screen");
    });

    it('sends a claim as one joined counter, without a snapshot or a version bump', function (): void {
        $event = Event::factory()->create(['status' => 'open', 'state_version' => 7]);
        Person::factory()->for($event)->claimed()->create();
        $person = Person::factory()->for($event)->create();
        Events::fake([StateChanged::class, JoinedCounted::class]);

        app(ClaimName::class)->handle($event, $person);

        Events::assertNotDispatched(StateChanged::class);
        Events::assertDispatchedTimes(JoinedCounted::class, 1);
        Events::assertDispatched(JoinedCounted::class, fn (JoinedCounted $sent): bool => $sent->broadcastOn()->name === "event.{$event->id}.screen"
            && $sent->broadcastAs() === 'joined'
            && $sent->broadcastWith() === ['joined' => 2]);
        expect($event->fresh()?->state_version)->toBe(7);
    });

    it('sends one snapshot per audience when an action runs another one first (F22)', function (): void {
        $event = Pentahoot::event();
        Pentahoot::running($event);
        Events::fake([StateChanged::class]);

        // Close finishes the running game first; both bump the version and publish.
        app(CloseEvent::class)->handle($event, $event->owner()->firstOrFail());

        $version = $event->fresh()?->state_version;
        Events::assertDispatchedTimes(StateChanged::class, 2);
        Events::assertDispatched(StateChanged::class, fn (StateChanged $sent): bool => $sent->audience === Audience::Public && $sent->payload['version'] === $version);
        Events::assertDispatched(StateChanged::class, fn (StateChanged $sent): bool => $sent->audience === Audience::Host && $sent->payload['version'] === $version);
    });

    it('lets the Public View and Live control follow the counters, but not the phones', function (): void {
        $event = Event::factory()->create(['status' => 'open']);
        $configs = app(ClientConfig::class);
        $names = fn (array $config): array => array_column($config['channels'], 'name');

        expect($names($configs->for($event, Audience::Public, ['name' => 'Rita', 'vote' => null])))->not->toContain("event.{$event->id}.screen")
            ->and($names($configs->for($event, Audience::Public, counters: true)))->toContain("event.{$event->id}.screen")
            ->and($names($configs->for($event, Audience::Host)))->toContain("event.{$event->id}.screen");
    });

    it('counts the joined phones fresh in every snapshot, since claims keep the version (F15)', function (): void {
        $event = Event::factory()->create(['status' => 'open']);
        $person = Person::factory()->for($event)->create();
        $state = fn (): mixed => data_get(app(EventSnapshot::class)->for($event->fresh() ?? $event, Audience::Public), 'lobby.joined');

        expect($state())->toBe(0);
        app(ClaimName::class)->handle($event, $person);
        expect($state())->toBe(1);
    });
});
