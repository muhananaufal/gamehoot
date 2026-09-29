<?php

declare(strict_types=1);

namespace App\Actions\Accounts;

use App\Enums\LoggedAction;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * C-5: a super-admin sets a temporary password and hands it over in person. Open sessions of
 * that account end on their next request (auth.session middleware compares the password hash).
 */
final readonly class ResetPassword
{
    public function __construct(private AuditLog $auditLog) {}

    public function handle(User $account, string $password, User $actor): void
    {
        DB::transaction(function () use ($account, $password, $actor): void {
            $account->forceFill(['password' => $password])->setRememberToken(Str::random(60));
            $account->save();

            $this->auditLog->record(LoggedAction::PasswordReset, $actor, payload: ['account_id' => $account->id]);
        });
    }
}
