<?php

declare(strict_types=1);

use App\Enums\Audience;
use App\Models\Event;
use App\Realtime\EventSnapshot;
use App\Realtime\StateChanged;
use App\Realtime\StatePublisher;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Support\Facades\Log;
use Tests\Support\FailingBroadcaster;

uses(RefreshDatabase::class);

it('broadcasts the new state on both channels only after the transaction commits (F22)', function (): void {
    Events::fake([StateChanged::class]);
    $event = Event::factory()->create();

    DB::transaction(function () use ($event): void {
        $event->bumpStateVersion();
        $event->save();
        app(StatePublisher::class)->publish($event);

        Events::assertNotDispatched(StateChanged::class);
    });

    Events::assertDispatchedTimes(StateChanged::class, 2);
    Events::assertDispatched(StateChanged::class, fn (StateChanged $sent): bool => $sent->audience === Audience::Public && $sent->snapshot['version'] === 1);
    Events::assertDispatched(StateChanged::class, fn (StateChanged $sent): bool => $sent->audience === Audience::Host && array_key_exists('host', $sent->snapshot));
});

it('sends nothing when the transaction rolls back (F22)', function (): void {
    Events::fake([StateChanged::class]);
    $event = Event::factory()->create();

    try {
        DB::transaction(function () use ($event): void {
            app(StatePublisher::class)->publish($event);

            throw new RuntimeException('rolled back');
        });
    } catch (RuntimeException) {
    }

    Events::assertNotDispatched(StateChanged::class);
});

it('keeps the action alive and logs the event id when a broadcast fails (F22, K5)', function (): void {
    Broadcast::extend('failing', fn (): FailingBroadcaster => new FailingBroadcaster);
    config(['broadcasting.connections.failing' => ['driver' => 'failing'], 'broadcasting.default' => 'failing']);
    $event = Event::factory()->create();

    Log::shouldReceive('warning')->twice()->withArgs(
        fn (string $message, array $context): bool => $message === 'Realtime broadcast failed.'
            && $context['event_id'] === $event->id
            && in_array($context['audience'], ['public', 'host'], true),
    );

    app(StatePublisher::class)->publish($event);
});

it('names the channels by event UUID and sends the snapshot as the payload (F1, F18)', function (): void {
    $event = Event::factory()->create();
    $snapshot = app(EventSnapshot::class)->for($event, Audience::Host);

    $public = new StateChanged($event, Audience::Public, $snapshot);
    $host = new StateChanged($event, Audience::Host, $snapshot);

    expect($public->broadcastOn())->toEqual(new Channel("event.{$event->id}.public"))
        ->and($host->broadcastOn())->toEqual(new PrivateChannel("event.{$event->id}.host"))
        ->and($public->broadcastAs())->toBe('state')
        ->and($public->broadcastWith())->toBe($snapshot);
});
