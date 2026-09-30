<?php

declare(strict_types=1);

namespace App\Realtime;

use App\Enums\Audience;
use App\Models\Event;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F22: actions call publish() inside their transaction, after bumping state_version (F15).
 * The snapshots go out once the transaction commits; nothing is sent for a rollback.
 * A failed broadcast never fails the action: clients catch up through /state (F17, T2).
 */
final readonly class StatePublisher
{
    public function __construct(
        private Dispatcher $events,
        private EventSnapshot $snapshots,
    ) {}

    public function publish(Event $event): void
    {
        DB::afterCommit(function () use ($event): void {
            foreach (Audience::cases() as $audience) {
                try {
                    $this->events->dispatch(new StateChanged($event, $audience, $this->snapshots->for($event, $audience)));
                } catch (Throwable $exception) {
                    // K5: the event id only, never names or tokens.
                    Log::warning('Realtime broadcast failed.', [
                        'event_id' => $event->id,
                        'audience' => $audience->value,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        });
    }
}
