<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over websockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'pusher' => [
            'driver'  => 'pusher',
            'key'     => env('PUSHER_APP_KEY'),
            'secret'  => env('PUSHER_APP_SECRET'),
            'app_id'  => env('PUSHER_APP_ID'),
            'options' => env('PUSHER_HOST')
                // Self-hosted Soketi (or compatible). Need explicit host/port/scheme.
                ? [
                    'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
                    'host'    => env('PUSHER_HOST'),
                    'port'    => (int) env('PUSHER_PORT', 6001),
                    'scheme'  => env('PUSHER_SCHEME', 'http'),
                    'useTLS'  => env('PUSHER_SCHEME', 'http') === 'https',
                ]
                // Pusher cloud. Just cluster + useTLS — the SDK resolves
                // api-{cluster}.pusher.com:443 internally. Passing scheme,
                // encrypted, host, or port alongside this triggers a
                // longstanding bug in pusher/pusher-php-server 7.x where the
                // SDK builds the URL with port 80 but does a TLS handshake,
                // surfacing as "cURL error 35: SSL routines::packet length
                // too long" on every broadcast.
                : [
                    'cluster' => env('PUSHER_APP_CLUSTER', 'eu'),
                    'useTLS'  => true,
                ],
            'client_options' => [
                // Guzzle client options for webhook callbacks
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key'    => env('ABLY_KEY'),
        ],

        'redis' => [
            'driver'     => 'redis',
            'connection' => 'default',
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
