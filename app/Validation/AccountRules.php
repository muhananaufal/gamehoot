<?php

declare(strict_types=1);

namespace App\Validation;

use App\Accounts\Username;
use Illuminate\Validation\Rule;
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
            'username' => self::username(),
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => self::password(),
        ];
    }

    /**
     * C-6: lower case letters, digits, dots, underscores and hyphens; unique, also among
     * deleted accounts (C-7). Callers lower-case the typed username first.
     *
     * @return list<mixed>
     */
    public static function username(?string $ignoreId = null): array
    {
        $unique = Rule::unique('users', 'username');

        return ['required', 'string', 'regex:'.Username::PATTERN, $ignoreId === null ? $unique : $unique->ignore($ignoreId)];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return ['username.regex' => __('accounts.username_rule')];
    }

    /**
     * @return list<mixed>
     */
    public static function password(): array
    {
        return ['required', 'string', 'confirmed', Password::defaults()];
    }
}
