<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

function stateEvent(): Event
{
    return Event::factory()->create(['name' => 'Gathering 2026', 'slug' => 'gathering-2026', 'state_version' => 3]);
}

describe('public state (F1)', function (): void {
    it('returns the public snapshot without host data, for any event status', function (): void {
        stateEvent();

        getJson('/gathering-2026/state')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('version', 3)
            ->assertJsonPath('event.status', 'draft')
            ->assertJsonMissingPath('host');
    });

    it('answers unknown and deleted events with a fixed error code (K4)', function (): void {
        getJson('/no-such-event/state')
            ->assertNotFound()
            ->assertExactJson(['code' => 'EVENT_NOT_FOUND', 'message' => 'We can’t find this event']);

        stateEvent()->delete();
        getJson('/gathering-2026/state')->assertNotFound()->assertJsonPath('code', 'EVENT_NOT_FOUND');
    });
});

describe('host state (F2, C-2)', function (): void {
    it('gives the owner and co-hosts the host snapshot', function (): void {
        $event = stateEvent();
        $cohost = User::factory()->create();
        $event->cohosts()->attach($cohost);

        foreach ([$event->owner()->firstOrFail(), $cohost] as $host) {
            actingAs($host)->getJson("/host/{$event->id}/state")
                ->assertOk()
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertJsonPath('version', 3)
                ->assertJsonPath('host.names', 0);
        }
    });

    it('refuses other hosts and guests', function (): void {
        $event = stateEvent();

        actingAs(User::factory()->create())->getJson("/host/{$event->id}/state")->assertForbidden();
        auth()->logout();
        getJson("/host/{$event->id}/state")->assertUnauthorized();
    });
});
