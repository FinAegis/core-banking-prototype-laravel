<?php

declare(strict_types=1);

namespace Tests\MultiConnection\Concerns;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

trait SkipsWithoutMySQL
{
    /**
     * Skip the test if MySQL is not the configured default driver or not reachable.
     *
     * Multi-connection tests need real MySQL because they assert behavior
     * specific to InnoDB row-level locks and independent MySQL sessions.
     * On dev machines without MySQL (e.g., WSL), tests skip gracefully so
     * `pest --parallel` stays green. CI is the source of truth.
     */
    protected function skipIfMySQLUnavailable(): void
    {
        if (! Config::has('database.connections.tenant')) {
            $this->markTestSkipped('Multi-connection tests require a "tenant" connection in database config.');
        }

        $driver = Config::get('database.connections.' . Config::get('database.default') . '.driver');

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped(
                "Multi-connection tests require MySQL/MariaDB; default driver is '{$driver}'."
            );
        }

        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->markTestSkipped('MySQL not reachable: ' . $e->getMessage());
        }
    }
}
