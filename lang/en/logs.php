<?php

declare(strict_types=1);

// E13: labels for the logged actions (the irreversible ones and changes of access or links), keyed by LoggedAction value with dots as underscores.
return [
    'title' => 'Activity log',
    'subtitle' => 'Important actions, newest first. That is everything that cannot be undone, plus changes to access and links. Times are in WIB.',
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
        'event_opened' => 'Opened the event',
        'event_deleted' => 'Deleted the event',
        'event_restored' => 'Restored the event',
        'event_closed' => 'Closed the event',
        'event_reopened' => 'Reopened the event',
        'event_ownership_transferred' => 'Transferred ownership',
        'event_join_locked' => 'Locked new name claims',
        'event_join_unlocked' => 'Unlocked new name claims',
        'event_link_changed' => 'Changed the event link',
        'event_cohost_added' => 'Added a co-host',
        'event_cohost_removed' => 'Removed a co-host',
        'person_claim_released' => 'Released a claimed name',
        'person_link_regenerated' => 'Made a new personal link',
        'person_deleted' => 'Deleted a name',
        'game_started' => 'Started a game',
        'game_deleted' => 'Deleted a game',
        'game_finished_early' => 'Finished a game early',
        'question_revealed' => 'Revealed a question',
        'question_reset' => 'Reset the votes of a question',
        'question_winner_picked' => 'Picked a winner',
        'question_surrendered' => 'Surrendered a question',
    ],
];
