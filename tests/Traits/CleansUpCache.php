<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\Cache;

trait CleansUpCache
{
    /**
     * Clean up all cache data to prevent memory leaks in tests.
     */
    protected function cleanupCache(): void
    {
        Cache::flush();

        // Force garbage collection to free memory from cache objects
        gc_collect_cycles();
    }

    /**
     * Setup cache isolation for parallel tests.
     */
    protected function setUpCacheIsolation(): void
    {
        // This trait can be used by tests that need aggressive cache cleanup
        $this->beforeApplicationDestroyed(function () {
            $this->cleanupCache();
        });
    }
}
