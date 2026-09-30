<?php

declare(strict_types=1);

use App\Enums\Audience;
use App\Models\Event;
use App\Models\Person;
use App\Realtime\EventSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\travelTo;

uses(RefreshDatabase::class);

function snapshotEvent(): Event
{
    $event = Event::factory()->open()->create(['name' => 'Gathering 2026', 'slug' => 'gathering-2026', 'state_version' => 7]);
    Person::factory()->for($event)->count(2)->claimed()->create();
    Person::factory()->for($event)->create();

    return $event;
}

/**
 * @return array<string, mixed>
 */
function snapshotOf(Event $event, Audience $audience = Audience::Public): array
{
    return app(EventSnapshot::class)->for($event, $audience);
}

it('gives every screen the event, the lobby count and the version (F1, F15, F23)', function (): void {
    travelTo(now()->setTimestamp(1_790_000_000));
    $event = snapshotEvent();

    expect(snapshotOf($event))->toBe([
        'server_now' => 1_790_000_000_000,
        'version' => 7,
        'event' => [
            'name' => 'Gathering 2026',
            'status' => 'open',
            'link' => url('/gathering-2026'),
            'claims_locked' => false,
            'screen_theme' => 'dark',
        ],
        'lobby' => ['joined' => 2],
        'game' => null,
    ]);
});

it('adds the host-only part for the host channel (F2)', function (): void {
    $event = snapshotEvent();

    expect(snapshotOf($event, Audience::Host))->toHaveKey('host', ['names' => 3])
        ->and(snapshotOf($event))->not->toHaveKey('host');
});

it('computes the body once per version, but the server clock on every call (F4, F15)', function (): void {
    $event = snapshotEvent();
    travelTo(now()->setTimestamp(1_790_000_000));
    snapshotOf($event);

    Event::query()->whereKey($event->id)->update(['name' => 'Renamed without a version bump']);
    travelTo(now()->addSeconds(5));
    $cached = snapshotOf($event->fresh() ?? $event);

    expect($cached)->toHaveKey('event.name', 'Gathering 2026')
        ->toHaveKey('server_now', 1_790_000_005_000);

    $bumped = $event->fresh() ?? $event;
    $bumped->bumpStateVersion();
    $bumped->save();

    expect(snapshotOf($bumped))->toHaveKey('event.name', 'Renamed without a version bump');
});

it('tells open screens that a deleted event is gone (D-3)', function (): void {
    $event = snapshotEvent();
    $event->delete();

    expect(snapshotOf($event)['event'])->toMatchArray(['status' => 'deleted', 'link' => url('/gathering-2026')]);
});

it('keeps the largest snapshot well under the Reverb message limit (F14)', function (string $channel): void {
    $audience = Audience::from($channel);
    // Worst case: the longest name allowed, in characters that need the most bytes once escaped.
    $event = Event::factory()->open()->create([
        'name' => str_repeat('😀', 100),
        'slug' => str_repeat('a', 60),
        'state_version' => PHP_INT_MAX,
    ]);

    $message = json_encode([
        'event' => 'state',
        'channel' => $audience->channelName($event),
        'data' => json_encode(snapshotOf($event, $audience)),
    ], JSON_THROW_ON_ERROR);

    expect(strlen($message))->toBeLessThan(EventSnapshot::MAX_MESSAGE_BYTES);
})->with(['public', 'host']);
