<?php

declare(strict_types=1);

/**
 * REVERB_HOST answers two different questions, and production needs two
 * different answers.
 *
 * The browser connects to the public hostname over wss on 443, through nginx.
 * PHP posts events to Reverb on loopback. Pointing the second at the first
 * sends the broadcast back out to the public domain, where nginx hands it to
 * Laravel — which has no /apps/{id}/events route and answers with its 404 page.
 * The Pusher client reports that HTML as the error, and the popup silently
 * stops working.
 */
it('lets the broadcaster be pointed somewhere other than the public host', function (): void {
    // Production's shape: public host for the browser, loopback for PHP.
    putenv('REVERB_HOST=crm.example.test');
    putenv('REVERB_PORT=443');
    putenv('REVERB_SCHEME=https');
    putenv('REVERB_BROADCAST_HOST=127.0.0.1');
    putenv('REVERB_BROADCAST_PORT=8080');
    putenv('REVERB_BROADCAST_SCHEME=http');

    $options = require config_path('broadcasting.php');
    $reverb = $options['connections']['reverb']['options'];

    expect($reverb['host'])->toBe('127.0.0.1')
        ->and($reverb['port'])->toBe('8080')
        ->and($reverb['scheme'])->toBe('http')
        // TLS has to follow the broadcast scheme, not the public one, or the
        // client negotiates https against a plaintext loopback listener.
        ->and($reverb['useTLS'])->toBeFalse();
});

/**
 * A machine where both answers really are the same must need no extra
 * configuration, or every developer has to learn this distinction to run the
 * popup locally.
 */
it('falls back to the shared host when no separate one is given', function (): void {
    putenv('REVERB_HOST=localhost');
    putenv('REVERB_PORT=8080');
    putenv('REVERB_SCHEME=http');
    putenv('REVERB_BROADCAST_HOST');
    putenv('REVERB_BROADCAST_PORT');
    putenv('REVERB_BROADCAST_SCHEME');

    $options = require config_path('broadcasting.php');
    $reverb = $options['connections']['reverb']['options'];

    expect($reverb['host'])->toBe('localhost')
        ->and($reverb['port'])->toBe('8080')
        ->and($reverb['scheme'])->toBe('http');
});
