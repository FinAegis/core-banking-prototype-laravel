# Wave 0A — Queue-Worker Coverage Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ensure every queue application code dispatches to has a consuming worker in production, and add a CI guard so it can never silently regress — fixing the live defect where transaction/security push notifications, real-time broadcasts, outbound webhooks, and fraud scans queue forever with no worker.

**Architecture:** Introduce a canonical `config('queue.managed_queues')` list as the single source of truth. A Pest guard test (mirroring `tests/Feature/Compliance/GdprCoverageGuardTest.php`) asserts (a) no code dispatches to a queue missing from that list, and (b) `etc/supervisor.conf` (the live production runner) has a worker for each managed queue. Then fix `etc/supervisor.conf` (add the missing workers) and `config/horizon.php` (parity, in case the runner ever switches to Horizon).

**Tech Stack:** PHP 8.4, Laravel 12, Laravel Horizon, Pest, Supervisor.

## Global Constraints

- No float money anywhere (bcmath) — N/A to this PR (config/tests only), but never introduce it.
- Commits: `fix:` / `test:` / `docs:` prefix + `Co-Authored-By: Claude <noreply@anthropic.com>`.
- Code style: `./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php` before commit.
- Static analysis: `XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G` must stay clean (Level 8).
- Branch: continue on `wave-0-production-readiness` (already holds the spec + this plan). This becomes the Wave-0A PR. Later items (0B/0C/0D) branch fresh off `main` after 0A merges.
- Authoritative live worker config is `etc/supervisor.conf` (owner runs Supervisor; the Helm/Horizon path is NOT deployed).
- Canonical queue set (verified 2026-07-04 — the only queues application code dispatches to): `default, events, ledger, transactions, liquidity_pools, mobile, broadcasts, webhooks, fraud-batch, exchange, proofs`. (`transfers` has no producer — deliberately excluded.)

---

### Task 1: Canonical `managed_queues` + failing coverage guard

**Files:**
- Modify: `config/queue.php` (add `managed_queues` before the closing `];`, after the `failed` block ~line 110)
- Create: `tests/Feature/Ops/QueueCoverageTest.php`

**Interfaces:**
- Produces: `config('queue.managed_queues')` → `array<int,string>` of canonical queue names, consumed by the guard test and available to any future config.

- [ ] **Step 1: Add the `managed_queues` config block**

In `config/queue.php`, immediately before the final `];`, add:

```php
    /*
    |--------------------------------------------------------------------------
    | Managed Queues (worker-coverage source of truth)
    |--------------------------------------------------------------------------
    |
    | Every queue application code dispatches to. tests/Feature/Ops/
    | QueueCoverageTest.php asserts (a) no code dispatches to a queue missing
    | from this list, and (b) etc/supervisor.conf has a worker for each entry.
    | When you add a new ->onQueue('x') or a $queue = 'x' on a ShouldQueue
    | class, add 'x' here AND add a worker in etc/supervisor.conf (and
    | config/horizon.php for parity).
    |
    */

    'managed_queues' => [
        'default',
        'events',
        'ledger',
        'transactions',
        'liquidity_pools',
        'mobile',
        'broadcasts',
        'webhooks',
        'fraud-batch',
        'exchange',
        'proofs',
    ],
```

- [ ] **Step 2: Write the failing guard test**

Create `tests/Feature/Ops/QueueCoverageTest.php`:

```php
<?php

declare(strict_types=1);

/**
 * Queue-worker coverage guard (mirrors tests/Feature/Compliance/GdprCoverageGuardTest.php).
 *
 * Every queue application code dispatches to (via ->onQueue('x') or a
 * $queue = 'x' property on a ShouldQueue listener/job) MUST (1) be declared
 * in config('queue.managed_queues') and (2) have a consuming worker in
 * etc/supervisor.conf — the production runner. Catches the class of bug where
 * a new listener queues to a name no worker consumes (e.g. push notifications
 * on the 'mobile' queue silently never delivered).
 */

/** @return array<string,string> queue name => first file that dispatches to it */
function dispatchedQueues(): array
{
    $appDir = dirname(__DIR__, 3) . '/app';
    $found = [];
    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($appDir, FilesystemIterator::SKIP_DOTS)
    );
    /** @var SplFileInfo $file */
    foreach ($rii as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $code = (string) file_get_contents($file->getPathname());
        if (preg_match_all('/onQueue\(\s*[\'"]([a-z0-9_\-]+)[\'"]\s*\)/i', $code, $m)) {
            foreach ($m[1] as $q) {
                $found[$q] ??= $file->getPathname();
            }
        }
        if (preg_match_all('/\$queue\s*=\s*[\'"]([a-z0-9_\-]+)[\'"]/', $code, $m)) {
            foreach ($m[1] as $q) {
                $found[$q] ??= $file->getPathname();
            }
        }
    }

    return $found;
}

/** @return array<int,string> queue names consumed by etc/supervisor.conf */
function supervisorConsumedQueues(): array
{
    $conf = (string) file_get_contents(dirname(__DIR__, 3) . '/etc/supervisor.conf');
    $queues = [];
    foreach (preg_split('/\R/', $conf) ?: [] as $line) {
        if (! str_contains($line, 'queue:work')) {
            continue;
        }
        if (preg_match('/--queue=([a-z0-9_,\-]+)/i', $line, $m)) {
            foreach (explode(',', $m[1]) as $q) {
                $queues[] = $q;
            }
        } else {
            $queues[] = 'default'; // bare `queue:work` consumes 'default'
        }
    }

    return array_values(array_unique($queues));
}

it('declares every dispatched queue in config queue.managed_queues', function () {
    /** @var array<int,string> $managed */
    $managed = config('queue.managed_queues');
    expect($managed)->toBeArray()->not->toBeEmpty();

    $undeclared = array_keys(array_filter(
        dispatchedQueues(),
        fn (string $file, string $queue) => ! in_array($queue, $managed, true),
        ARRAY_FILTER_USE_BOTH
    ));

    if ($undeclared !== []) {
        $this->fail('Queues dispatched to but missing from config(queue.managed_queues): ' . implode(', ', $undeclared));
    }
    expect($undeclared)->toBe([]);
});

it('has a supervisor worker for every managed queue', function () {
    /** @var array<int,string> $managed */
    $managed  = config('queue.managed_queues');
    $consumed = supervisorConsumedQueues();

    $uncovered = array_values(array_diff($managed, $consumed));

    if ($uncovered !== []) {
        $this->fail('Managed queues with NO worker in etc/supervisor.conf (jobs queue forever): ' . implode(', ', $uncovered));
    }
    expect($uncovered)->toBe([]);
});
```

- [ ] **Step 3: Run the guard — verify it FAILS on the supervisor gap**

Run: `./vendor/bin/pest tests/Feature/Ops/QueueCoverageTest.php`
Expected: the first test PASSES (managed_queues covers all dispatched queues); the second test FAILS with
`Managed queues with NO worker in etc/supervisor.conf (jobs queue forever): mobile, broadcasts, webhooks, fraud-batch, exchange, proofs`

- [ ] **Step 4: Commit the source-of-truth + failing guard**

```bash
git add config/queue.php tests/Feature/Ops/QueueCoverageTest.php
git commit -m "test: queue-worker coverage guard + managed_queues source of truth

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 2: Add the missing Supervisor workers (makes the live path correct)

**Files:**
- Modify: `etc/supervisor.conf` (add 2 program blocks; extend the `[group:finaegis-workers]` list)

**Interfaces:**
- Consumes: the queue names from Task 1's `managed_queues`.
- Produces: Supervisor programs consuming `mobile` (dedicated) and `broadcasts,webhooks,fraud-batch,exchange,proofs` (combined).

- [ ] **Step 1: Append two worker programs**

In `etc/supervisor.conf`, after the `[program:finaegis-liquidity-pools-worker]` block (before `[group:finaegis-workers]`), insert:

```ini
[program:finaegis-mobile-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/yozaz/www/finaegis/core-banking-prototype-laravel/artisan queue:work --queue=mobile --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=yozaz
numprocs=2
redirect_stderr=true
stdout_logfile=/home/yozaz/www/finaegis/core-banking-prototype-laravel/storage/logs/mobile-worker.log
stopwaitsecs=3600

[program:finaegis-aux-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/yozaz/www/finaegis/core-banking-prototype-laravel/artisan queue:work --queue=broadcasts,webhooks,fraud-batch,exchange,proofs --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=yozaz
numprocs=2
redirect_stderr=true
stdout_logfile=/home/yozaz/www/finaegis/core-banking-prototype-laravel/storage/logs/aux-worker.log
stopwaitsecs=3600
```

- [ ] **Step 2: Extend the worker group**

Replace the `[group:finaegis-workers]` `programs=` line with:

```ini
programs=finaegis-events-worker,finaegis-ledger-worker,finaegis-transactions-worker,finaegis-default-worker,finaegis-liquidity-pools-worker,finaegis-mobile-worker,finaegis-aux-worker
```

- [ ] **Step 3: Run the guard — verify it PASSES**

Run: `./vendor/bin/pest tests/Feature/Ops/QueueCoverageTest.php`
Expected: both tests PASS (every managed queue now has a supervisor worker).

- [ ] **Step 4: Commit**

```bash
git add etc/supervisor.conf
git commit -m "fix: consume mobile/broadcasts/webhooks/fraud-batch/exchange/proofs queues

Adds Supervisor workers for the six queues that had no consumer, restoring
push notifications, real-time broadcasts, outbound webhooks, and fraud scans.

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 3: Fix `config/horizon.php` for parity (no live impact, prevents future footgun)

**Files:**
- Modify: `config/queue.php` — N/A. `config/horizon.php` production + local `environments`.

**Interfaces:**
- Consumes: the managed queue set. Produces: Horizon supervisors that cover the same set, so a future switch to `php artisan horizon` is already correct.

- [ ] **Step 1: Replace the null-bound ES supervisor + widen supervisor-1 (production block)**

In `config/horizon.php`, in `environments.production`, change `event-sourcing-supervisor-1`'s `queue` from `[env('EVENT_PROJECTOR_QUEUE_NAME')]` to the explicit list, and change `supervisor-1` to name its queues (it currently inherits `['default']` from defaults):

```php
        'production' => [
            'supervisor-1' => [
                'connection'      => 'redis',
                'queue'           => ['default', 'webhooks', 'fraud-batch', 'exchange', 'proofs'],
                'maxProcesses'    => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'event-sourcing-supervisor-1' => [
                'connection' => 'redis',
                'queue'      => ['events', 'ledger', 'transactions', 'liquidity_pools'],
                'balance'    => 'simple',
                'processes'  => 1,
                'tries'      => 3,
            ],
```

(Leave `broadcasts-supervisor` and `mobile-supervisor` unchanged — they already cover `broadcasts` and `mobile`.)

- [ ] **Step 2: Mirror the ES fix in the local block**

In `environments.local`, change `event-sourcing-supervisor-1`'s `queue` from `[env('EVENT_PROJECTOR_QUEUE_NAME')]` to `['events', 'ledger', 'transactions', 'liquidity_pools']`.

- [ ] **Step 3: Verify config still loads + guard still green**

Run: `php artisan config:clear && php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo json_encode(config('horizon.environments.production.event-sourcing-supervisor-1.queue'));"`
Expected: `["events","ledger","transactions","liquidity_pools"]`
Run: `./vendor/bin/pest tests/Feature/Ops/QueueCoverageTest.php`
Expected: both tests PASS.

- [ ] **Step 4: Commit**

```bash
git add config/horizon.php
git commit -m "fix: config/horizon.php parity — explicit ES queues, no null-bound supervisor

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 4: Docs — deploy notes, checklist, ops:verify-env manual step

**Files:**
- Modify: `docs/10-OPERATIONS/PRODUCTION_READINESS_CHECKLIST.md` (§8 queues)
- Modify: `docs/superpowers/specs/2026-07-04-wave-0-production-readiness-blockers-design.md` (mark 0A done — optional)

- [ ] **Step 1: Update the readiness checklist §8**

In `docs/10-OPERATIONS/PRODUCTION_READINESS_CHECKLIST.md`, replace the Horizon line under "## 8. Observability & alerting" with:

```markdown
- [x] Queue workers cover **every** managed queue (`config/queue.php` `managed_queues`), enforced by `tests/Feature/Ops/QueueCoverageTest.php`. Production runs Supervisor off `etc/supervisor.conf` — **after deploying this config you MUST run on the server:** `supervisorctl reread && supervisorctl update && supervisorctl restart all`, then confirm with `supervisorctl status` that `finaegis-mobile-worker` and `finaegis-aux-worker` are RUNNING. A repo edit alone does not restart workers.
- [ ] Run `php artisan ops:verify-env` as a manual deploy step after `git pull`, before `migrate` (deploy is manual `git pull` + artisan steps; the `deploy.yml` gate is not in the live path, so env guards only fire when run).
```

- [ ] **Step 2: Commit**

```bash
git add docs/10-OPERATIONS/PRODUCTION_READINESS_CHECKLIST.md
git commit -m "docs: queue-coverage checklist + supervisorctl apply step + ops:verify-env manual gate

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 5: Full gate, post-phase-review, PR

- [ ] **Step 1: Run the full local quality gate**

```bash
./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php
XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G
./vendor/bin/pest tests/Feature/Ops/QueueCoverageTest.php
```
Expected: cs-fixer clean, PHPStan no new errors, guard green. Commit any cs-fixer changes.

- [ ] **Step 2: Run post-phase-review** (project workflow requirement before a phase PR). Address any critical/important findings.

- [ ] **Step 3: Push and open the PR**

```bash
git push -u origin wave-0-production-readiness
gh pr create --title "Wave 0A: queue-worker coverage — restore push/webhooks/fraud + CI guard" \
  --body "$(cat <<'EOF'
## Wave 0A — queue-worker coverage (live defect)

Production Supervisor consumed only events/ledger/transactions/default/liquidity_pools, so `mobile` (transaction + security-alert push), `broadcasts`, `webhooks`, `fraud-batch`, `exchange`, and `proofs` jobs queued forever with no worker. Fixes that and guards against regression.

- `config/queue.php`: `managed_queues` source of truth
- `tests/Feature/Ops/QueueCoverageTest.php`: fails if any dispatched queue lacks a managed entry or a supervisor worker
- `etc/supervisor.conf`: dedicated `mobile` worker + combined `aux` worker
- `config/horizon.php`: parity (explicit ES queues; no null-bound supervisor)

**Deploy step (manual):** on the server `supervisorctl reread && supervisorctl update && supervisorctl restart all`, then `supervisorctl status`.

Spec: docs/superpowers/specs/2026-07-04-wave-0-production-readiness-blockers-design.md
Plan: docs/superpowers/plans/2026-07-04-wave-0a-queue-worker-coverage.md

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

- [ ] **Step 4: Watch CI green, then merge** (`gh pr checks <n> --watch`; require total ≥ 15 before trusting — known false-green race). Merge when green.

## Self-Review

**Spec coverage:** 0A spec section → Tasks 1-4 (managed_queues + guard = spec approach step 1&5; supervisor.conf = step 2; horizon parity = step 4; server apply + ops:verify-env note = docs). ✅ Covered. `transfers` correctly excluded (no producer). Path-parameterisation dropped from 0A scope (YAGNI — existing workers already use the path; noted as follow-up).

**Placeholder scan:** No TBD/TODO; all code shown in full; exact commands + expected output present. ✅

**Type consistency:** `dispatchedQueues(): array<string,string>`, `supervisorConsumedQueues(): array<int,string>`, `config('queue.managed_queues'): array<int,string>` — used consistently across both tests. ✅

## Execution notes (deviations from the drafted plan)

Two corrections found while verifying against real code before committing:

1. **`transfers` is a real uncovered money queue.** The plan draft said `transfers` had "no producer" — wrong. `MoneyTransferred`, `AssetTransferred`, and `TransferThresholdReached` all set `$queue = EventQueues::TRANSFERS->value` (`app/Values/EventQueues.php`). The initial regex missed it because the queue is an **enum reference, not a quoted literal**. So `transfers` was added to `managed_queues` and given its own dedicated Supervisor worker (money-projection isolation). The true uncovered set was **7** queues, not 6.
2. **The guard is enum-aware.** `dispatchedQueues()` seeds from `EventQueues::cases()` (the event-sourcing source of truth) in addition to scanning quoted `onQueue('x')`/`$queue = 'x'` literals — otherwise any future ES event on a new enum case would escape the guard.
3. PHPStan: `expect(...)->toBeArray()->not->toBeEmpty()` trips the Pest generics extension; split into `->toBeArray()` + `->toContain('default')`.
