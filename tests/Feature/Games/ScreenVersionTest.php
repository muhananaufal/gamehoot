<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Enums\GameStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Pentahoot;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

uses(RefreshDatabase::class);

/**
 * G15 (audit FLOW-1): Reveal by one host and Reset by another both used to succeed. Actions that
 * destroy or decide now carry the version their host saw, and are refused when it moved.
 */
describe('version of the screen the host saw (G15)', function (): void {
    it('refuses a Reset sent from a screen another host has changed, and takes one sent from the current screen', function (): void {
        travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $event = Pentahoot::event();
        $cohost = User::factory()->create();
        $event->cohosts()->attach($cohost);
        $question = Pentahoot::question(Pentahoot::running($event));
        $owner = $event->owner()->firstOrFail();
        $url = "/host/{$event->id}/questions/{$question->id}";

        actingAs($owner)->postJson("{$url}/start")->assertNoContent();
        actingAs($owner)->postJson("{$url}/stop")->assertNoContent();
        // E3: votes are taken until one second after the end; Reveal waits for that.
        travelTo(now()->addSeconds(2));
        $seenByCohost = $event->refresh()->state_version;

        // The owner reveals; the co-host's screen still shows the question before Reveal.
        actingAs($owner)->postJson("{$url}/reveal")->assertNoContent();

        actingAs($cohost)->postJson("{$url}/reset", ['version' => $seenByCohost])
            ->assertStatus(409)->assertJsonPath('code', 'STALE_ACTION');
        actingAs($cohost)->postJson("{$url}/reset")
            ->assertStatus(409)->assertJsonPath('code', 'STALE_ACTION');

        actingAs($cohost)->postJson("{$url}/reset", ['version' => $event->refresh()->state_version])->assertNoContent();
    });

    it('lets actions that change nothing for good run without a version', function (): void {
        travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $event = Pentahoot::event();
        $question = Pentahoot::question(Pentahoot::running($event));

        actingAs($event->owner()->firstOrFail())->postJson("/host/{$event->id}/questions/{$question->id}/start")->assertNoContent();
    });

    it('refuses Finish game and Close event from an old screen, and runs them from the current one', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::running($event);
        $owner = $event->owner()->firstOrFail();
        $old = $event->refresh()->state_version - 1;

        actingAs($owner)->from("/host/{$event->id}")
            ->post("/host/{$event->id}/games/{$game->id}/finish", ['version' => $old])
            ->assertRedirect("/host/{$event->id}")->assertSessionHasErrors('action');
        actingAs($owner)->post("/host/{$event->id}/close")->assertSessionHasErrors('action');
        expect($game->refresh()->status)->not->toBe(GameStatus::Finished)
            ->and($event->refresh()->status)->toBe(EventStatus::Open);

        actingAs($owner)->post("/host/{$event->id}/games/{$game->id}/finish", ['version' => $event->refresh()->state_version])
            ->assertRedirect("/host/{$event->id}");
        actingAs($owner)->post("/host/{$event->id}/close", ['version' => $event->refresh()->state_version])->assertSessionHasNoErrors();

        expect($game->refresh()->status)->toBe(GameStatus::Finished)
            ->and($event->refresh()->status)->toBe(EventStatus::Finished);
    });
});
