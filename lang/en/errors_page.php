<?php

declare(strict_types=1);

// Error pages for hosts and phones alike: short, no technical words.
return [
    'home' => 'Go to the start page',
    'reload' => 'Reload the page',
    'code' => 'Error :status',

    403 => [
        'title' => 'You can’t open this page',
        'body' => 'This page belongs to another host or needs a different role. Ask the event owner if you should have access.',
    ],
    404 => [
        'title' => 'This page doesn’t exist',
        'body' => 'The link may be mistyped, or what it pointed to was removed.',
    ],
    419 => [
        'title' => 'This page was open too long',
        'body' => 'For your safety the page expired. Reload it and try again; nothing you saved is lost.',
    ],
    429 => [
        'title' => 'Too many tries at once',
        'body' => 'Many people are joining right now. Wait a few seconds, then reload the page.',
    ],
    500 => [
        'title' => 'Something went wrong',
        'body' => 'This is a problem on our side. Try again in a moment; if it keeps happening, tell the host.',
    ],
    503 => [
        'title' => 'Pentahoot is being updated',
        'body' => 'This takes about a minute. Reload the page shortly.',
    ],
];
