<?php

declare(strict_types=1);

namespace App\Actions\Accounts;

use App\Accounts\Username;
use App\Enums\LoggedAction;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * C-7: a super-admin changes the name, username and email of an account. The username and
 * email sign in, so the change is logged (E13).
 */
final readonly class UpdateAccount
{
    public function __construct(private AuditLog $auditLog) {}

    public function handle(User $account, string $name, string $username, string $email, User $actor): void
    {
        DB::transaction(function () use ($account, $name, $username, $email, $actor): void {
            $account->forceFill([
                'name' => trim($name),
                'username' => Username::normalize($username),
                'email' => Str::lower(trim($email)),
            ])->save();

            $this->auditLog->record(LoggedAction::AccountUpdated, $actor, payload: ['account_id' => $account->id]);
        });
    }
}
