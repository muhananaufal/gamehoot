<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * E13: irreversible actions written to action_logs.
 */
enum LoggedAction: string
{
    case AccountCreated = 'account.created';
    case PasswordReset = 'account.password_reset';
    case AccountDisabled = 'account.disabled';
    case AccountEnabled = 'account.enabled';
}
