<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * C-1, C-4: only super-admins manage host accounts.
 */
final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->is_super_admin;
    }

    public function create(User $actor): bool
    {
        return $actor->is_super_admin;
    }

    public function resetPassword(User $actor, User $account): bool
    {
        return $actor->is_super_admin;
    }

    /**
     * A super-admin cannot lock themselves out.
     */
    public function changeStatus(User $actor, User $account): bool
    {
        return $actor->is_super_admin && ! $actor->is($account);
    }
}
