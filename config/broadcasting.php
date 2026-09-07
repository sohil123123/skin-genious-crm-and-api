<?php

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
    | Supported: "reverb", "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over WebSockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            /*
             * Where PHP posts events to Reverb — which is not where the browser
             * connects to it.
             *
             * REVERB_HOST answers the browser's question: the public hostname,
             * 443, wss, reached through nginx. Using the same answer for this
             * connection sends the server's own broadcast back out to the
             * public domain, where nginx hands it to Laravel, which has no
             * /apps/{id}/events route and returns its 404 page. The Pusher
             * client then reports that HTML as the error, which is what
             * "Could not announce a call" was carrying.
             *
             * So this side gets its own variables, falling back to the shared
             * ones so a local machine — where both answers really are the same
             * — needs no extra configuration. In production set:
             *
             *     REVERB_BROADCAST_HOST=127.0.0.1
             *     REVERB_BROADCAST_PORT=8080
             *     REVERB_BROADCAST_SCHEME=http
             *
             * Loopback and plaintext are correct here: the request never leaves
             * the machine, and terminating TLS for it would mean Reverb holding
             * a certificate for a hop that has no network to be sniffed on.
             */
            'options' => [
                'host' => env('REVERB_BROADCAST_HOST', env('REVERB_HOST')),
                'port' => env('REVERB_BROADCAST_PORT', env('REVERB_PORT', 443)),
                'scheme' => env('REVERB_BROADCAST_SCHEME', env('REVERB_SCHEME', 'https')),
                'useTLS' => env('REVERB_BROADCAST_SCHEME', env('REVERB_SCHEME', 'https')) === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
