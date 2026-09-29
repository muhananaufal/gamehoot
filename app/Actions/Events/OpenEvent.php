<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

/**
 * Draft to open: the join links start working (last step of event setup).
 */
final class OpenEvent
{
    public function handle(Event $event): void
    {
        DB::transaction(function () use ($event): void {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);

            if ($locked->status !== EventStatus::Draft) {
                return;
            }

            $locked->status = EventStatus::Open;
            $locked->bumpStateVersion();
            $locked->save();
        });
    }
}
