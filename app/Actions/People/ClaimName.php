<?php

declare(strict_types=1);

namespace App\Actions\People;

use App\Enums\EventStatus;
use App\Exceptions\ClaimRefused;
use App\Models\Event;
use App\Models\Person;
use App\People\ClaimCookie;
use App\Realtime\StatePublisher;
use Illuminate\Support\Facades\DB;

/**
 * B-1: the first phone to claim a name gets it. The update only matches an unclaimed row,
 * so two phones racing for one name cannot both win (F6). The event row stays locked until
 * the claim is written, which keeps the open and join-lock checks valid (B-7) and lets the
 * claim bump state_version for the lobby count (F15, F23).
 */
final readonly class ClaimName
{
    public function __construct(private StatePublisher $publisher) {}

    /**
     * @return string the raw token for the phone's cookie
     *
     * @throws ClaimRefused
     */
    public function handle(Event $event, Person $person): string
    {
        return DB::transaction(function () use ($event, $person): string {
            $current = Event::query()->lockForUpdate()->findOrFail($event->id);

            if ($current->status !== EventStatus::Open) {
                throw ClaimRefused::eventNotOpen();
            }

            if ($current->join_locked_at !== null) {
                throw ClaimRefused::claimsLocked();
            }

            $token = ClaimCookie::newToken();

            $claimed = Person::query()
                ->whereKey($person->id)
                ->where('event_id', $current->id)
                ->whereNull('claimed_at')
                ->update(['claim_token_hash' => ClaimCookie::hash($token), 'claimed_at' => now()]);

            if ($claimed !== 1) {
                throw ClaimRefused::nameTaken();
            }

            $current->bumpStateVersion();
            $current->save();
            $this->publisher->publish($current);

            return $token;
        });
    }
}
