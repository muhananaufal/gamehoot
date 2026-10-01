<?php

declare(strict_types=1);

namespace App\Actions\People;

use App\Enums\LoggedAction;
use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use App\People\JoinToken;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * T5: a leaked personal link stops working once a new one is made. A phone that already
 * claimed the name keeps its claim cookie.
 */
final readonly class RegenerateJoinLink
{
    public function __construct(private AuditLog $auditLog) {}

    public function handle(Event $event, Person $person, User $actor): void
    {
        DB::transaction(function () use ($event, $person, $actor): void {
            $person->forceFill(['join_token' => JoinToken::generate()])->save();
            // E13: the old link stops working for good (T5).
            $this->auditLog->record(LoggedAction::LinkRegenerated, $actor, $event, ['person_id' => $person->id]);
        });
    }
}
