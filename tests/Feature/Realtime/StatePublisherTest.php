<?php

declare(strict_types=1);

use App\Enums\Audience;
use App\Enums\QuestionStatus;
use App\Models\Event;
use App\Models\QuestionResult;
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
use Tests\Support\Pentahoot;

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
    Events::assertDispatched(StateChanged::class, fn (StateChanged $sent): bool => $sent->audience === Audience::Public && $sent->payload['version'] === 1);
    Events::assertDispatched(StateChanged::class, fn (StateChanged $sent): bool => $sent->audience === Audience::Host && array_key_exists('host', $sent->payload));
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

it('sends only a refresh signal when the snapshot would be too big for Reverb (F24)', function (): void {
    Events::fake([StateChanged::class]);
    // A reveal list long enough to pass the budget: 80 tied names of 60 characters.
    $event = Pentahoot::event(array_map(fn (int $i): string => str_pad("Person {$i} ", 60, 'x'), range(1, 80)));
    $game = Pentahoot::running($event);
    $question = Pentahoot::question($game);
    $question->forceFill(['status' => QuestionStatus::Revealed])->save();
    $game->forceFill(['current_question_id' => $question->id])->save();
    foreach ($event->people()->get() as $person) {
        QuestionResult::query()->create(['question_id' => $question->id, 'attempt' => 1, 'person_id' => $person->id, 'rank' => 1, 'votes' => 1, 'frozen_at' => now()]);
    }

    app(StatePublisher::class)->publish($event->refresh());

    Events::assertDispatched(StateChanged::class, fn (StateChanged $sent): bool => $sent->audience === Audience::Public
        && array_keys($sent->payload) === ['server_now', 'version', 'refresh']
        && $sent->payload['refresh'] === true);
});
