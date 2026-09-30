<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // A signing broadcaster, so /broadcasting/auth runs the channel callbacks (F2).
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => '1',
    ]);

    // Channels register on the broadcaster that was the default at boot (null in phpunit.xml).
    require base_path('routes/channels.php');
});

/**
 * @return TestResponse<Response>
 */
function hostChannelAuth(Event $event): TestResponse
{
    return postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-event.{$event->id}.host"]);
}

it('lets the owner and co-hosts into the host channel (F2, C-2)', function (): void {
    $event = Event::factory()->create();
    $cohost = User::factory()->create();
    $event->cohosts()->attach($cohost);

    foreach ([$event->owner()->firstOrFail(), $cohost] as $host) {
        actingAs($host);
        hostChannelAuth($event)->assertOk()->assertJsonStructure(['auth']);
    }
});

it('keeps other hosts, disabled accounts and guests out of the host channel', function (): void {
    $event = Event::factory()->create();

    actingAs(User::factory()->create());
    hostChannelAuth($event)->assertForbidden();

    $owner = $event->owner()->firstOrFail();
    $owner->forceFill(['disabled_at' => now()])->save();
    actingAs($owner);
    hostChannelAuth($event)->assertStatus(302);

    auth()->logout();
    hostChannelAuth($event)->assertUnauthorized();
});

it('refuses the host channel of a deleted event', function (): void {
    $event = Event::factory()->create();
    $event->delete();

    actingAs($event->owner()->firstOrFail());
    hostChannelAuth($event)->assertForbidden();
});
