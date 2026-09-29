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

    case EventDeleted = 'event.deleted';
    case EventRestored = 'event.restored';
    case EventClosed = 'event.closed';
    case EventReopened = 'event.reopened';
    case OwnershipTransferred = 'event.ownership_transferred';
    case JoinLocked = 'event.join_locked';
    case JoinUnlocked = 'event.join_unlocked';
}
