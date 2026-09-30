<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | F5, F7: one Reverb server, messages sent straight from the request without a queue.
    | Tests use "null"; "log" writes messages to the log instead of sending them.
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'null'),

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            // How the app reaches Reverb (inside Docker: the reverb service over plain HTTP).
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
            // How browsers reach Reverb. Sent to the page at runtime, not baked into the
            // Vite build, so one image serves every environment (P1).
            'client' => [
                'host' => env('REVERB_CLIENT_HOST'),
                'port' => (int) env('REVERB_CLIENT_PORT', 443),
                'scheme' => env('REVERB_CLIENT_SCHEME', 'https'),
            ],
            'client_options' => [],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
