<?php

declare(strict_types=1);

namespace App\Actions\Accounts;

use App\Enums\LoggedAction;
use App\Models\Event;
use App\Models\User;
use App\Realtime\HostChannelKeys;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * C-7: deleting an account is a soft delete. It can no longer sign in and leaves the lists, but
 * stays in the database so events, packs, files and the Activity log keep its name. An account
 * that still owns events (deleted ones too) is refused: they would have no owner who can sign in.
 */
final readonly class DeleteAccount
{
    public function __construct(private AuditLog $auditLog) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $account, User $actor): void
    {
        DB::transaction(function () use ($account, $actor): void {
            $locked = User::query()->lockForUpdate()->findOrFail($account->id);

            if (Event::withTrashed()->where('owner_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['account' => __('accounts.delete_owns_events')]);
            }

            // F26: the events it co-hosted get a new host channel key before it leaves them.
            $locked->cohostedEvents()->lockForUpdate()->get()->each(fn (Event $event) => HostChannelKeys::rotate($event));
            $locked->cohostedEvents()->detach();
            $locked->delete();

            $this->auditLog->record(LoggedAction::AccountDeleted, $actor, payload: ['account_id' => $locked->id]);
        });
    }
}
