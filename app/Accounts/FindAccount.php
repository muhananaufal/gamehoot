<?php

declare(strict_types=1);

namespace App\Accounts;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * C-6: one field takes a username or an email (sign in, co-host invites, transfers). Text with
 * an @ is an email, anything else a username; both are read in lower case. Deleted accounts are
 * never found (C-7).
 */
final class FindAccount
{
    public static function byLogin(string $typed): ?User
    {
        $typed = trim($typed);

        if ($typed === '') {
            return null;
        }

        return str_contains($typed, '@')
            ? User::query()->where('email', Str::lower($typed))->first()
            : User::query()->where('username', Username::normalize($typed))->first();
    }
}
