<?php

declare(strict_types=1);

namespace App\Actions\Accounts;

use App\Enums\LoggedAction;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * C-1: accounts are created by a super-admin, or by the console for the first super-admin (O10).
 */
final readonly class CreateAccount
{
    public function __construct(private AuditLog $auditLog) {}

    public function handle(string $name, string $email, string $password, bool $superAdmin, ?User $actor): User
    {
        return DB::transaction(function () use ($name, $email, $password, $superAdmin, $actor): User {
            $user = new User([
                'name' => trim($name),
                'email' => Str::lower(trim($email)),
                'password' => $password,
            ]);
            $user->forceFill(['is_super_admin' => $superAdmin])->save();

            $this->auditLog->record(LoggedAction::AccountCreated, $actor, payload: ['account_id' => $user->id]);

            return $user;
        });
    }
}
