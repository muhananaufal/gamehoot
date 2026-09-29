<?php

declare(strict_types=1);

return [
    'pick_title' => 'Pick your name to join the game',
    'search' => 'Search name',
    'claimed_hidden' => 'Names already claimed on another phone are hidden.',
    'wrong_name' => 'Picked the wrong name? Ask the host to release it.',
    'no_names' => 'No names are available right now. Ask the host.',
    'no_match' => 'No name matches your search.',
    'join_as' => 'Join as :name',

    'welcome' => 'You’re in, :name!',
    'waiting' => 'Waiting for the host to start the next game. Keep this page open.',

    'error_code' => 'Error code: :code',
    'back_to_names' => 'Back to the name list',

    'screens' => [
        'not_found' => [
            'title' => 'We can’t find this event',
            'body' => 'Check the link on the screen or ask the host for a new one. The event may also have been removed.',
        ],
        'not_open' => [
            'title' => 'This event is not open yet',
            'body' => 'The host hasn’t opened :event yet. Try again in a moment.',
        ],
        'ended' => [
            'title' => 'This event has ended',
            'body' => 'Thanks for playing at :event. You can close this page.',
        ],
        'locked' => [
            'title' => 'Joining is closed for now',
            'body' => 'The host has stopped new players from joining. If you should be playing, ask the host to open joining again.',
        ],
        'taken' => [
            'title' => 'This name is already in use',
            'body' => '“:name” is playing on another phone. If that’s you on a new phone, ask the host to release the name, then try again.',
        ],
        'link_invalid' => [
            'title' => 'This link no longer works',
            'body' => 'The host made a new personal link for this name. Ask the host for your new link, or pick your name from the list.',
        ],
    ],
];
