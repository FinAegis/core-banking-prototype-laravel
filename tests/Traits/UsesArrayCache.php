<?php

declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Contracts\Console\Kernel;

/**
 * Trait for tests that need array cache driver.
 *
 * This trait overrides createApplication to set cache and session
 * drivers to 'array' before the application is fully bootstrapped.
 * This is necessary for tests that run without Redis.
 */
trait UsesArrayCache
{
    /**
     * Creates the application with array cache/session config.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__ . '/../../bootstrap/app.php';

        // Set cache and session config BEFORE bootstrapping
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('permission.cache.store', 'array');

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
