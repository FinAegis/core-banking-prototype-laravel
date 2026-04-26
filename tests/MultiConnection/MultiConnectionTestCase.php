<?php

declare(strict_types=1);

namespace Tests\MultiConnection;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;
use Tests\MultiConnection\Concerns\SkipsWithoutMySQL;
use Tests\MultiConnection\Concerns\TruncatesAllConnections;

/**
 * Base class for tests that exercise the production multi-session topology.
 *
 * Production runs models with `UsesTenantConnection` on a separate MySQL
 * session from the default connection. The standard `Tests\TestCase` collapses
 * those connections to a single session under APP_ENV=testing — which masks
 * an entire class of multi-session bugs (see PR #960).
 *
 * This base class:
 *   1. Skips the test if MySQL is not reachable (local dev safety).
 *   2. Sets `database.force_real_tenant_connection = true` so the
 *      UsesTenantConnection trait stops collapsing.
 *   3. Purges both PDO instances so the two connections are independent
 *      MySQL sessions for the lifetime of the test.
 *   4. Truncates non-preserved tables before and after each test (we cannot
 *      use RefreshDatabase — its single-connection transaction is exactly
 *      what we are trying to test against).
 */
abstract class MultiConnectionTestCase extends BaseTestCase
{
    use CreatesApplication;
    use SkipsWithoutMySQL;
    use TruncatesAllConnections;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipIfMySQLUnavailable();

        Config::set('database.force_real_tenant_connection', true);

        DB::purge((string) Config::get('database.default'));
        DB::purge('tenant');

        $this->truncateAllConnections();
    }

    protected function tearDown(): void
    {
        if (Config::get('database.force_real_tenant_connection') === true) {
            // A failing test may leave a transaction open on either connection.
            // Open transactions hold metadata locks that block TRUNCATE for
            // 10s+, cascading the failure into every subsequent test's setUp.
            $this->rollBackOpenTransactions();
            $this->truncateAllConnections();
        }

        parent::tearDown();
    }

    private function rollBackOpenTransactions(): void
    {
        foreach ([(string) Config::get('database.default'), 'tenant'] as $connection) {
            while (DB::connection($connection)->transactionLevel() > 0) {
                DB::connection($connection)->rollBack();
            }
        }
    }
}
