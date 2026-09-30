<?php

declare(strict_types=1);

return [
    // F20: connection status, shown on every live screen.
    'connection' => [
        'connecting' => 'Connecting…',
        'reconnecting' => 'Reconnecting…',
        'offline' => 'Connection lost. The screen still updates every few seconds.',
    ],

    'loading' => 'Loading…',

    // F23: the Public View lobby.
    'screen' => [
        'title' => 'Public View',
        'join_at' => 'Join on your phone at',
        'joined' => ['one' => '__N__ player joined', 'other' => '__N__ players joined'],
        'waiting' => 'The game starts soon',
    ],

    // The host's Live control page.
    'live' => [
        'title' => 'Live control',
        'status' => 'Status',
        'joined' => 'Joined',
        'names_count' => ['one' => 'of __N__ name', 'other' => 'of __N__ names'],
        'connection' => 'Connection',
        'connected' => 'Live',
        'open_screen' => 'Open Public View',
        'join_link' => 'Join link',
        'screen_link' => 'Public View link',
        'no_game' => 'No game is running yet.',
    ],
];
