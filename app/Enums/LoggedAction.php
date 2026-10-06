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
    case AccountUpdated = 'account.updated';
    case AccountDeleted = 'account.deleted';

    case EventOpened = 'event.opened';
    case EventDeleted = 'event.deleted';
    case EventRestored = 'event.restored';
    case EventClosed = 'event.closed';
    case EventReopened = 'event.reopened';
    case OwnershipTransferred = 'event.ownership_transferred';
    case JoinLocked = 'event.join_locked';
    case JoinUnlocked = 'event.join_unlocked';
    case LinkChanged = 'event.link_changed';
    case CohostAdded = 'event.cohost_added';
    case CohostRemoved = 'event.cohost_removed';

    case ClaimReleased = 'person.claim_released';
    case LinkRegenerated = 'person.link_regenerated';
    case PersonDeleted = 'person.deleted';
    case GameStarted = 'game.started';
    case GameDeleted = 'game.deleted';
    case GameFinishedEarly = 'game.finished_early';
    case QuestionRevealed = 'question.revealed';
    case QuestionReset = 'question.reset';
    case WinnerPicked = 'question.winner_picked';
    case QuestionSurrendered = 'question.surrendered';
}
