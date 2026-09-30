<?php

declare(strict_types=1);

// E13: labels for the irreversible actions, keyed by LoggedAction value with dots as underscores.
return [
    'title' => 'Activity log',
    'subtitle' => 'Actions that cannot be undone, newest first. Times are in WIB.',
    'empty' => 'Nothing has been logged for this event yet.',
    'system' => 'System',
    'when' => 'When',
    'who' => 'Who',
    'what' => 'What',
    'actions' => [
        'account_created' => 'Created a host account',
        'account_password_reset' => 'Reset a password',
        'account_disabled' => 'Disabled an account',
        'account_enabled' => 'Enabled an account',
        'event_deleted' => 'Deleted the event',
        'event_restored' => 'Restored the event',
        'event_closed' => 'Closed the event',
        'event_reopened' => 'Reopened the event',
        'event_ownership_transferred' => 'Transferred ownership',
        'event_join_locked' => 'Locked new name claims',
        'event_join_unlocked' => 'Unlocked new name claims',
        'person_claim_released' => 'Released a claimed name',
        'game_finished_early' => 'Finished a game early',
        'question_reset' => 'Reset the votes of a question',
        'question_winner_picked' => 'Picked a winner',
        'question_surrendered' => 'Surrendered a question',
    ],
];
