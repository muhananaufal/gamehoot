<?php

declare(strict_types=1);

return [
    'nav' => [
        'main' => 'Main',
        'workspace' => 'Workspace',
        'admin' => 'Admin',
        'events' => 'Events',
        'host_accounts' => 'Host accounts',
        'question_packs' => 'Question packs',
        'deleted_events' => 'Deleted events',
        'all_deleted_events' => 'All deleted events',
        'names' => 'Names',
        'settings' => 'Settings',
        'open_menu' => 'Open menu',
        'close_menu' => 'Close menu',
        'breadcrumb' => 'Breadcrumb',
        'switch_theme' => 'Switch theme',
        'recent' => 'Recent events',
        'collapse' => 'Collapse sidebar',
        'expand' => 'Expand sidebar',
    ],

    'dashboard' => [
        'title' => 'Events',
        'all' => 'All',
        'subtitle' => 'Events you own or co-host',
        'event' => 'Event',
        'status' => 'Status',
        'names' => 'Names',
        'games' => 'Games',
        'your_role' => 'Your role',
        'owner' => 'Owner',
        'cohost' => 'Co-host',
        'names_count' => '{0} No names yet|{1} 1 name|[2,*] :count names',
        'games_count' => '{0} No games yet|{1} 1 game|[2,*] :count games',
        'empty_title' => 'No events yet.',
        'empty' => 'Write a question pack first, then make an event, add the names and pick its games.',
        'empty_filtered' => 'No event matches.',
        'search' => 'Search name, link or owner',
        'all_statuses' => 'All statuses',
        'changed' => 'Changed',
        'changed_at' => 'Changed :time',
        'created_column' => 'Created',
        'date_filter' => 'Filter by created date',
        'created' => 'Created :date',
        'owned_by' => 'Owned by :name',
    ],

    'event_status' => [
        'draft' => 'Draft',
        // D-10: an open event reads Playing everywhere; Open event stays the action that opens it.
        'open' => 'Playing',
        'finished' => 'Finished',
    ],
];
