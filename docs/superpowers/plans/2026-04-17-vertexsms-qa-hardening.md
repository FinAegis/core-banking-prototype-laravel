# VertexSMS Q&A Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Harden the SMS domain based on VertexSMS developer answers: add outbound throttle, client-side dedup, error-24 alerting, DLR reconciliation command, subscriber "landing" source fix, and draft the Q13 bidirectional MCP response.

**Architecture:** Targeted hardening of existing SMS domain — no new services or models. Adds a `getMessageStatus()` method to the existing client, a new artisan command for reconciliation, and surgical edits to `SmsService`, `VertexSmsClient`, `Subscriber` model, and `SubscriberResource`.

**Tech Stack:** PHP 8.4 / Laravel 12 / Pest / PHPStan Level 8 / bcmath

---

## File Map

| Action | File | Responsibility |
|--------|------|---------------|
| Modify | `app/Domain/SMS/Clients/VertexSmsClient.php` | Add `getMessageStatus()` for reconciliation; add 1/sec outbound throttle |
| Modify | `app/Domain/SMS/Services/SmsService.php` | Add dedup guard; add error-24 critical log |
| Create | `app/Console/Commands/SmsReconcileCommand.php` | Artisan `sms:reconcile` — backfill missed DLRs past expiration window |
| Modify | `app/Domain/Newsletter/Models/Subscriber.php` | Add `SOURCE_LANDING` constant |
| Modify | `app/Filament/Admin/Resources/SubscriberResource.php` | Add "Landing Page" to source options |
| Modify | `config/sms.php` | Add `sms.defaults.expire_seconds` (259200), `sms.throttle.send_per_second` (1) |
| Create | `tests/Feature/Domain/SMS/SmsServiceDedupTest.php` | Dedup guard tests |
| Create | `tests/Feature/Domain/SMS/SmsServiceDlrErrorTest.php` | Error-24 critical log test |
| Create | `tests/Feature/Domain/SMS/VertexSmsClientStatusTest.php` | `getMessageStatus()` tests |
| Create | `tests/Feature/Console/SmsReconcileCommandTest.php` | Reconciliation command tests |
| Modify | `tests/Feature/Domain/SMS/VertexSmsClientTest.php` | Add throttle test |

---

### Task 1: Outbound Send Throttle (Q6)

VertexSMS queues on their side, 1 SMS/sec per token. They said approximate limiting is fine. Add a `Cache::lock` throttle in the client so we don't flood their queue.

**Files:**
- Modify: `config/sms.php`
- Modify: `app/Domain/SMS/Clients/VertexSmsClient.php`
- Modify: `tests/Feature/Domain/SMS/VertexSmsClientTest.php`

- [ ] **Step 1: Add throttle config**

In `config/sms.php`, add to the `defaults` section:

```php
// Inside 'defaults' array, after 'test_mode':
'send_interval_ms' => (int) env('SMS_SEND_INTERVAL_MS', 1000),
```

- [ ] **Step 2: Write the failing test**

Append to `tests/Feature/Domain/SMS/VertexSmsClientTest.php`:

```php
describe('VertexSmsClient::sendSms throttle', function (): void {
    it('acquires a cache lock before sending', function (): void {
        config([
            'sms.defaults.send_interval_ms' => 1000,
            'cache.default'                 => 'array',
        ]);

        Http::fake([
            'kube-api.vertexsms.com/sms' => Http::response(['throttle-msg-1'], 200),
        ]);

        $client = new VertexSmsClient();
        $result = $client->sendSms('37069912345', 'Zelta', 'Throttle test');

        expect($result['message_id'])->toBe('throttle-msg-1');
        Http::assertSentCount(1);
    });
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `XDEBUG_MODE=off ./vendor/bin/pest tests/Feature/Domain/SMS/VertexSmsClientTest.php --filter="acquires a cache lock"`
Expected: PASS (current code doesn't break, but let's confirm baseline)

- [ ] **Step 4: Add throttle to `sendSms()`**

In `app/Domain/SMS/Clients/VertexSmsClient.php`, add import and modify `sendSms()`:

```php
// Add import at top:
use Illuminate\Support\Facades\Cache;

// In sendSms(), add right after $this->requireApiToken():
$intervalMs = (int) config('sms.defaults.send_interval_ms', 1000);
if ($intervalMs > 0) {
    $lockSeconds = (int) ceil($intervalMs / 1000);
    $lock = Cache::lock('sms:vertexsms:send-throttle', $lockSeconds);
    $lock->block($lockSeconds + 5); // wait up to lockSeconds+5s for the lock
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `XDEBUG_MODE=off ./vendor/bin/pest tests/Feature/Domain/SMS/VertexSmsClientTest.php`
Expected: All tests PASS

- [ ] **Step 6: Commit**

```bash
git add config/sms.php app/Domain/SMS/Clients/VertexSmsClient.php tests/Feature/Domain/SMS/VertexSmsClientTest.php
git commit -m "feat(sms): add 1/sec outbound send throttle per VertexSMS Q6 answer

Cache::lock prevents flooding VertexSMS queue beyond their per-token
1 SMS/sec limit. Configurable via SMS_SEND_INTERVAL_MS env var.

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 2: Client-Side Dedup Guard (Q10)

VertexSMS has no idempotency keys. If our HTTP call times out, we can't safely retry. Add a `Cache::add` dedup guard in `SmsService::send()` that prevents duplicate sends within a 60-second window.

**Files:**
- Modify: `app/Domain/SMS/Services/SmsService.php`
- Create: `tests/Feature/Domain/SMS/SmsServiceDedupTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Domain/SMS/SmsServiceDedupTest.php`:

```php
<?php

declare(strict_types=1);

use App\Domain\SMS\Clients\VertexSmsClient;
use App\Domain\SMS\Services\SmsPricingService;
use App\Domain\SMS\Services\SmsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'sms.enabled'                       => true,
        'sms.default_provider'              => 'vertexsms',
        'sms.defaults.test_mode'            => true,
        'sms.defaults.send_interval_ms'     => 0,
        'sms.providers.vertexsms.api_token' => 'test-token',
        'sms.providers.vertexsms.base_url'  => 'https://kube-api.vertexsms.com',
        'sms.webhook.dlr_url'               => 'https://example.com/dlr',
        'sms.webhook.dlr_url_token'         => '',
        'cache.default'                     => 'array',
    ]);
    Cache::flush();
});

describe('SmsService dedup guard', function (): void {
    it('rejects duplicate send within 60s window', function (): void {
        Http::fake([
            'kube-api.vertexsms.com/sms/cost' => Http::response([[
                'parts' => 1, 'countryISO' => 'LT', 'mccmnc' => '24601',
                'pricePerPart' => 0.035, 'totalPrice' => 0.035, 'currency' => 'EUR',
            ]], 200),
            'kube-api.vertexsms.com/sms' => Http::response(['dedup-msg-1'], 200),
        ]);

        $service = app(SmsService::class);

        // First send succeeds
        $result = $service->send('+37069912345', 'Zelta', 'Hello dedup');
        expect($result['message_id'])->toBe('dedup-msg-1');

        // Duplicate within 60s throws
        expect(fn () => $service->send('+37069912345', 'Zelta', 'Hello dedup'))
            ->toThrow(RuntimeException::class, 'Duplicate SMS detected');
    });

    it('allows same message to different recipients', function (): void {
        Http::fake([
            'kube-api.vertexsms.com/sms/cost' => Http::response([[
                'parts' => 1, 'countryISO' => 'LT', 'mccmnc' => '24601',
                'pricePerPart' => 0.035, 'totalPrice' => 0.035, 'currency' => 'EUR',
            ]], 200),
            'kube-api.vertexsms.com/sms' => Http::sequence()
                ->push(['msg-a'], 200)
                ->push(['msg-b'], 200),
        ]);

        $service = app(SmsService::class);

        $a = $service->send('+37069912345', 'Zelta', 'Same message');
        $b = $service->send('+37069900000', 'Zelta', 'Same message');

        expect($a['message_id'])->toBe('msg-a');
        expect($b['message_id'])->toBe('msg-b');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `XDEBUG_MODE=off ./vendor/bin/pest tests/Feature/Domain/SMS/SmsServiceDedupTest.php`
Expected: FAIL — "Duplicate SMS detected" not thrown

- [ ] **Step 3: Add dedup guard to `SmsService::send()`**

In `app/Domain/SMS/Services/SmsService.php`, add import and dedup check at the start of `send()`:

```php
// Add import:
use Illuminate\Support\Facades\Cache;
use RuntimeException;

// At the start of send(), before $testMode:
$dedupKey = 'sms:dedup:' . hash('sha256', $to . '|' . $from . '|' . $message);
if (! Cache::add($dedupKey, true, 60)) {
    Log::warning('SMS: Duplicate send blocked', ['to' => $to, 'from' => $from]);
    throw new RuntimeException('Duplicate SMS detected within 60-second window');
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `XDEBUG_MODE=off ./vendor/bin/pest tests/Feature/Domain/SMS/SmsServiceDedupTest.php`
Expected: All PASS

- [ ] **Step 5: Commit**

```bash
git add app/Domain/SMS/Services/SmsService.php tests/Feature/Domain/SMS/SmsServiceDedupTest.php
git commit -m "feat(sms): add client-side dedup guard per VertexSMS Q10 answer

VertexSMS has no idempotency keys. Cache::add prevents duplicate sends
for the same (to, from, message) tuple within a 60-second window,
protecting against timeout retries double-charging.

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 3: Error-24 Critical Alerting (Q8)

When VertexSMS test credit is exhausted, DLR arrives with `error=24` (Account balance limit reached). This is a billing event that needs critical-level logging.

**Files:**
- Modify: `app/Domain/SMS/Services/SmsService.php`
- Create: `tests/Feature/Domain/SMS/SmsServiceDlrErrorTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Domain/SMS/SmsServiceDlrErrorTest.php`:

```php
<?php

declare(strict_types=1);

use App\Domain\SMS\Models\SmsMessage;
use App\Domain\SMS\Services\SmsService;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    config(['cache.default' => 'array']);
});

describe('SmsService DLR error-24 alerting', function (): void {
    it('logs critical when error code 24 (balance exhausted) arrives', function (): void {
        Log::shouldReceive('critical')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'balance')
                    && ($context['error_code'] ?? null) === 24;
            });
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('debug')->andReturnNull();

        SmsMessage::create([
            'provider'     => 'vertexsms',
            'provider_id'  => 'err24-msg-001',
            'to'           => '+37069912345',
            'from'         => 'Zelta',
            'message'      => 'Balance test',
            'parts'        => 1,
            'status'       => SmsMessage::STATUS_SENT,
            'price_usdc'   => '48000',
            'country_code' => 'LT',
            'test_mode'    => true,
        ]);

        app(SmsService::class)->handleDeliveryReport([
            'message_id' => 'err24-msg-001',
            'raw_status' => 2,
            'error_code' => 24,
        ]);
    });

    it('does not log critical for other error codes', function (): void {
        Log::shouldReceive('critical')->never();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('debug')->andReturnNull();

        SmsMessage::create([
            'provider'     => 'vertexsms',
            'provider_id'  => 'err42-msg-001',
            'to'           => '+37069912345',
            'from'         => 'Zelta',
            'message'      => 'Other error test',
            'parts'        => 1,
            'status'       => SmsMessage::STATUS_SENT,
            'price_usdc'   => '48000',
            'country_code' => 'LT',
            'test_mode'    => true,
        ]);

        app(SmsService::class)->handleDeliveryReport([
            'message_id' => 'err42-msg-001',
            'raw_status' => 2,
            'error_code' => 42,
        ]);
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `XDEBUG_MODE=off ./vendor/bin/pest tests/Feature/Domain/SMS/SmsServiceDlrErrorTest.php`
Expected: FAIL — `critical` never called

- [ ] **Step 3: Add error-24 alerting in `handleDeliveryReport()`**

In `app/Domain/SMS/Services/SmsService.php`, inside `handleDeliveryReport()`, add after the `$sms->update($updates)` line and before the event dispatch block:

```php
// Alert on VertexSMS error code 24 = Account balance limit reached
if (($dlr['error_code'] ?? null) === 24) {
    Log::critical('SMS: VertexSMS account balance exhausted — all further SMS will fail until topped up', [
        'provider_id' => $dlr['message_id'],
        'error_code'  => 24,
        'sms_id'      => $sms->id,
    ]);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `XDEBUG_MODE=off ./vendor/bin/pest tests/Feature/Domain/SMS/SmsServiceDlrErrorTest.php`
Expected: All PASS

- [ ] **Step 5: Commit**

```bash
git add app/Domain/SMS/Services/SmsService.php tests/Feature/Domain/SMS/SmsServiceDlrErrorTest.php
git commit -m "feat(sms): critical log on error-24 (balance exhausted) per VertexSMS Q8

When VertexSMS test credit runs out, DLR arrives with error code 24.
This is now logged at critical level so monitoring picks it up.

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 4: VertexSMS Client `getMessageStatus()` Method (Q11)

The reconciliation command needs to query VertexSMS directly for message status. Add `getMessageStatus()` to the client.

**Files:**
- Modify: `app/Domain/SMS/Clients/VertexSmsClient.php`
- Create: `tests/Feature/Domain/SMS/VertexSmsClientStatusTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Domain/SMS/VertexSmsClientStatusTest.php`:

```php
<?php

declare(strict_types=1);

use App\Domain\SMS\Clients\VertexSmsClient;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'sms.providers.vertexsms.api_token' => 'test-token',
        'sms.providers.vertexsms.base_url'  => 'https://kube-api.vertexsms.com',
        'sms.webhook.dlr_url'               => '',
        'sms.webhook.dlr_url_token'         => '',
    ]);
});

describe('VertexSmsClient::getMessageStatus', function (): void {
    it('returns parsed status for a delivered message', function (): void {
        Http::fake([
            'kube-api.vertexsms.com/sms/status/12345*' => Http::response([
                'id'     => 12345,
                'status' => 1,
                'error'  => 0,
            ], 200),
        ]);

        $result = (new VertexSmsClient())->getMessageStatus('12345');

        expect($result)->not->toBeNull();
        expect($result['id'])->toBe('12345');
        expect($result['status'])->toBe(1);
        expect($result['error'])->toBe(0);
    });

    it('returns parsed status for a failed message', function (): void {
        Http::fake([
            'kube-api.vertexsms.com/sms/status/67890*' => Http::response([
                'id'     => 67890,
                'status' => 2,
                'error'  => 24,
            ], 200),
        ]);

        $result = (new VertexSmsClient())->getMessageStatus('67890');

        expect($result['status'])->toBe(2);
        expect($result['error'])->toBe(24);
    });

    it('returns null on non-2xx response', function (): void {
        Http::fake([
            'kube-api.vertexsms.com/sms/status/99999*' => Http::response('Not found', 404),
        ]);

        $result = (new VertexSmsClient())->getMessageStatus('99999');

        expect($result)->toBeNull();
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `XDEBUG_MODE=off ./vendor/bin/pest tests/Feature/Domain/SMS/VertexSmsClientStatusTest.php`
Expected: FAIL — method does not exist

- [ ] **Step 3: Implement `getMessageStatus()`**

Add to `app/Domain/SMS/Clients/VertexSmsClient.php`, after the `getRates()` method:

```php
/**
 * Query delivery status for a single message via GET /sms/status/{id}.
 *
 * VertexSMS confirmed no rate limit on this endpoint (Q11). Use as
 * reconciliation fallback for missed DLRs after the SMS expiration
 * window (default 3 days).
 *
 * @return array{id: string, status: int, error: int}|null
 */
public function getMessageStatus(string $messageId): ?array
{
    $this->requireApiToken();

    $response = $this->request()->get("{$this->baseUrl}/sms/status/{$messageId}");

    if (! $response->successful()) {
        Log::warning('VertexSMS: /sms/status failed', [
            'message_id' => $messageId,
            'status'     => $response->status(),
        ]);

        return null;
    }

    /** @var array<string, mixed>|null $data */
    $data = $response->json();

    if (! is_array($data) || $data === []) {
        return null;
    }

    return [
        'id'     => (string) ($data['id'] ?? $messageId),
        'status' => is_numeric($data['status'] ?? null) ? (int) $data['status'] : -1,
        'error'  => is_numeric($data['error'] ?? null) ? (int) $data['error'] : 0,
    ];
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `XDEBUG_MODE=off ./vendor/bin/pest tests/Feature/Domain/SMS/VertexSmsClientStatusTest.php`
Expected: All PASS

- [ ] **Step 5: Commit**

```bash
git add app/Domain/SMS/Clients/VertexSmsClient.php tests/Feature/Domain/SMS/VertexSmsClientStatusTest.php
git commit -m "feat(sms): add getMessageStatus() to VertexSmsClient for reconciliation

Queries GET /sms/status/{id} on VertexSMS API. No rate limit per Q11.
Used by the sms:reconcile command to backfill missed DLR reports.

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 5: DLR Reconciliation Command (Q11)

Create `sms:reconcile` artisan command. Finds messages stuck in "sent" past the SMS expiration window (default 3 days), queries VertexSMS for their status, and processes the result as a synthetic DLR.

**Files:**
- Modify: `config/sms.php`
- Create: `app/Console/Commands/SmsReconcileCommand.php`
- Create: `tests/Feature/Console/SmsReconcileCommandTest.php`

- [ ] **Step 1: Add expiration config**

In `config/sms.php`, add to the `defaults` section:

```php
// Default SMS expiration in seconds (VertexSMS default: 3 days).
// After this window, if no DLR received, reconciliation can poll status.
'expire_seconds' => (int) env('SMS_EXPIRE_SECONDS', 259200),
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Console/SmsReconcileCommandTest.php`:

```php
<?php

declare(strict_types=1);

use App\Domain\SMS\Models\SmsMessage;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'sms.providers.vertexsms.api_token' => 'test-token',
        'sms.providers.vertexsms.base_url'  => 'https://kube-api.vertexsms.com',
        'sms.defaults.expire_seconds'       => 259200,
        'sms.webhook.dlr_url'               => '',
        'sms.webhook.dlr_url_token'         => '',
        'cache.default'                     => 'array',
    ]);
});

describe('sms:reconcile command', function (): void {
    it('reconciles stale sent messages past expiration window', function (): void {
        $sms = SmsMessage::create([
            'provider'     => 'vertexsms',
            'provider_id'  => 'recon-001',
            'to'           => '+37069912345',
            'from'         => 'Zelta',
            'message'      => 'Reconcile test',
            'parts'        => 1,
            'status'       => SmsMessage::STATUS_SENT,
            'price_usdc'   => '48000',
            'country_code' => 'LT',
            'test_mode'    => true,
            'created_at'   => now()->subDays(4),
        ]);

        Http::fake([
            'kube-api.vertexsms.com/sms/status/recon-001*' => Http::response([
                'id'     => 'recon-001',
                'status' => 1,
                'error'  => 0,
            ], 200),
        ]);

        $this->artisan('sms:reconcile')
            ->assertExitCode(0);

        $sms->refresh();
        expect($sms->status)->toBe(SmsMessage::STATUS_DELIVERED);
    });

    it('marks undelivered messages as failed', function (): void {
        $sms = SmsMessage::create([
            'provider'     => 'vertexsms',
            'provider_id'  => 'recon-002',
            'to'           => '+37069912345',
            'from'         => 'Zelta',
            'message'      => 'Failed reconcile',
            'parts'        => 1,
            'status'       => SmsMessage::STATUS_SENT,
            'price_usdc'   => '48000',
            'country_code' => 'LT',
            'test_mode'    => true,
            'created_at'   => now()->subDays(4),
        ]);

        Http::fake([
            'kube-api.vertexsms.com/sms/status/recon-002*' => Http::response([
                'id'     => 'recon-002',
                'status' => 2,
                'error'  => 2,
            ], 200),
        ]);

        $this->artisan('sms:reconcile')
            ->assertExitCode(0);

        $sms->refresh();
        expect($sms->status)->toBe(SmsMessage::STATUS_FAILED);
        expect($sms->error_code)->toBe(2);
    });

    it('skips messages within the expiration window', function (): void {
        SmsMessage::create([
            'provider'     => 'vertexsms',
            'provider_id'  => 'recon-003',
            'to'           => '+37069912345',
            'from'         => 'Zelta',
            'message'      => 'Too recent',
            'parts'        => 1,
            'status'       => SmsMessage::STATUS_SENT,
            'price_usdc'   => '48000',
            'country_code' => 'LT',
            'test_mode'    => true,
            'created_at'   => now()->subHours(1),
        ]);

        Http::fake();

        $this->artisan('sms:reconcile')
            ->assertExitCode(0);

        Http::assertNothingSent();
    });

    it('does nothing when no stale messages exist', function (): void {
        Http::fake();

        $this->artisan('sms:reconcile')
            ->assertExitCode(0);

        Http::assertNothingSent();
    });
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `XDEBUG_MODE=off ./vendor/bin/pest tests/Feature/Console/SmsReconcileCommandTest.php`
Expected: FAIL — command does not exist

- [ ] **Step 4: Create the reconciliation command**

Create `app/Console/Commands/SmsReconcileCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\SMS\Clients\VertexSmsClient;
use App\Domain\SMS\Models\SmsMessage;
use App\Domain\SMS\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reconciles SMS messages stuck in "sent" past the VertexSMS expiration window.
 *
 * VertexSMS guarantees a final DLR (delivered or undelivered with error=2) before
 * the expiration time (default 3 days / 259200s). If no DLR was received by then,
 * this command polls GET /sms/status/{id} as a fallback.
 *
 * Safe to run on a schedule (e.g. daily). No rate limit on the status endpoint (Q11).
 */
class SmsReconcileCommand extends Command
{
    protected $signature = 'sms:reconcile
        {--limit=100 : Maximum messages to reconcile per run}
        {--dry-run : Show what would be reconciled without making changes}';

    protected $description = 'Reconcile SMS messages with missing DLR reports by polling VertexSMS status API';

    public function handle(VertexSmsClient $client, SmsService $smsService): int
    {
        $expireSeconds = (int) config('sms.defaults.expire_seconds', 259200);
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $cutoff = now()->subSeconds($expireSeconds);

        $stale = SmsMessage::where('status', SmsMessage::STATUS_SENT)
            ->where('provider', 'vertexsms')
            ->where('created_at', '<', $cutoff)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        if ($stale->isEmpty()) {
            $this->info('No stale messages to reconcile.');

            return self::SUCCESS;
        }

        $this->info("Found {$stale->count()} stale message(s) past {$expireSeconds}s expiration window.");

        $reconciled = 0;
        $errors = 0;

        foreach ($stale as $sms) {
            $providerId = (string) $sms->provider_id;

            if ($dryRun) {
                $this->line("  [DRY-RUN] Would reconcile: {$providerId} (created {$sms->created_at})");

                continue;
            }

            try {
                $remote = $client->getMessageStatus($providerId);

                if ($remote === null) {
                    $this->warn("  Could not fetch status for {$providerId} — skipping");
                    $errors++;

                    continue;
                }

                $smsService->handleDeliveryReport([
                    'message_id' => $providerId,
                    'raw_status' => $remote['status'],
                    'error_code' => $remote['error'],
                ]);

                $reconciled++;
                $this->line("  Reconciled {$providerId}: status={$remote['status']}, error={$remote['error']}");
            } catch (Throwable $e) {
                $errors++;
                $this->error("  Failed to reconcile {$providerId}: {$e->getMessage()}");
                Log::error('sms:reconcile failed', [
                    'provider_id' => $providerId,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Reconciled: {$reconciled}, Errors: {$errors}");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `XDEBUG_MODE=off ./vendor/bin/pest tests/Feature/Console/SmsReconcileCommandTest.php`
Expected: All PASS

- [ ] **Step 6: Run full SMS test suite**

Run: `XDEBUG_MODE=off ./vendor/bin/pest tests/Feature/Domain/SMS/ tests/Feature/Api/SMS/ tests/Feature/Console/SmsReconcileCommandTest.php`
Expected: All PASS

- [ ] **Step 7: Commit**

```bash
git add config/sms.php app/Console/Commands/SmsReconcileCommand.php tests/Feature/Console/SmsReconcileCommandTest.php
git commit -m "feat(sms): add sms:reconcile command for missed DLR backfill per Q11

Polls VertexSMS GET /sms/status/{id} for messages stuck in 'sent' past
the 3-day expiration window. Safe for daily scheduling. Supports
--dry-run and --limit options.

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 6: Subscriber "Landing" Source Fix

The landing page early-access form submits `source = "landing"` but the `Subscriber` model and Filament admin don't recognize it. Also, the `SubscriberResource` is hidden when `ADMIN_MODULES` excludes "Marketing".

**Files:**
- Modify: `app/Domain/Newsletter/Models/Subscriber.php`
- Modify: `app/Filament/Admin/Resources/SubscriberResource.php`

- [ ] **Step 1: Add `SOURCE_LANDING` constant**

In `app/Domain/Newsletter/Models/Subscriber.php`, add after `SOURCE_PARTNER`:

```php
public const SOURCE_LANDING = 'landing';
```

- [ ] **Step 2: Add to Filament source options (form)**

In `app/Filament/Admin/Resources/SubscriberResource.php`, in the `form()` method, add to the source `->options()` array:

```php
Subscriber::SOURCE_LANDING    => 'Landing Page',
```

- [ ] **Step 3: Add to Filament source options (table filter)**

In the same file, in the `table()` method, add to the `SelectFilter::make('source')->options()` array:

```php
Subscriber::SOURCE_LANDING    => 'Landing Page',
```

- [ ] **Step 4: Add to table column display**

In the `table()` method, add to the `formatStateUsing` match in the `source` column:

```php
Subscriber::SOURCE_LANDING    => 'Landing Page',
```

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Newsletter/Models/Subscriber.php app/Filament/Admin/Resources/SubscriberResource.php
git commit -m "fix(newsletter): add 'landing' source constant for early-access form

The landing page form submits source='landing' but it had no matching
constant or Filament display label. Now visible in admin Subscribers.

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 7: Q13 Bidirectional MCP Response

Draft the response to VertexSMS explaining the bidirectional MCP setup.

**Files:**
- Create: `docs/partners/VERTEXSMS_Q13_BIDIRECTIONAL_MCP.md`

- [ ] **Step 1: Write the response document**

Create `docs/partners/VERTEXSMS_Q13_BIDIRECTIONAL_MCP.md`:

```markdown
# Q13: Bidirectional MCP Setup — Details for VertexSMS

**Context:** VertexSMS asked for more details on what the bidirectional setup involves before committing.

---

## What "Bidirectional" Means

Right now the plan is one-directional:
- **Zelta → VertexSMS**: AI agents call Zelta's MCP `send_sms` tool, which internally calls VertexSMS API to deliver the SMS.

Bidirectional adds the reverse:
- **VertexSMS → Zelta**: VertexSMS appears as a discoverable service provider *inside* Zelta's MCP ecosystem. Any Zelta-connected agent can discover VertexSMS as an SMS rail alongside other future providers.

## What VertexSMS Would Provide

| Item | Description | Effort |
|------|------------|--------|
| **Sandbox API token** | A test-mode token for our MCP server to call your API | 0 — you already gave us one |
| **Signed DLR callbacks** | HMAC-SHA256 on DLR webhooks (already confirmed in Q2) | 0 — already agreed |
| **Rate card endpoint** | `GET /rates/?format=json` (already exists) | 0 |
| **Logo + description** | Brand assets for the provider listing (64x64 icon, one-line tagline) | 5 minutes |

**Total effort from VertexSMS: near zero** — everything technical is already in place.

## What Zelta Builds (Already Done)

- `SmsSendTool` MCP tool is registered and exposes `send_sms` with payment auto-handling
- VertexSMS client is wired as the SMS provider behind this tool
- Any MCP-compatible client (Claude, GPT, custom agents) can discover the tool via standard MCP manifest at `https://zelta.app/.well-known/mcp-manifest.json`

## What "Discoverable" Means for VertexSMS

When an AI agent connects to Zelta's MCP server, it sees available tools:

```json
{
  "tools": [
    {
      "name": "send_sms",
      "description": "Send SMS via VertexSMS. Pay per-message via USDC, Stripe, or Lightning.",
      "provider": "VertexSMS",
      "inputSchema": {
        "type": "object",
        "properties": {
          "to": { "type": "string", "description": "E.164 phone number" },
          "from": { "type": "string", "description": "Sender ID" },
          "message": { "type": "string", "description": "Message body" }
        },
        "required": ["to", "message"]
      }
    }
  ]
}
```

The agent doesn't need to know about VertexSMS directly — it discovers SMS capability through Zelta's tool registry. Payment is handled transparently by Zelta's SDK.

## Benefits for VertexSMS

1. **Zero integration work** — everything is already built on our side
2. **New distribution channel** — every Zelta-connected AI agent is a potential VertexSMS customer
3. **No API changes** — we call your existing `POST /sms` endpoint
4. **Revenue from day one** — agents pay per-message, settlement to your account via Stripe Connect or USDC

## Next Step

If you're open to this, we just need:
1. Confirmation we can list "VertexSMS" as the provider name in the tool description
2. A small logo/icon for the provider listing (optional, we can use a text placeholder)

No code changes or API work needed from your side.
```

- [ ] **Step 2: Commit**

```bash
git add docs/partners/VERTEXSMS_Q13_BIDIRECTIONAL_MCP.md
git commit -m "docs: draft Q13 bidirectional MCP response for VertexSMS

Explains what the bidirectional setup involves (near-zero effort from
their side) and the benefits of being listed as a provider in Zelta's
MCP tool registry.

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 8: Code Quality & PHPStan

Run the full quality pipeline to ensure all changes pass.

**Files:**
- All modified files from Tasks 1-6

- [ ] **Step 1: Run php-cs-fixer**

Run: `./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php`
Expected: Any formatting issues auto-fixed

- [ ] **Step 2: Run PHPStan**

Run: `XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G`
Expected: No new errors. If errors found, fix them.

- [ ] **Step 3: Run full test suite**

Run: `XDEBUG_MODE=off ./vendor/bin/pest --parallel --stop-on-failure`
Expected: All tests pass

- [ ] **Step 4: Commit any fixes**

```bash
git add -A
git commit -m "fix: code style and PHPStan fixes for SMS hardening

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

### Task 9: PR

Create PR with all changes.

- [ ] **Step 1: Create PR**

```bash
gh pr create --title "feat(sms): VertexSMS Q&A hardening — throttle, dedup, reconciliation" --body "..."
```

Include summary of all 6 functional changes and link to the Q&A answers in the PR body.
