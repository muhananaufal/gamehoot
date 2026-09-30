<?php

declare(strict_types=1);

$appHost = parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST);

return [

    /*
    |--------------------------------------------------------------------------
    | Reverb server (A8: its own container, F7: one server, no Redis)
    |--------------------------------------------------------------------------
    */

    'default' => env('REVERB_SERVER', 'reverb'),

    'servers' => [

        'reverb' => [
            'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
            'port' => env('REVERB_SERVER_PORT', 8080),
            'path' => env('REVERB_SERVER_PATH', ''),
            'hostname' => env('REVERB_HOST'),
            'options' => [
                'tls' => [],
            ],
            // F14: requests from the app (broadcasts) above this size are refused with 413.
            'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 10_000),
            'scaling' => [
                'enabled' => false,
            ],
        ],

    ],

    'apps' => [

        'provider' => 'config',

        'apps' => [
            [
                'key' => env('REVERB_APP_KEY'),
                'secret' => env('REVERB_APP_SECRET'),
                'app_id' => env('REVERB_APP_ID'),
                'options' => [
                    'host' => env('REVERB_HOST'),
                    'port' => env('REVERB_PORT', 8080),
                    'scheme' => env('REVERB_SCHEME', 'http'),
                    'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
                ],
                // Only pages served from the app's own host may open a connection.
                'allowed_origins' => array_values(array_filter(explode(',', (string) env('REVERB_ALLOWED_ORIGINS', is_string($appHost) ? $appHost : 'localhost')))),
                'ping_interval' => env('REVERB_APP_PING_INTERVAL', 60),
                'activity_timeout' => env('REVERB_APP_ACTIVITY_TIMEOUT', 30),
                'max_connections' => env('REVERB_APP_MAX_CONNECTIONS'),
                // F3: clients never send data over the socket, only subscribe.
                'max_message_size' => env('REVERB_APP_MAX_MESSAGE_SIZE', 10_000),
                'accept_client_events_from' => 'none',
                'rate_limiting' => [
                    'enabled' => false,
                ],
            ],
        ],

    ],

];
