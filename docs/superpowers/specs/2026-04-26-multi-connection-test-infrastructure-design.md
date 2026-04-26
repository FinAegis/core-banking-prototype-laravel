# Multi-Connection Test Infrastructure — Design

**Status:** approved (pending implementation)
**Author:** Marijus Planciunas
**Date:** 2026-04-26
**Related:** PR #960 (the production bug this prevents recurring)

## Goal

Build test infrastructure that catches multi-connection (multi-MySQL-session) failure modes — the class of bugs that production hit on 2026-04-26 (`Lock wait timeout exceeded` on a `cardholders` insert from `AccountProvisioningService::apply()`).

## Why this is needed

The `App\Domain\Shared\Traits\UsesTenantConnection` trait deliberately collapses the `tenant` connection to the default connection whenever `APP_ENV === 'testing'`. The trait's own docblock names the failure mode it is hiding:

```php
// MySQL: Separate connections can cause lock wait timeouts with transactions
```

This collapse is the reason the deadlock never appeared in CI:

- **Production topology:** `users` → default connection (one MySQL session). `cardholders`, `cards` → `tenant` connection (separate MySQL session, even when pointed at the same physical database).
- **Test topology (today):** `users`, `cardholders`, `cards` → all default connection. Single session. Same in-process transaction. No way to deadlock against yourself across sessions.

The bug shipped because every test ran a topology that does not exist in production. CI already has MySQL available for Feature/Integration jobs — the gap is structural, not infrastructural.

## Non-goals

- Changing the production tenant topology.
- Removing the `UsesTenantConnection` testing carve-out for the existing test suite — that has hundreds of dependent tests; an audit is out of scope here.
- Comprehensive coverage across all ~20 `UsesTenantConnection` models in v1. Framework first, breadth later.

## Design

### Component overview

```
tests/
  MultiConnection/                       # new testsuite
    MultiConnectionTestCase.php          # base class (forces real tenant connection + truncation)
    Concerns/
      TruncatesAllConnections.php        # cleanup trait
      SkipsWithoutMySQL.php              # local-dev skip
    AccountProvisioning/
      AccountProvisioningServiceMultiConnectionTest.php   # tier 1 regression
    Framework/
      ConnectionTopologyTest.php         # self-check: confirms tenant ≠ default
```

### 1. Trait escape hatch

Add one config-driven branch to `UsesTenantConnection::shouldUseDefaultConnection()`:

```php
protected function shouldUseDefaultConnection(): bool
{
    if (Config::get('database.force_real_tenant_connection') === true) {
        return false;
    }
    return Config::get('app.env') === 'testing';
}
```

Default: `false`. The base class flips it on in `setUp`. Laravel resets `config()` between tests, so no manual cleanup is needed.

This is intentionally one extra `if` — discoverable in a grep, easy to delete if we ever fully remove the carve-out.

### 2. Base class: `MultiConnectionTestCase`

Responsibilities:

1. **Hard-require MySQL.** If `DB_CONNECTION !== 'mysql'` (or the connection is unreachable), `markTestSkipped` with a clear reason — preserves local dev workflow on machines without MySQL.
2. **Set `database.force_real_tenant_connection = true`** so the trait stops collapsing. The existing `tenant` connection in `config/database.php` already points at the same env-driven host/database as `mysql`, so no additional config rewriting is needed — separate PDO instances on the same DB is exactly the production topology.
3. **Force fresh PDO instances** with `DB::purge('mysql')` and `DB::purge('tenant')` so the two connections are independent MySQL sessions for the lifetime of the test.
4. **Truncate all touched tables** between tests instead of `RefreshDatabase`. `RefreshDatabase` wraps the default connection in a transaction that the tenant connection cannot see — wrong tool for this job.

Sketch:

```php
abstract class MultiConnectionTestCase extends BaseTestCase
{
    use CreatesApplication;
    use TruncatesAllConnections;
    use SkipsWithoutMySQL;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipIfMySQLUnavailable();

        Config::set('database.force_real_tenant_connection', true);

        // Force fresh PDO instances so the two connections are separate sessions.
        DB::purge('mysql');
        DB::purge('tenant');

        $this->truncateAllConnections();
    }

    protected function tearDown(): void
    {
        $this->truncateAllConnections();
        parent::tearDown();
    }
}
```

### 3. `TruncatesAllConnections` cleanup

Why truncation, not transactions:

| Strategy | Cross-session FK visibility | Speed | Verdict |
|---|---|---|---|
| `RefreshDatabase` (one transaction on default) | Broken — tenant can't see uncommitted users | Fast | Wrong: collapses the topology we're trying to test |
| Per-connection transactions | Broken — same reason | Fast | Wrong, same problem |
| `migrate:fresh` per test | OK | 5–15s/test | Too slow |
| **Truncation per test** | **OK — both connections see committed writes during test** | **~50–200ms/test** | **Chosen** |

Implementation:

- One-time bootstrap: `migrate:fresh` runs once per test process (Pest fixture/`beforeAll`).
- Per-test cleanup: `TRUNCATE` only the tables that the test actually touched, plus a known set of "always touched" tables (users, account_flags, cardholders, cards, etc.).
- `SET FOREIGN_KEY_CHECKS=0` around truncation — required because we're truncating across FK boundaries.
- Roles/permissions tables (`spatie/laravel-permission`) excluded from truncation so `createRoles()` from the existing `TestCase` pattern can be called once per process.

Open implementation detail (resolved during build): exact list of "always truncate" tables. Start with the union of tables hit by Tier 1+2 tests, document and grow.

### 4. CI integration

New job in `.github/workflows/03-test-suite.yml`, modeled on the existing `integration-tests` job:

```yaml
multi-connection-tests:
  name: Multi-Connection Tests
  runs-on: ubuntu-latest
  timeout-minutes: 15
  services:
    mysql: { image: mysql:8.0, ... }
    redis: { image: redis:7-alpine, ... }
  steps:
    - ... (standard setup) ...
    - name: Run Multi-Connection Tests
      env:
        DB_CONNECTION: mysql
        DB_HOST: 127.0.0.1
        ...
      run: php -d memory_limit=2G ./vendor/bin/pest --testsuite=MultiConnection
```

- Required check on PRs (branch protection rule, separate from existing checks).
- New testsuite added to `phpunit.xml`:
  ```xml
  <testsuite name="MultiConnection">
      <directory>tests/MultiConnection</directory>
  </testsuite>
  ```

### 5. Local dev

`SkipsWithoutMySQL`:

```php
protected function skipIfMySQLUnavailable(): void
{
    if (Config::get('database.default') !== 'mysql') {
        $this->markTestSkipped('Multi-connection tests require MySQL.');
    }
    try {
        DB::connection('mysql')->getPdo();
    } catch (\Throwable $e) {
        $this->markTestSkipped('MySQL not reachable: ' . $e->getMessage());
    }
}
```

`pest --parallel` on a machine without MySQL stays green by skipping. CI is the source of truth.

## Initial test coverage (v1)

### Tier 1 — must ship

1. **`Framework/ConnectionTopologyTest`** — self-check. Asserts that inside the base class:
   - `User::create()` writes via the default connection.
   - `Cardholder::create()` writes via the `tenant` connection.
   - The two connections have distinct PDO instances (`!==`).
   - A row inserted on default but not committed is visible to the default connection (same session) but invisible to the tenant connection (different session).

   Without this, a future regression that re-collapses the connections silently neuters every other test in the suite.

2. **`AccountProvisioning/AccountProvisioningServiceMultiConnectionTest`** — exercises `apply()` with the real reviewer profile end-to-end. Confirms the no-wrapping-transaction fix works under realistic multi-session topology. Pair this with a "guarded regression" test: re-introduce a wrapping `DB::transaction` via reflection or a test-only subclass and assert it deadlocks (proves the test would catch the regression).

### Tier 2 — ship if ≤30 min wiring

3. A direct card-issuance flow test (without going through `AccountProvisioningService`) — covers the same multi-connection write pattern via a different entry point. Skip if it requires significant scaffolding.

### Tier 3 — defer

Lending, AgentProtocol, Performance flows. Add as bugs surface or as part of those domains' own work. The framework makes it cheap to add later.

## Risks and mitigations

| Risk | Mitigation |
|---|---|
| The "always truncate" table list drifts as new models are added | Document in `MultiConnectionTestCase` docblock; add a CI lint step that warns when a new `UsesTenantConnection` model lacks coverage (deferred to follow-up) |
| Truncation cost grows with more tests | Acceptable up to ~50 tests in this suite. Beyond that, switch to per-test ephemeral DBs |
| The trait escape hatch gets misused outside `tests/MultiConnection/` | Keep the config key documented; its only legitimate caller is `MultiConnectionTestCase::setUp` |
| Self-check test ironically passes when broken | The self-check explicitly asserts PDO instance inequality (`!==`), which is the bypass-resistant invariant — collapse and you fail this test |

## Decisions log

- **Test class scope**: B (new test category) — chosen over A (single regression test) and C (suite-wide promotion). Surgical, doesn't blow up existing tests.
- **Isolation strategy**: #3 truncation — chosen over migrations (slow), per-connection transactions (defeats the purpose), and ephemeral DBs (overkill).
- **Trait override mechanism**: A (config flag) — chosen over a static trait flag (footgun: requires manual reset).
- **Coverage scope**: Tier 1 + Tier 2 if cheap.
- **CI placement**: B (new dedicated job, required check) — chosen over reusing Integration (pollution risk) and slow-tests (PRs would slip through).
- **Local dev**: graceful skip when MySQL unreachable.

## Implementation handoff

Implementation plan to be drafted via `superpowers:writing-plans` next.
