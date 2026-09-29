<?php

declare(strict_types=1);

namespace App\Validation;

use Illuminate\Validation\Rules\Password;

/**
 * C-1, O7: one set of account rules for the admin form and the console command.
 */
final class AccountRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function newAccount(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => self::password(),
        ];
    }

    /**
     * @return list<mixed>
     */
    public static function password(): array
    {
        return ['required', 'string', 'confirmed', Password::defaults()];
    }
}
