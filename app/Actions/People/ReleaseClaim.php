<?php

declare(strict_types=1);

namespace App\Actions\People;

use App\Enums\LoggedAction;
use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use App\Realtime\StatePublisher;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * B-1, B-6, G3: only a host can free a claimed name (wrong pick, new phone, cleared cookie).
 * Allowed after the list is locked (B-5). Logged with the person id only (K5).
 */
final readonly class ReleaseClaim
{
    public function __construct(private AuditLog $auditLog, private StatePublisher $publisher) {}

    public function handle(Event $event, Person $person, User $actor): void
    {
        DB::transaction(function () use ($event, $person, $actor): void {
            // F15, F23: same lock order as ClaimName (event, then person), and the lobby count changes.
            $row = Event::query()->lockForUpdate()->findOrFail($event->id);
            $locked = Person::query()->lockForUpdate()->findOrFail($person->id);

            if ($locked->claimed_at === null) {
                return;
            }

            $locked->forceFill(['claim_token_hash' => null, 'claimed_at' => null])->save();
            $row->bumpStateVersion();
            $row->save();
            $this->publisher->publish($row);

            $this->auditLog->record(LoggedAction::ClaimReleased, $actor, $event, ['person_id' => $locked->id]);
        });
    }
}
