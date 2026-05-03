<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the Pusher broadcasting config shape.
 *
 * The pusher/pusher-php-server 7.x SDK has a longstanding bug where
 * passing `scheme` / `encrypted` / a stray `port` alongside cluster
 * mode causes it to build the URL with port 80 and do a TLS handshake
 * — surfacing as "cURL error 35: SSL routines::packet length too long"
 * on every broadcast (real production failure, April 4 2026).
 *
 * The config must produce a clean cluster-mode shape when PUSHER_HOST
 * is absent, and a complete self-hosted shape when it's present. These
 * tests guard the difference.
 */
class BroadcastingPusherConfigTest extends TestCase
{
    /**
     * @param array<string, string> $env
     * @return array<string, mixed>
     */
    private function loadPusherConfig(array $env): array
    {
        $previous = [];
        foreach ($env as $key => $value) {
            $previous[$key] = $_ENV[$key] ?? null;
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }

        try {
            // Re-evaluate the config file in isolation
            $config = require __DIR__ . '/../../../config/broadcasting.php';

            return $config['connections']['pusher'];
        } finally {
            foreach ($previous as $key => $value) {
                if ($value === null) {
                    unset($_ENV[$key]);
                    putenv($key);
                } else {
                    $_ENV[$key] = $value;
                    putenv("{$key}={$value}");
                }
            }
        }
    }

    public function test_cluster_mode_config_omits_scheme_and_host(): void
    {
        $config = $this->loadPusherConfig([
            'PUSHER_APP_KEY'     => 'test-key',
            'PUSHER_APP_SECRET'  => 'test-secret',
            'PUSHER_APP_ID'      => 'test-id',
            'PUSHER_APP_CLUSTER' => 'eu',
            // PUSHER_HOST intentionally absent
        ]);

        $options = $config['options'];

        $this->assertSame('eu', $options['cluster']);
        $this->assertTrue($options['useTLS']);

        // Explicit absences — these are what triggered cURL error 35
        $this->assertArrayNotHasKey('scheme', $options);
        $this->assertArrayNotHasKey('encrypted', $options);
        $this->assertArrayNotHasKey('host', $options);
        $this->assertArrayNotHasKey('port', $options);
        $this->assertArrayNotHasKey('curl_options', $options);
    }

    public function test_self_hosted_mode_includes_host_port_scheme(): void
    {
        $config = $this->loadPusherConfig([
            'PUSHER_APP_KEY'     => 'test-key',
            'PUSHER_APP_SECRET'  => 'test-secret',
            'PUSHER_APP_ID'      => 'test-id',
            'PUSHER_APP_CLUSTER' => 'mt1',
            'PUSHER_HOST'        => 'soketi.internal',
            'PUSHER_PORT'        => '6001',
            'PUSHER_SCHEME'      => 'http',
        ]);

        $options = $config['options'];

        $this->assertSame('mt1', $options['cluster']);
        $this->assertSame('soketi.internal', $options['host']);
        $this->assertSame(6001, $options['port']);
        $this->assertSame('http', $options['scheme']);
        $this->assertFalse($options['useTLS']);
    }
}
