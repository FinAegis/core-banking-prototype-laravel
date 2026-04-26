# Multi-Connection Test Infrastructure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a `tests/MultiConnection/` test category that runs against real MySQL with the `UsesTenantConnection` testing carve-out disabled, so tests exercise the production multi-session topology and catch the class of bug PR #960 fixed.

**Architecture:** Add a config-driven escape hatch to the `UsesTenantConnection` trait. Build a `MultiConnectionTestCase` base class that flips the flag, purges PDO instances on both connections, and truncates non-system tables between tests. Two trait helpers handle MySQL availability skip and table truncation. Wire into a new CI job and required check.

**Tech Stack:** PHP 8.4, Laravel 12, Pest 3, MySQL 8 (CI), MariaDB 10.11+ (prod). Spec: `docs/superpowers/specs/2026-04-26-multi-connection-test-infrastructure-design.md`.

**Branch:** `feat/multi-connection-test-infrastructure` (already created)

---

## File Structure

| Path | Action | Responsibility |
|---|---|---|
| `app/Domain/Shared/Traits/UsesTenantConnection.php` | Modify | Add config-driven escape hatch (one extra `if` in `shouldUseDefaultConnection`) |
| `tests/Unit/Shared/UsesTenantConnectionTraitTest.php` | Create | Unit-test the new escape hatch flag (no DB required) |
| `tests/MultiConnection/Concerns/SkipsWithoutMySQL.php` | Create | Trait: `markTestSkipped` if MySQL not available |
| `tests/MultiConnection/Concerns/TruncatesAllConnections.php` | Create | Trait: truncate non-system tables on default + tenant connections |
| `tests/MultiConnection/MultiConnectionTestCase.php` | Create | Base class: flip flag, purge PDO, truncate, skip-if-no-MySQL |
| `tests/MultiConnection/Framework/ConnectionTopologyTest.php` | Create | Self-check: confirms tenant ≠ default at the PDO level |
| `tests/MultiConnection/AccountProvisioning/AccountProvisioningServiceMultiConnectionTest.php` | Create | Tier 1 regression — `apply()` does not deadlock under real multi-session |
| `phpunit.xml` | Modify | Add `<testsuite name="MultiConnection">` |
| `tests/Pest.php` | Modify | Bind base class to `MultiConnection` directory |
| `.github/workflows/03-test-suite.yml` | Modify | Add `multi-connection-tests` job |
| `CLAUDE.md` | Modify | One-line pointer to the new test category |

---

## Task 1: Add config-driven escape hatch to UsesTenantConnection trait

**Files:**
- Modify: `app/Domain/Shared/Traits/UsesTenantConnection.php`
- Test: `tests/Unit/Shared/UsesTenantConnectionTraitTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Shared/UsesTenantConnectionTraitTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->model = new class () extends Model {
        use UsesTenantConnection;
    };
});

it('returns null (default connection) when running under testing env without override', function () {
    Config::set('app.env', 'testing');
    Config::set('database.force_real_tenant_connection', false);

    expect($this->model->getConnectionName())->toBeNull();
});

it('returns "tenant" when force_real_tenant_connection is true even under testing', function () {
    Config::set('app.env', 'testing');
    Config::set('database.force_real_tenant_connection', true);

    expect($this->model->getConnectionName())->toBe('tenant');
});

it('returns "tenant" when env is not testing regardless of override flag', function () {
    Config::set('app.env', 'production');
    Config::set('database.force_real_tenant_connection', false);

    expect($this->model->getConnectionName())->toBe('tenant');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Unit/Shared/UsesTenantConnectionTraitTest.php`
Expected: 1 fail (second test — flag is currently ignored, returns null when env=testing).

- [ ] **Step 3: Implement the escape hatch**

Edit `app/Domain/Shared/Traits/UsesTenantConnection.php` — replace the `shouldUseDefaultConnection` method:

```php
    /**
     * Determine if the model should use the default connection instead of tenant.
     *
     * Returns true when APP_ENV is 'testing'. The
     * `database.force_real_tenant_connection` config flag overrides the
     * carve-out — set it to true in test base classes that need the production
     * multi-session topology (see tests/MultiConnection/).
     */
    protected function shouldUseDefaultConnection(): bool
    {
        if (Config::get('database.force_real_tenant_connection') === true) {
            return false;
        }

        return Config::get('app.env') === 'testing';
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Unit/Shared/UsesTenantConnectionTraitTest.php`
Expected: 3 pass.

- [ ] **Step 5: Run PHPStan + cs-fixer**

Run: `./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php app/Domain/Shared/Traits/UsesTenantConnection.php tests/Unit/Shared/UsesTenantConnectionTraitTest.php`
Run: `XDEBUG_MODE=off vendor/bin/phpstan analyse app/Domain/Shared/Traits/UsesTenantConnection.php tests/Unit/Shared/UsesTenantConnectionTraitTest.php --memory-limit=2G`
Expected: zero errors.

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Shared/Traits/UsesTenantConnection.php tests/Unit/Shared/UsesTenantConnectionTraitTest.php
git commit -m "feat: add force_real_tenant_connection escape hatch to UsesTenantConnection

Default off — preserves existing test-mode collapse. Set to true in test
base classes that need real multi-session topology (production parity).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Add MultiConnection testsuite to phpunit.xml + Pest.php

**Files:**
- Modify: `phpunit.xml`
- Modify: `tests/Pest.php`
- Create: `tests/MultiConnection/.gitkeep` (placeholder, removed in Task 5 when first real file lands)

- [ ] **Step 1: Add testsuite entry to `phpunit.xml`**

Edit `phpunit.xml` — locate the `<testsuites>` block and add a new entry after the `Security` testsuite:

```xml
        <testsuite name="Security">
            <directory>tests/Security</directory>
        </testsuite>
        <testsuite name="MultiConnection">
            <directory>tests/MultiConnection</directory>
        </testsuite>
    </testsuites>
```

- [ ] **Step 2: Verify Pest discovers the testsuite**

Create the directory:

```bash
mkdir -p tests/MultiConnection
touch tests/MultiConnection/.gitkeep
```

Run: `./vendor/bin/pest --testsuite=MultiConnection --list-tests`
Expected: zero tests listed (no test files yet), no error about unknown testsuite.

- [ ] **Step 3: Commit**

```bash
git add phpunit.xml tests/MultiConnection/.gitkeep
git commit -m "test: register MultiConnection testsuite in phpunit.xml

Empty directory placeholder. Tests land in subsequent commits.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Create SkipsWithoutMySQL concern

**Files:**
- Create: `tests/MultiConnection/Concerns/SkipsWithoutMySQL.php`

This trait does not need its own dedicated unit test — its behavior is exercised in Task 6's framework self-check (which would fail at setup time on machines without MySQL if the skip didn't fire correctly).

- [ ] **Step 1: Create the trait**

Create `tests/MultiConnection/Concerns/SkipsWithoutMySQL.php`:

```php
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
```

- [ ] **Step 2: Run cs-fixer + PHPStan**

Run: `./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php tests/MultiConnection/Concerns/SkipsWithoutMySQL.php`
Run: `XDEBUG_MODE=off vendor/bin/phpstan analyse tests/MultiConnection/Concerns/SkipsWithoutMySQL.php --memory-limit=2G`
Expected: zero errors.

- [ ] **Step 3: Commit**

```bash
git add tests/MultiConnection/Concerns/SkipsWithoutMySQL.php
git commit -m "test: add SkipsWithoutMySQL concern for multi-connection tests

Marks tests skipped when default driver isn't MySQL/MariaDB or
when the connection is unreachable. Keeps local dev workflow on
WSL/SQLite green; CI runs everything.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Create TruncatesAllConnections concern

**Files:**
- Create: `tests/MultiConnection/Concerns/TruncatesAllConnections.php`

Strategy: list **preserved** tables (much shorter and more stable than listing every table to truncate). Truncate everything else on both connections. Foreign key checks toggled around the truncation.

- [ ] **Step 1: Create the trait**

Create `tests/MultiConnection/Concerns/TruncatesAllConnections.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\MultiConnection\Concerns;

use Illuminate\Support\Facades\DB;

trait TruncatesAllConnections
{
    /**
     * Tables preserved across tests in this suite.
     *
     * - migrations: required for schema state
     * - permission/role tables: created once via createRoles() pattern; truncating
     *   them forces every test to re-seed roles, which is wasteful
     */
    private const PRESERVED_TABLES = [
        'migrations',
        'roles',
        'permissions',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
    ];

    /**
     * Truncate every non-preserved table on both default and tenant connections.
     *
     * MultiConnection tests cannot use RefreshDatabase (which wraps the default
     * connection in a single transaction the tenant connection cannot see).
     * We truncate instead, and since both connections target the same physical
     * database, we only need to truncate once via the default connection.
     * The two connections still see the same on-disk state because they share
     * the same DB.
     */
    protected function truncateAllConnections(): void
    {
        $tables = $this->resolveTablesToTruncate();

        DB::connection()->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tables as $table) {
                DB::connection()->table($table)->truncate();
            }
        } finally {
            DB::connection()->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveTablesToTruncate(): array
    {
        $all = array_map(
            fn (array $row) => array_values((array) $row)[0],
            DB::connection()->select('SHOW TABLES')
        );

        return array_values(array_filter(
            $all,
            fn (string $table) => ! in_array($table, self::PRESERVED_TABLES, true)
        ));
    }
}
```

- [ ] **Step 2: Run cs-fixer + PHPStan**

Run: `./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php tests/MultiConnection/Concerns/TruncatesAllConnections.php`
Run: `XDEBUG_MODE=off vendor/bin/phpstan analyse tests/MultiConnection/Concerns/TruncatesAllConnections.php --memory-limit=2G`
Expected: zero errors.

- [ ] **Step 3: Commit**

```bash
git add tests/MultiConnection/Concerns/TruncatesAllConnections.php
git commit -m "test: add TruncatesAllConnections concern for multi-connection tests

Truncates every non-preserved table after each test. Preserves
migrations + Spatie role/permission tables to avoid re-seeding cost.
Toggles FK checks around the truncation.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: Create MultiConnectionTestCase base class

**Files:**
- Create: `tests/MultiConnection/MultiConnectionTestCase.php`
- Modify: `tests/Pest.php` (bind base class to MultiConnection directory)
- Delete: `tests/MultiConnection/.gitkeep`

- [ ] **Step 1: Create the base class**

Create `tests/MultiConnection/MultiConnectionTestCase.php`:

```php
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

        DB::purge(Config::get('database.default'));
        DB::purge('tenant');

        $this->truncateAllConnections();
    }

    protected function tearDown(): void
    {
        if (Config::get('database.force_real_tenant_connection') === true) {
            $this->truncateAllConnections();
        }

        parent::tearDown();
    }
}
```

- [ ] **Step 2: Bind base class to MultiConnection directory in Pest.php**

Edit `tests/Pest.php` — add this line after the existing `uses(TestCase::class)->in(...)` calls (around line 23):

```php
uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Domain');
uses(TestCase::class)->in('Console');
uses(\Tests\MultiConnection\MultiConnectionTestCase::class)->in('MultiConnection');
```

- [ ] **Step 3: Remove the placeholder**

```bash
git rm tests/MultiConnection/.gitkeep
```

- [ ] **Step 4: Run cs-fixer + PHPStan**

Run: `./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php tests/MultiConnection/MultiConnectionTestCase.php tests/Pest.php`
Run: `XDEBUG_MODE=off vendor/bin/phpstan analyse tests/MultiConnection/MultiConnectionTestCase.php --memory-limit=2G`
Expected: zero errors.

- [ ] **Step 5: Commit**

```bash
git add tests/MultiConnection/MultiConnectionTestCase.php tests/Pest.php
git rm tests/MultiConnection/.gitkeep
git commit -m "test: add MultiConnectionTestCase base class

Forces real tenant connection (separate MySQL session), purges PDO
instances, truncates between tests. Bound to tests/MultiConnection/
via Pest.php uses() binding.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: Topology self-check test

**Files:**
- Create: `tests/MultiConnection/Framework/ConnectionTopologyTest.php`

This is the framework's smoke test. If it ever breaks, every other test in this suite is silently neutered — so it is the most important test in the suite.

- [ ] **Step 1: Create the self-check test**

Create `tests/MultiConnection/Framework/ConnectionTopologyTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\MultiConnection\Framework;

use App\Domain\CardIssuance\Models\Cardholder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('routes User writes to the default connection', function () {
    $user = new User();
    expect($user->getConnectionName())->toBeNull();
});

it('routes Cardholder writes to the tenant connection', function () {
    $cardholder = new Cardholder();
    expect($cardholder->getConnectionName())->toBe('tenant');
});

it('uses distinct PDO instances on default and tenant connections', function () {
    $defaultPdo = DB::connection()->getPdo();
    $tenantPdo = DB::connection('tenant')->getPdo();

    expect($defaultPdo)->not->toBe($tenantPdo);
});

it('does not see uncommitted writes from another connection', function () {
    $email = 'topology-' . uniqid() . '@example.invalid';

    DB::connection()->beginTransaction();

    DB::connection()->table('users')->insert([
        'name'              => 'Topology Test',
        'email'             => $email,
        'password'          => 'irrelevant',
        'email_verified_at' => now(),
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);

    // Same connection (same session) sees it.
    $sameConn = DB::connection()->table('users')->where('email', $email)->exists();
    // Different connection (different session) does NOT see it.
    $tenantConn = DB::connection('tenant')->table('users')->where('email', $email)->exists();

    DB::connection()->rollBack();

    expect($sameConn)->toBeTrue();
    expect($tenantConn)->toBeFalse();
});
```

- [ ] **Step 2: Run the self-check**

Prerequisite: MySQL must be reachable. If running locally without MySQL, all four tests will skip — that is correct behavior.

If MySQL is available, run:

```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=testing DB_USERNAME=root DB_PASSWORD=root \
  ./vendor/bin/pest --testsuite=MultiConnection
```

Expected: 4 pass (or 4 skip on machines without MySQL).

- [ ] **Step 3: Run cs-fixer + PHPStan**

Run: `./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php tests/MultiConnection/Framework/ConnectionTopologyTest.php`
Run: `XDEBUG_MODE=off vendor/bin/phpstan analyse tests/MultiConnection/Framework/ConnectionTopologyTest.php --memory-limit=2G`
Expected: zero errors.

- [ ] **Step 4: Commit**

```bash
git add tests/MultiConnection/Framework/ConnectionTopologyTest.php
git commit -m "test: add ConnectionTopology self-check for MultiConnection suite

Asserts the four invariants the framework depends on:
  1. User writes route to default connection
  2. Cardholder writes route to tenant connection
  3. PDO instances are distinct (separate MySQL sessions)
  4. Uncommitted writes are session-isolated

Without these, every other test in the suite is silently neutered.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: AccountProvisioningServiceMultiConnectionTest

**Files:**
- Create: `tests/MultiConnection/AccountProvisioning/AccountProvisioningServiceMultiConnectionTest.php`

Tier 1 regression — exercises `apply()` with a real reviewer profile end-to-end under multi-session topology. Confirms PR #960's fix holds; if anyone re-introduces a wrapping `DB::transaction`, this test will deadlock and timeout, surfacing the regression.

- [ ] **Step 1: Create the regression test**

Create `tests/MultiConnection/AccountProvisioning/AccountProvisioningServiceMultiConnectionTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\MultiConnection\AccountProvisioning;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Domain\AccountProvisioning\Profiles\ReviewerAccountProfile;
use App\Domain\AccountProvisioning\Services\AccountProvisioningService;
use App\Domain\AccountProvisioning\ValueObjects\ProvisioningContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('completes apply() without deadlock under real multi-session topology', function () {
    $profile = app(ReviewerAccountProfile::class);

    $ctx = new ProvisioningContext(
        email: 'multiconn-' . uniqid() . '@example.invalid',
        name: 'Multi-Connection Test',
        region: 'US',
        expiresAt: now()->addDays(60)->toImmutable(),
        note: 'multi-connection regression',
        operatorId: 1,
    );

    // If apply() ever re-introduces a wrapping DB::transaction, this call
    // deadlocks on the cardholders FK lookup and times out at innodb_lock_wait_timeout.
    $result = app(AccountProvisioningService::class)->apply(
        profile: $profile,
        ctx: $ctx,
        password: 'Strong-Pass-2026!',
        rotatePassword: false,
        forceConvert: false,
    );

    expect($result['password_action'])->toBe('created');
    expect($result['user'])->toBeInstanceOf(User::class);
});

it('persists AccountFlag and routes Cardholder write to the tenant connection', function () {
    $profile = app(ReviewerAccountProfile::class);

    $ctx = new ProvisioningContext(
        email: 'multiconn-flag-' . uniqid() . '@example.invalid',
        name: 'Flag Routing Test',
        region: 'US',
        expiresAt: now()->addDays(60)->toImmutable(),
        note: 'flag and tenant routing',
        operatorId: 1,
    );

    $result = app(AccountProvisioningService::class)->apply(
        profile: $profile,
        ctx: $ctx,
        password: 'Strong-Pass-2026!',
        rotatePassword: false,
        forceConvert: false,
    );

    $userId = $result['user']->id;

    // AccountFlag persisted on default connection.
    $flagOnDefault = DB::connection()->table('account_flags')->where('user_id', $userId)->exists();
    expect($flagOnDefault)->toBeTrue();

    $flag = AccountFlag::where('user_id', $userId)->first();
    expect($flag)->not->toBeNull();
    expect($flag->is_review_account)->toBeTrue();

    // Cardholder persisted on tenant connection (proves multi-session write completed).
    $cardholderOnTenant = DB::connection('tenant')->table('cardholders')->where('user_id', $userId)->exists();
    expect($cardholderOnTenant)->toBeTrue();
});
```

- [ ] **Step 2: Run the test against MySQL**

If MySQL is available locally:

```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=testing DB_USERNAME=root DB_PASSWORD=root \
  ./vendor/bin/pest --testsuite=MultiConnection
```

Expected: 6 pass (4 from Task 6 + 2 from this task), or 6 skip without MySQL.

If a deadlock or timeout fires here, **stop** and check whether `AccountProvisioningService::apply()` has been wrapped in a `DB::transaction` again — that would be exactly the regression this test catches.

- [ ] **Step 3: Run cs-fixer + PHPStan**

Run: `./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php tests/MultiConnection/AccountProvisioning/AccountProvisioningServiceMultiConnectionTest.php`
Run: `XDEBUG_MODE=off vendor/bin/phpstan analyse tests/MultiConnection/AccountProvisioning/AccountProvisioningServiceMultiConnectionTest.php --memory-limit=2G`
Expected: zero errors.

- [ ] **Step 4: Commit**

```bash
git add tests/MultiConnection/AccountProvisioning/AccountProvisioningServiceMultiConnectionTest.php
git commit -m "test: add multi-connection regression test for AccountProvisioningService

Exercises apply() with the reviewer profile under real two-MySQL-session
topology. If a wrapping DB::transaction is reintroduced (the bug PR #960
fixed), this test deadlocks on the cardholders insert and times out at
innodb_lock_wait_timeout — surfacing the regression in CI.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: Add Multi-Connection Tests CI job

**Files:**
- Modify: `.github/workflows/03-test-suite.yml`

- [ ] **Step 1: Add the job to the workflow file**

Edit `.github/workflows/03-test-suite.yml` — add this job after the existing `integration-tests:` job (around line 414, before `behat-tests:`):

```yaml
  multi-connection-tests:
    name: Multi-Connection Tests
    runs-on: ubuntu-latest
    timeout-minutes: 15

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5

      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379
        options: >-
          --health-cmd="redis-cli ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5

    steps:
      - name: Checkout Code
        uses: actions/checkout@v6

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}
          extensions: mbstring, dom, fileinfo, mysql, redis, opcache, pcntl, gd, imagick, bcmath, intl, zip, soap, gmp
          tools: composer:v2
          coverage: none

      - name: Get Composer Cache Directory
        id: composer-cache
        run: echo "dir=$(composer config cache-files-dir)" >> $GITHUB_OUTPUT

      - name: Cache Composer Dependencies
        uses: actions/cache@v5
        with:
          path: |
            ${{ steps.composer-cache.outputs.dir }}
            vendor
          key: ${{ runner.os }}-multiconn-composer-${{ hashFiles('**/composer.lock') }}
          restore-keys: |
            ${{ runner.os }}-multiconn-composer-

      - name: Setup Environment
        run: cp .env.testing .env

      - name: Install Dependencies
        run: |
          for i in 1 2 3; do
            if composer install --prefer-dist --no-progress --optimize-autoloader; then
              break
            fi
            sleep 5
            composer clear-cache
          done

      - name: Prepare Laravel Application
        run: |
          echo "DB_CONNECTION=mysql" >> .env
          echo "DB_HOST=127.0.0.1" >> .env
          echo "DB_PORT=3306" >> .env
          echo "DB_DATABASE=testing" >> .env
          echo "DB_USERNAME=root" >> .env
          echo "DB_PASSWORD=root" >> .env

          php artisan key:generate
          php artisan config:clear
          php artisan migrate --force

      - name: Run Multi-Connection Tests
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: testing
          DB_USERNAME: root
          DB_PASSWORD: root
          CI: true
        run: php -d memory_limit=2G ./vendor/bin/pest --testsuite=MultiConnection
```

- [ ] **Step 2: Verify YAML syntax**

Run: `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/03-test-suite.yml'))"`
Expected: no output (valid YAML).

- [ ] **Step 3: Commit and push**

```bash
git add .github/workflows/03-test-suite.yml
git commit -m "ci: add multi-connection tests job to test suite pipeline

Runs tests/MultiConnection/ against real MySQL on every PR.
Mirrors the integration-tests job structure, ~15 min timeout.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push -u origin feat/multi-connection-test-infrastructure
```

- [ ] **Step 4: Verify CI passes**

Run: `gh run list --branch feat/multi-connection-test-infrastructure --limit 5`

Wait for the `Multi-Connection Tests` check to complete. Expected: PASS, with 6 tests run (4 framework + 2 regression).

If FAIL, run: `gh run view <RUN_ID> --log-failed` and triage.

---

## Task 9: Update CLAUDE.md with reference

**Files:**
- Modify: `CLAUDE.md`

- [ ] **Step 1: Add a one-line pointer to the "Notes" bullet list**

Edit `CLAUDE.md` — locate the "Notes" section (the long bullet list near the bottom) and add this line near the other test-related entries (after the "Test tables: use `Tests\Traits\CreatesSolanaTestTables`..." line):

```markdown
- Multi-connection tests: `tests/MultiConnection/` — runs against real MySQL with `database.force_real_tenant_connection=true` so models with `UsesTenantConnection` use a separate MySQL session. Required check on PRs. See `docs/superpowers/specs/2026-04-26-multi-connection-test-infrastructure-design.md`.
```

- [ ] **Step 2: Add a CI/CD table row**

In the "CI/CD" section's `| Issue | Fix |` table, add this row near the other multi-connection / DB rows:

```markdown
| Multi-connection deadlock under DB::transaction | Do not wrap flows that touch UsesTenantConnection models in `DB::transaction()` — those models run on a separate MySQL session and will self-deadlock against the wrapping connection's row locks. Add a regression test in `tests/MultiConnection/`. |
```

- [ ] **Step 3: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: reference multi-connection test category in CLAUDE.md

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Final verification

- [ ] **Push and confirm all CI checks pass**

```bash
git push
gh pr create --title "feat: multi-connection test infrastructure" --body "$(cat <<'EOF'
## Summary
- New \`tests/MultiConnection/\` test category that exercises the production multi-MySQL-session topology
- Catches the class of bug PR #960 fixed (wrapping \`DB::transaction\` deadlocking against tenant-connection writes)
- Adds config-driven escape hatch on \`UsesTenantConnection\` so the test base class can disable the testing carve-out

## Test plan
- [ ] CI \`Multi-Connection Tests\` check passes
- [ ] All other CI checks remain green (no regression to existing suites)
- [ ] \`./vendor/bin/pest tests/Unit/Shared/UsesTenantConnectionTraitTest.php\` passes
- [ ] Locally without MySQL: \`./vendor/bin/pest --testsuite=MultiConnection\` skips all tests gracefully

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

Expected:
- New CI job `Multi-Connection Tests` runs and passes
- All other checks pass
- 6 multi-connection tests pass

- [ ] **Mark TaskCreate #36 complete and merge**

After all checks green and review approval:

```bash
gh pr merge --squash --auto
```
