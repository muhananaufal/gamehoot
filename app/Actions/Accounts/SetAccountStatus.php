<?php

declare(strict_types=1);

namespace App\Actions\Accounts;

use App\Enums\LoggedAction;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * C-5: a disabled account cannot sign in; its events stay as they are.
 */
final readonly class SetAccountStatus
{
    public function __construct(private AuditLog $auditLog) {}

    public function handle(User $account, bool $disabled, User $actor): void
    {
        if ($disabled === ($account->disabled_at !== null)) {
            return;
        }

        DB::transaction(function () use ($account, $disabled, $actor): void {
            $account->forceFill(['disabled_at' => $disabled ? now() : null])->save();

            $this->auditLog->record(
                $disabled ? LoggedAction::AccountDisabled : LoggedAction::AccountEnabled,
                $actor,
                payload: ['account_id' => $account->id],
            );
        });
    }
}
