# Stripe Bridge Ramp Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the latent bugs in the existing Stripe Bridge ramp scaffolding and widen the `RampProviderInterface` abstraction so Stripe Crypto Onramp works correctly against real traffic, while benefiting every ramp provider (Onramper, Mock, future).

**Architecture:** Widen `RampProviderInterface` with three capabilities — `normalizeWebhookPayload()`, `getWebhookSignatureHeader()`, and a widened `getWebhookValidator()` callable that takes raw HTTP body + full signature header. Move provider-specific logic out of `RampService` and into each provider. Add a `RampProviderRegistry` for name-based provider resolution at the webhook controller. Fix `StripeBridgeProvider`'s webhook signature verification (proper `t=,v1=` scheme with HMAC + replay window), payload normalization (event envelope unwrap), and session status fetch (real Stripe GET). Change `getSupportedCurrencies()` interface return type to the keyed shape `{fiatCurrencies, cryptoCurrencies, modes, limits}` so `RampService::validateRampParams()` can read capabilities from the active provider rather than global config.

**Tech Stack:** PHP 8.4, Laravel 12, Pest, PHPStan Level 8, bcmath, Laravel HTTP client (`Http::fake()` for tests).

**Spec:** `docs/superpowers/specs/2026-04-12-stripe-bridge-ramp-design.md`

---

## File Structure

### Files to create

- `app/Domain/Ramp/Exceptions/InvalidWebhookSignatureException.php` — new exception type for signature failures (distinguishes 400 vs 500 in controller)
- `app/Domain/Ramp/Registries/RampProviderRegistry.php` — name-based provider resolution for webhook controller
- `tests/Feature/Api/Ramp/RampProviderContractTest.php` — shared contract tests parameterized across all providers
- `tests/Feature/Api/Ramp/StripeBridgeRampTest.php` — Stripe-specific feature tests
- `tests/Feature/Api/Ramp/RampWebhookRawBodyTest.php` — controller-level tests for the raw-body refactor
- `tests/Fixtures/stripe_bridge_webhooks.php` — realistic Stripe event fixtures
- `docs/MOBILE_FEEDBACK_STRIPE_BRIDGE.md` — deviations + decisions log for mobile team

### Files to modify

- `app/Domain/Ramp/Contracts/RampProviderInterface.php` — widen interface
- `app/Domain/Ramp/Services/StripeBridgeService.php` — add `getSession()` method
- `app/Domain/Ramp/Providers/StripeBridgeProvider.php` — fix signature, normalize, getSessionStatus, getSupportedCurrencies, signature header, name
- `app/Domain/Ramp/Providers/OnramperProvider.php` — adapt to new interface
- `app/Domain/Ramp/Providers/MockRampProvider.php` — adapt to new interface
- `app/Domain/Ramp/Services/RampService.php` — refactor `handleWebhook` + `validateRampParams`
- `app/Http/Controllers/Api/V1/RampWebhookController.php` — raw body + provider registry + split exceptions
- `app/Http/Controllers/Api/V1/RampController.php` — route `supported()` through the provider interface
- `app/Providers/RampServiceProvider.php` — bind `RampProviderRegistry`
- `config/services.php` — verify `stripe.secret` entry
- `config/ramp.php` — comment on deprecated `supported_fiat`/`supported_crypto`
- `.env.example` — Stripe Bridge env var docs
- `.env.zelta.example` — Stripe Bridge env var docs
- `.env.production.example` — Stripe Bridge env var docs

### Files NOT changed

- `database/migrations/2026_03_04_221000_create_ramp_sessions_table.php` — zero migrations
- `app/Models/RampSession.php` — model unchanged; columns already present
- `routes/api.php` — all routes already defined
- `app/Http/Resources/V1/RampSessionResource.php` — response shape already correct

---

## Task 1: Create the `InvalidWebhookSignatureException` class

**Files:**
- Create: `app/Domain/Ramp/Exceptions/InvalidWebhookSignatureException.php`

- [ ] **Step 1: Create the exception class**

Create `app/Domain/Ramp/Exceptions/InvalidWebhookSignatureException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Exceptions;

use RuntimeException;

final class InvalidWebhookSignatureException extends RuntimeException
{
    public function __construct(string $message = 'Invalid webhook signature')
    {
        parent::__construct($message);
    }
}
```

- [ ] **Step 2: Verify file compiles**

Run: `XDEBUG_MODE=off ./vendor/bin/phpstan analyse app/Domain/Ramp/Exceptions/InvalidWebhookSignatureException.php --level=8 --memory-limit=2G`
Expected: `[OK] No errors`

- [ ] **Step 3: Commit**

```bash
git add app/Domain/Ramp/Exceptions/InvalidWebhookSignatureException.php
git commit -m "feat(ramp): add InvalidWebhookSignatureException for webhook 400 vs 500 split

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 2: Add Stripe webhook fixtures

**Files:**
- Create: `tests/Fixtures/stripe_bridge_webhooks.php`

- [ ] **Step 1: Create the fixtures file**

Create `tests/Fixtures/stripe_bridge_webhooks.php`:

```php
<?php

declare(strict_types=1);

/**
 * Realistic Stripe Crypto Onramp webhook event fixtures.
 *
 * These mirror the shape of events Stripe actually sends, used across
 * StripeBridgeRampTest and RampProviderContractTest.
 *
 * @see https://docs.stripe.com/crypto/onramp
 */
return [
    'session_updated' => [
        'id'      => 'evt_test_updated_123',
        'type'    => 'crypto_onramp_session.updated',
        'object'  => 'event',
        'created' => 1743500000,
        'data'    => [
            'object' => [
                'id'                   => 'cos_test_abc123',
                'object'               => 'crypto.onramp_session',
                'status'               => 'payment_pending',
                'source_currency'      => 'usd',
                'source_amount'        => '100.00',
                'destination_currency' => 'usdc',
                'destination_network'  => 'ethereum',
                'destination_amount'   => null,
                'wallet_addresses'     => [
                    'ethereum' => '0x1234567890abcdef1234567890abcdef12345678',
                ],
            ],
        ],
    ],

    'session_completed' => [
        'id'      => 'evt_test_completed_456',
        'type'    => 'crypto_onramp_session.completed',
        'object'  => 'event',
        'created' => 1743500100,
        'data'    => [
            'object' => [
                'id'                   => 'cos_test_abc123',
                'object'               => 'crypto.onramp_session',
                'status'               => 'fulfilled',
                'source_currency'      => 'usd',
                'source_amount'        => '100.00',
                'destination_currency' => 'usdc',
                'destination_network'  => 'ethereum',
                'destination_amount'   => '98.50000000',
                'wallet_addresses'     => [
                    'ethereum' => '0x1234567890abcdef1234567890abcdef12345678',
                ],
            ],
        ],
    ],

    'unrelated_event' => [
        'id'      => 'evt_test_unrelated_789',
        'type'    => 'payment_intent.succeeded',
        'object'  => 'event',
        'created' => 1743500200,
        'data'    => [
            'object' => [
                'id'     => 'pi_test_xyz',
                'object' => 'payment_intent',
                'status' => 'succeeded',
                'amount' => 10000,
            ],
        ],
    ],

    'session_without_id' => [
        'id'      => 'evt_test_noid_000',
        'type'    => 'crypto_onramp_session.updated',
        'object'  => 'event',
        'created' => 1743500300,
        'data'    => [
            'object' => [
                'object' => 'crypto.onramp_session',
                'status' => 'initialized',
            ],
        ],
    ],
];
```

- [ ] **Step 2: Commit**

```bash
git add tests/Fixtures/stripe_bridge_webhooks.php
git commit -m "test(ramp): add Stripe webhook event fixtures

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 3: Widen the `RampProviderInterface`

This task changes the interface contract. All three providers will break compilation after this change — Tasks 4, 5, 6 fix them. Commit the interface change separately so the breaking change is a single atomic commit.

**Files:**
- Modify: `app/Domain/Ramp/Contracts/RampProviderInterface.php`

- [ ] **Step 1: Replace the interface**

Replace the entire contents of `app/Domain/Ramp/Contracts/RampProviderInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Contracts;

interface RampProviderInterface
{
    /**
     * Create a ramp session via checkout API.
     *
     * @param  array{type: string, fiat_currency: string, fiat_amount: string, crypto_currency: string, wallet_address: string, quote_id: string|null}  $params
     * @return array{session_id: string, checkout_url: string|null, metadata: array<string, mixed>}
     */
    public function createSession(array $params): array;

    /**
     * Get the status of a ramp session.
     *
     * @return array{status: string, fiat_amount: float|null, crypto_amount: float|null, metadata: array<string, mixed>}
     */
    public function getSessionStatus(string $sessionId): array;

    /**
     * Get supported capabilities (fiat currencies, crypto currencies, modes, and limits)
     * for this provider. Used by RampService::validateRampParams and RampController::supported.
     *
     * @return array{fiatCurrencies: list<string>, cryptoCurrencies: list<string>, modes: list<string>, limits: array{minAmount: int, maxAmount: int, dailyLimit: int}}
     */
    public function getSupportedCurrencies(): array;

    /**
     * Get all quotes from the provider for a ramp transaction.
     *
     * @return array<int, array{provider_name: string, quote_id: string|null, fiat_amount: float, crypto_amount: float, exchange_rate: float, fee: float, network_fee: float, fee_currency: string, payment_methods: array<string>}>
     */
    public function getQuotes(string $type, string $fiatCurrency, string $fiatAmount, string $cryptoCurrency): array;

    /**
     * Get the webhook validator for this provider.
     *
     * The callable receives the raw HTTP body bytes (NOT re-encoded) and the
     * full signature header string. Each provider parses its own header format.
     * Must use hash_equals() for constant-time comparison.
     *
     * @return callable(string $rawBody, string $signatureHeader): bool
     */
    public function getWebhookValidator(): callable;

    /**
     * Return the HTTP header name the validator should read. The webhook controller
     * uses this to fetch the right header from the incoming Request.
     *
     * Example: "Stripe-Signature", "X-Onramper-Webhook-Signature".
     */
    public function getWebhookSignatureHeader(): string;

    /**
     * Unwrap a provider-specific webhook event envelope into a canonical shape.
     * Providers that wrap their payload (Stripe) unwrap it; flat-payload providers
     * (Onramper) return their fields in the canonical shape.
     *
     * Return null to explicitly ignore the event (e.g. Stripe event types we
     * don't care about). Returning null is not an error — the controller will
     * still return 200.
     *
     * The `status` field MUST be one of RampSession::STATUS_* constants — the
     * provider owns the mapping from its vendor-specific status vocabulary.
     *
     * @param  array<string, mixed>  $payload  Parsed JSON body (already decoded after signature verification)
     * @return array{session_id: string, status: string, crypto_amount: string|null, raw: array<string, mixed>}|null
     */
    public function normalizeWebhookPayload(array $payload): ?array;

    /**
     * Get the provider name. Used as the stable identifier in routes, logs,
     * and the `provider` column of ramp_sessions.
     */
    public function getName(): string;
}
```

- [ ] **Step 2: Run PHPStan to confirm all three providers now break**

Run: `XDEBUG_MODE=off ./vendor/bin/phpstan analyse app/Domain/Ramp/ --level=8 --memory-limit=2G`
Expected: Errors like `Class App\Domain\Ramp\Providers\MockRampProvider does not implement method RampProviderInterface::getWebhookSignatureHeader()` for all three providers. This is expected — Tasks 4, 5, 6 fix them.

- [ ] **Step 3: Commit the interface change alone**

```bash
git add app/Domain/Ramp/Contracts/RampProviderInterface.php
git commit -m "refactor(ramp): widen RampProviderInterface with raw-body webhook shape

Adds normalizeWebhookPayload() and getWebhookSignatureHeader(); widens
getWebhookValidator() to (rawBody, signatureHeader). Changes
getSupportedCurrencies() return type to the keyed capability shape.
Provider implementations are updated in subsequent commits.

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 4: Adapt `MockRampProvider` to the new interface

**Files:**
- Modify: `app/Domain/Ramp/Providers/MockRampProvider.php`

- [ ] **Step 1: Replace `getSupportedCurrencies()`**

Open `app/Domain/Ramp/Providers/MockRampProvider.php`. Replace the `getSupportedCurrencies()` method with:

```php
    public function getSupportedCurrencies(): array
    {
        return [
            'fiatCurrencies'   => ['USD', 'EUR', 'GBP'],
            'cryptoCurrencies' => ['USDC', 'USDT', 'ETH', 'BTC'],
            'modes'            => ['on', 'off'],
            'limits'           => [
                'minAmount'  => (int) config('ramp.limits.min_fiat_amount', 10),
                'maxAmount'  => (int) config('ramp.limits.max_fiat_amount', 10000),
                'dailyLimit' => (int) config('ramp.limits.daily_limit', 50000),
            ],
        ];
    }
```

- [ ] **Step 2: Replace `getWebhookValidator()` and add the two new methods**

In the same file, replace the existing `getWebhookValidator()` method and add the two new methods just before `getName()`:

```php
    public function getWebhookValidator(): callable
    {
        return fn (string $rawBody, string $signatureHeader): bool => $signatureHeader !== '';
    }

    public function getWebhookSignatureHeader(): string
    {
        return 'X-Mock-Signature';
    }

    public function normalizeWebhookPayload(array $payload): ?array
    {
        $sessionId = (string) ($payload['session_id'] ?? '');
        if ($sessionId === '') {
            return null;
        }

        return [
            'session_id'    => $sessionId,
            'status'        => \App\Models\RampSession::STATUS_COMPLETED,
            'crypto_amount' => isset($payload['crypto_amount']) ? (string) $payload['crypto_amount'] : null,
            'raw'           => $payload,
        ];
    }
```

- [ ] **Step 3: Run PHPStan on the mock provider**

Run: `XDEBUG_MODE=off ./vendor/bin/phpstan analyse app/Domain/Ramp/Providers/MockRampProvider.php --level=8 --memory-limit=2G`
Expected: `[OK] No errors` (the mock provider is now compliant).

- [ ] **Step 4: Commit**

```bash
git add app/Domain/Ramp/Providers/MockRampProvider.php
git commit -m "refactor(ramp): adapt MockRampProvider to widened interface

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 5: Adapt `OnramperProvider` to the new interface

**Files:**
- Modify: `app/Domain/Ramp/Providers/OnramperProvider.php`

- [ ] **Step 1: Replace `getSupportedCurrencies()`**

Open `app/Domain/Ramp/Providers/OnramperProvider.php`. Replace the entire `getSupportedCurrencies()` method with:

```php
    public function getSupportedCurrencies(): array
    {
        /** @var list<string> $fiats */
        $fiats = config('ramp.supported_fiat', ['USD', 'EUR', 'GBP']);
        /** @var list<string> $cryptos */
        $cryptos = config('ramp.supported_crypto', ['USDC', 'USDT', 'ETH', 'BTC']);

        return [
            'fiatCurrencies'   => array_values($fiats),
            'cryptoCurrencies' => array_values($cryptos),
            'modes'            => ['on', 'off'],
            'limits'           => [
                'minAmount'  => (int) config('ramp.limits.min_fiat_amount', 10),
                'maxAmount'  => (int) config('ramp.limits.max_fiat_amount', 10000),
                'dailyLimit' => (int) config('ramp.limits.daily_limit', 50000),
            ],
        ];
    }
```

- [ ] **Step 2: Replace `getWebhookValidator()` and add the two new methods**

In the same file, replace the existing `getWebhookValidator()` method with the two-arg version and add the two new methods just before `mapOnramperStatus()`:

```php
    public function getWebhookValidator(): callable
    {
        return fn (string $rawBody, string $signatureHeader): bool
            => $this->client->verifyWebhookSignature($rawBody, $signatureHeader);
    }

    public function getWebhookSignatureHeader(): string
    {
        return 'X-Onramper-Webhook-Signature';
    }

    public function normalizeWebhookPayload(array $payload): ?array
    {
        /** @var mixed $sessionId */
        $sessionId = $payload['session_id'] ?? $payload['partnerContext'] ?? $payload['id'] ?? null;
        if (! is_string($sessionId) || $sessionId === '') {
            return null;
        }

        $rawStatus = (string) ($payload['status'] ?? '');
        if ($rawStatus === '') {
            return null;
        }

        $cryptoAmount = null;
        if (isset($payload['crypto_amount']) && is_numeric($payload['crypto_amount'])) {
            $cryptoAmount = bcadd((string) $payload['crypto_amount'], '0', 8);
        }

        return [
            'session_id'    => $sessionId,
            'status'        => $this->mapOnramperStatus($rawStatus),
            'crypto_amount' => $cryptoAmount,
            'raw'           => $payload,
        ];
    }
```

Note: `verifyWebhookSignature` in `OnramperClient` already accepts `(string $payload, string $signature)` — it treats the first arg as raw bytes already, so just passing through works.

- [ ] **Step 3: Run PHPStan on the Onramper provider**

Run: `XDEBUG_MODE=off ./vendor/bin/phpstan analyse app/Domain/Ramp/Providers/OnramperProvider.php --level=8 --memory-limit=2G`
Expected: `[OK] No errors`.

- [ ] **Step 4: Commit**

```bash
git add app/Domain/Ramp/Providers/OnramperProvider.php
git commit -m "refactor(ramp): adapt OnramperProvider to widened interface

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 6: Add `StripeBridgeService::getSession()` method

**Files:**
- Modify: `app/Domain/Ramp/Services/StripeBridgeService.php`

- [ ] **Step 1: Write the test first**

Open `tests/Feature/Api/Ramp/StripeBridgeRampTest.php` (we'll create this file now if it doesn't exist). Create `tests/Feature/Api/Ramp/StripeBridgeRampTest.php` with just this first test:

```php
<?php

declare(strict_types=1);

use App\Domain\Ramp\Services\StripeBridgeService;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

beforeEach(function () {
    config([
        'services.stripe.secret'                => 'sk_test_fake_key',
        'services.stripe.bridge_webhook_secret' => 'whsec_test_fake',
    ]);
});

it('fetches a Stripe onramp session via getSession()', function () {
    Http::fake([
        'api.stripe.com/v1/crypto/onramp_sessions/cos_test_abc123' => Http::response([
            'id'                 => 'cos_test_abc123',
            'status'             => 'fulfilled',
            'source_amount'      => '100.00',
            'destination_amount' => '98.50000000',
            'destination_currency' => 'usdc',
        ], 200),
    ]);

    $service = new StripeBridgeService();
    $result = $service->getSession('cos_test_abc123');

    expect($result)
        ->toHaveKeys(['status', 'destination_amount', 'raw'])
        ->and($result['status'])->toBe('fulfilled')
        ->and($result['destination_amount'])->toBe('98.50000000');
});

it('throws RuntimeException when Stripe returns 404 for getSession()', function () {
    Http::fake([
        'api.stripe.com/v1/crypto/onramp_sessions/cos_missing' => Http::response([
            'error' => ['message' => 'No such session', 'type' => 'invalid_request_error'],
        ], 404),
    ]);

    $service = new StripeBridgeService();
    $service->getSession('cos_missing');
})->throws(RuntimeException::class);
```

- [ ] **Step 2: Run the test — it should fail because `getSession()` doesn't exist**

Run: `./vendor/bin/pest tests/Feature/Api/Ramp/StripeBridgeRampTest.php --filter="fetches a Stripe onramp session"`
Expected: FAIL with `Call to undefined method App\Domain\Ramp\Services\StripeBridgeService::getSession()`.

- [ ] **Step 3: Implement `getSession()` in `StripeBridgeService`**

Open `app/Domain/Ramp/Services/StripeBridgeService.php`. Add this method after the existing `createSession()` method (around line 86):

```php
    /**
     * Fetch a Stripe crypto onramp session by ID.
     *
     * @return array{status: string, destination_amount: string|null, raw: array<string, mixed>}
     */
    public function getSession(string $sessionId): array
    {
        $this->ensureConfigured();

        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->get("{$this->baseUrl}/crypto/onramp_sessions/{$sessionId}");

        if (! $response->successful()) {
            Log::error('Stripe Bridge: Session fetch failed', [
                'status'     => $response->status(),
                'body'       => $response->body(),
                'session_id' => $sessionId,
            ]);
            throw new RuntimeException(
                'Failed to fetch Stripe Bridge session: ' . ($response->json('error.message') ?? $response->body())
            );
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        $destinationAmount = null;
        if (isset($data['destination_amount']) && is_numeric($data['destination_amount'])) {
            $destinationAmount = bcadd((string) $data['destination_amount'], '0', 8);
        }

        return [
            'status'             => (string) ($data['status'] ?? 'initialized'),
            'destination_amount' => $destinationAmount,
            'raw'                => $data,
        ];
    }
```

- [ ] **Step 4: Run the tests**

Run: `./vendor/bin/pest tests/Feature/Api/Ramp/StripeBridgeRampTest.php`
Expected: Both tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Ramp/Services/StripeBridgeService.php tests/Feature/Api/Ramp/StripeBridgeRampTest.php
git commit -m "feat(ramp): add StripeBridgeService::getSession() for status polling

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 7: Fix `StripeBridgeProvider` — signature verification, normalization, capabilities

This is the biggest single task because the four interface methods all change together and benefit from being tested as a unit.

**Files:**
- Modify: `app/Domain/Ramp/Providers/StripeBridgeProvider.php`
- Modify: `tests/Feature/Api/Ramp/StripeBridgeRampTest.php`

- [ ] **Step 1: Add signature verification tests**

Append to `tests/Feature/Api/Ramp/StripeBridgeRampTest.php` (after the existing `getSession` tests):

```php
it('accepts a valid Stripe-Signature header with a fresh timestamp', function () {
    $provider = app(\App\Domain\Ramp\Providers\StripeBridgeProvider::class);
    $secret = 'whsec_test_fake';
    $body = '{"id":"evt_test","type":"crypto_onramp_session.updated"}';
    $timestamp = time();
    $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    $header = "t={$timestamp},v1={$expected}";

    $validator = $provider->getWebhookValidator();

    expect($validator($body, $header))->toBeTrue();
});

it('rejects a tampered body even with a valid-looking signature', function () {
    $provider = app(\App\Domain\Ramp\Providers\StripeBridgeProvider::class);
    $secret = 'whsec_test_fake';
    $originalBody = '{"id":"evt_test","type":"crypto_onramp_session.updated"}';
    $tamperedBody = '{"id":"evt_test","type":"crypto_onramp_session.completed"}';
    $timestamp = time();
    $expected = hash_hmac('sha256', $timestamp . '.' . $originalBody, $secret);
    $header = "t={$timestamp},v1={$expected}";

    $validator = $provider->getWebhookValidator();

    expect($validator($tamperedBody, $header))->toBeFalse();
});

it('rejects a timestamp older than the 300s replay window', function () {
    $provider = app(\App\Domain\Ramp\Providers\StripeBridgeProvider::class);
    $secret = 'whsec_test_fake';
    $body = '{"id":"evt_test","type":"crypto_onramp_session.updated"}';
    $timestamp = time() - 600;  // 10 minutes ago
    $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    $header = "t={$timestamp},v1={$expected}";

    $validator = $provider->getWebhookValidator();

    expect($validator($body, $header))->toBeFalse();
});

it('rejects a header missing the v1 signature element', function () {
    $provider = app(\App\Domain\Ramp\Providers\StripeBridgeProvider::class);
    $validator = $provider->getWebhookValidator();
    $timestamp = time();

    expect($validator('{}', "t={$timestamp}"))->toBeFalse();
});

it('rejects an empty signature header', function () {
    $provider = app(\App\Domain\Ramp\Providers\StripeBridgeProvider::class);
    $validator = $provider->getWebhookValidator();

    expect($validator('{}', ''))->toBeFalse();
});

it('accepts any of multiple v1 signature entries', function () {
    $provider = app(\App\Domain\Ramp\Providers\StripeBridgeProvider::class);
    $secret = 'whsec_test_fake';
    $body = '{"test":"multi"}';
    $timestamp = time();
    $correct = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    $header = "t={$timestamp},v1=decoy_signature_1,v1={$correct},v1=decoy_signature_2";

    $validator = $provider->getWebhookValidator();

    expect($validator($body, $header))->toBeTrue();
});
```

- [ ] **Step 2: Add payload normalization tests**

Append the normalization tests to `tests/Feature/Api/Ramp/StripeBridgeRampTest.php`:

```php
it('normalizes a Stripe session.updated event into the canonical shape', function () {
    $fixtures = require base_path('tests/Fixtures/stripe_bridge_webhooks.php');
    $provider = app(\App\Domain\Ramp\Providers\StripeBridgeProvider::class);

    $result = $provider->normalizeWebhookPayload($fixtures['session_updated']);

    expect($result)->not->toBeNull()
        ->and($result['session_id'])->toBe('cos_test_abc123')
        ->and($result['status'])->toBe(\App\Models\RampSession::STATUS_PROCESSING)
        ->and($result['crypto_amount'])->toBeNull()
        ->and($result['raw'])->toBeArray();
});

it('normalizes a Stripe session.completed event with destination_amount', function () {
    $fixtures = require base_path('tests/Fixtures/stripe_bridge_webhooks.php');
    $provider = app(\App\Domain\Ramp\Providers\StripeBridgeProvider::class);

    $result = $provider->normalizeWebhookPayload($fixtures['session_completed']);

    expect($result)->not->toBeNull()
        ->and($result['session_id'])->toBe('cos_test_abc123')
        ->and($result['status'])->toBe(\App\Models\RampSession::STATUS_COMPLETED)
        ->and($result['crypto_amount'])->toBe('98.50000000');
});

it('returns null for an unrelated Stripe event type', function () {
    $fixtures = require base_path('tests/Fixtures/stripe_bridge_webhooks.php');
    $provider = app(\App\Domain\Ramp\Providers\StripeBridgeProvider::class);

    expect($provider->normalizeWebhookPayload($fixtures['unrelated_event']))->toBeNull();
});

it('returns null for a malformed event without a session id', function () {
    $fixtures = require base_path('tests/Fixtures/stripe_bridge_webhooks.php');
    $provider = app(\App\Domain\Ramp\Providers\StripeBridgeProvider::class);

    expect($provider->normalizeWebhookPayload($fixtures['session_without_id']))->toBeNull();
});
```

- [ ] **Step 3: Add `getWebhookSignatureHeader` + `getSupportedCurrencies` tests**

Append these tests to `tests/Feature/Api/Ramp/StripeBridgeRampTest.php`:

```php
it('returns the correct webhook signature header name', function () {
    $provider = app(\App\Domain\Ramp\Providers\StripeBridgeProvider::class);
    expect($provider->getWebhookSignatureHeader())->toBe('Stripe-Signature');
});

it('returns supported currencies in the canonical keyed shape', function () {
    $provider = app(\App\Domain\Ramp\Providers\StripeBridgeProvider::class);
    $supported = $provider->getSupportedCurrencies();

    expect($supported)
        ->toHaveKeys(['fiatCurrencies', 'cryptoCurrencies', 'modes', 'limits'])
        ->and($supported['fiatCurrencies'])->toContain('USD')
        ->and($supported['cryptoCurrencies'])->toContain('USDC')
        ->and($supported['limits'])->toHaveKeys(['minAmount', 'maxAmount', 'dailyLimit']);
});
```

- [ ] **Step 4: Run the new tests — they should fail**

Run: `./vendor/bin/pest tests/Feature/Api/Ramp/StripeBridgeRampTest.php`
Expected: The new tests FAIL (signature validator still uses plain HMAC, normalizeWebhookPayload doesn't exist, etc.). The existing `getSession()` tests still pass.

- [ ] **Step 5: Replace the entire `StripeBridgeProvider` class**

Open `app/Domain/Ramp/Providers/StripeBridgeProvider.php` and replace the file with:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Providers;

use App\Domain\Ramp\Contracts\RampProviderInterface;
use App\Domain\Ramp\Services\StripeBridgeService;
use App\Models\RampSession;

class StripeBridgeProvider implements RampProviderInterface
{
    public function __construct(
        private readonly StripeBridgeService $service,
    ) {
    }

    public function createSession(array $params): array
    {
        /** @var numeric-string $rawAmount */
        $rawAmount = (string) $params['fiat_amount'];
        $fiatAmount = bcadd($rawAmount, '0', 2);

        $result = $this->service->createSession(
            $params['type'],
            $params['fiat_currency'],
            $fiatAmount,
            $params['crypto_currency'],
            $params['wallet_address'],
        );

        return [
            'session_id'   => $result['session_id'],
            'checkout_url' => $result['checkout_url'],
            'metadata'     => [
                'provider'          => 'stripe_bridge',
                'stripe_session_id' => $result['session_id'],
                'client_secret'     => $result['client_secret'],
                'checkout_url'      => $result['checkout_url'],
                'type'              => $params['type'],
            ],
        ];
    }

    public function getSessionStatus(string $sessionId): array
    {
        $stripeSession = $this->service->getSession($sessionId);

        $cryptoAmount = null;
        if ($stripeSession['destination_amount'] !== null) {
            $cryptoAmount = (float) $stripeSession['destination_amount'];
        }

        return [
            'status'        => $this->service->mapStripeStatus($stripeSession['status']),
            'fiat_amount'   => null,
            'crypto_amount' => $cryptoAmount,
            'metadata'      => [
                'provider'      => 'stripe_bridge',
                'stripe_status' => $stripeSession['status'],
            ],
        ];
    }

    public function getSupportedCurrencies(): array
    {
        return $this->service->getSupportedCurrencies();
    }

    public function getQuotes(string $type, string $fiatCurrency, string $fiatAmount, string $cryptoCurrency): array
    {
        /** @var numeric-string $fiatAmount */
        $amount = bcadd($fiatAmount, '0', 2);
        $quote = $this->service->getQuote($type, $fiatCurrency, $amount, $cryptoCurrency);

        return [
            [
                'provider_name'   => $quote['providerName'],
                'quote_id'        => $quote['quoteId'],
                'fiat_amount'     => (float) $quote['fiatAmount'],
                'crypto_amount'   => (float) $quote['cryptoAmount'],
                'exchange_rate'   => (float) $quote['exchangeRate'],
                'fee'             => (float) $quote['fee'],
                'network_fee'     => (float) $quote['networkFee'],
                'fee_currency'    => $quote['feeCurrency'],
                'payment_methods' => $quote['paymentMethods'],
            ],
        ];
    }

    public function getWebhookValidator(): callable
    {
        return function (string $rawBody, string $signatureHeader): bool {
            $secret = (string) config('services.stripe.bridge_webhook_secret', '');

            if ($secret === '') {
                return ! app()->environment('production');
            }

            if ($signatureHeader === '') {
                return false;
            }

            /** @var array<string, list<string>> $parts */
            $parts = [];
            foreach (explode(',', $signatureHeader) as $element) {
                $element = trim($element);
                if ($element === '') {
                    continue;
                }
                $pair = array_pad(explode('=', $element, 2), 2, '');
                $parts[$pair[0]][] = $pair[1];
            }

            $timestamp = (int) ($parts['t'][0] ?? 0);
            $signatures = $parts['v1'] ?? [];

            if ($timestamp === 0 || $signatures === []) {
                return false;
            }

            if (abs(time() - $timestamp) > 300) {
                return false;
            }

            $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);

            foreach ($signatures as $candidate) {
                if (hash_equals($expected, $candidate)) {
                    return true;
                }
            }

            return false;
        };
    }

    public function getWebhookSignatureHeader(): string
    {
        return 'Stripe-Signature';
    }

    public function normalizeWebhookPayload(array $payload): ?array
    {
        $eventType = (string) ($payload['type'] ?? '');
        if (! str_starts_with($eventType, 'crypto_onramp_session.')) {
            return null;
        }

        $object = $payload['data']['object'] ?? null;
        if (! is_array($object)) {
            return null;
        }

        $sessionId = (string) ($object['id'] ?? '');
        if ($sessionId === '') {
            return null;
        }

        $stripeStatus = (string) ($object['status'] ?? '');
        if ($stripeStatus === '') {
            return null;
        }

        $cryptoAmount = null;
        if (isset($object['destination_amount']) && is_numeric($object['destination_amount'])) {
            $cryptoAmount = bcadd((string) $object['destination_amount'], '0', 8);
        }

        return [
            'session_id'    => $sessionId,
            'status'        => $this->service->mapStripeStatus($stripeStatus),
            'crypto_amount' => $cryptoAmount,
            'raw'           => $object,
        ];
    }

    public function getName(): string
    {
        return 'stripe_bridge';
    }
}
```

- [ ] **Step 6: Run the Stripe Bridge tests — they should all pass**

Run: `./vendor/bin/pest tests/Feature/Api/Ramp/StripeBridgeRampTest.php`
Expected: All tests PASS.

- [ ] **Step 7: Run PHPStan on the provider**

Run: `XDEBUG_MODE=off ./vendor/bin/phpstan analyse app/Domain/Ramp/Providers/StripeBridgeProvider.php --level=8 --memory-limit=2G`
Expected: `[OK] No errors`.

- [ ] **Step 8: Commit**

```bash
git add app/Domain/Ramp/Providers/StripeBridgeProvider.php tests/Feature/Api/Ramp/StripeBridgeRampTest.php
git commit -m "fix(ramp): implement proper Stripe signature verification and event normalization

- getWebhookValidator() now parses 't=<ts>,v1=<hmac>' header, verifies
  HMAC-SHA256(secret, '<ts>.<rawBody>') with constant-time compare,
  rejects timestamps outside the 300s replay window
- normalizeWebhookPayload() unwraps Stripe event envelope and maps
  vendor status vocabulary into RampSession::STATUS_*
- getSessionStatus() now calls StripeBridgeService::getSession()
  instead of returning hardcoded pending (fixes clobber-to-pending
  on concurrent poll + webhook race)
- getSupportedCurrencies() returns the canonical keyed shape from
  the service directly

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 8: Refactor `RampService::handleWebhook` and `validateRampParams`

**Files:**
- Modify: `app/Domain/Ramp/Services/RampService.php`

- [ ] **Step 1: Replace the entire file**

Open `app/Domain/Ramp/Services/RampService.php` and replace with:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Services;

use App\Domain\Ramp\Contracts\RampProviderInterface;
use App\Domain\Ramp\Exceptions\InvalidWebhookSignatureException;
use App\Models\RampSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RampService
{
    public function __construct(
        private readonly RampProviderInterface $provider,
    ) {
    }

    /**
     * @return array{quotes: array<int, array<string, mixed>>, provider: string, valid_until: string}
     */
    public function getQuotes(string $type, string $fiatCurrency, string $fiatAmount, string $cryptoCurrency): array
    {
        $this->validateRampParams($type, $fiatCurrency, $fiatAmount, $cryptoCurrency);

        $quotes = $this->provider->getQuotes($type, $fiatCurrency, $fiatAmount, $cryptoCurrency);

        return [
            'quotes'      => $quotes,
            'provider'    => $this->provider->getName(),
            'valid_until' => now()->addSeconds(60)->toIso8601String(),
        ];
    }

    public function createSession(
        User $user,
        string $type,
        string $fiatCurrency,
        string $fiatAmount,
        string $cryptoCurrency,
        string $walletAddress,
        ?string $quoteId = null,
    ): RampSession {
        $this->validateRampParams($type, $fiatCurrency, $fiatAmount, $cryptoCurrency);

        $providerResult = $this->provider->createSession([
            'type'            => $type,
            'fiat_currency'   => $fiatCurrency,
            'fiat_amount'     => $fiatAmount,
            'crypto_currency' => $cryptoCurrency,
            'wallet_address'  => $walletAddress,
            'quote_id'        => $quoteId,
        ]);

        $sessionData = [
            'user_id'             => $user->id,
            'provider'            => $this->provider->getName(),
            'type'                => $type,
            'fiat_currency'       => $fiatCurrency,
            'fiat_amount'         => $fiatAmount,
            'crypto_currency'     => $cryptoCurrency,
            'wallet_address'      => $walletAddress,
            'status'              => RampSession::STATUS_PENDING,
            'provider_session_id' => $providerResult['session_id'],
            'metadata'            => [
                'checkout_url' => $providerResult['checkout_url'],
                'provider'     => $providerResult['metadata'] ?? [],
            ],
        ];

        $metadata = $providerResult['metadata'] ?? [];
        if (isset($metadata['stripe_session_id'])) {
            $sessionData['stripe_session_id'] = $metadata['stripe_session_id'];
        }
        if (isset($metadata['client_secret'])) {
            $sessionData['stripe_client_secret'] = $metadata['client_secret'];
        }

        $session = RampSession::create($sessionData);

        Log::info('Ramp session created', [
            'session_id' => $session->id,
            'user_id'    => $user->id,
            'type'       => $type,
            'provider'   => $this->provider->getName(),
        ]);

        return $session;
    }

    /**
     * Refresh session status from the provider if non-terminal. Wraps the
     * update in a row lock and checks for terminal state after the remote
     * call, so a webhook that arrived during the fetch is not clobbered.
     */
    public function getSessionStatus(RampSession $session): RampSession
    {
        if (! in_array($session->status, [RampSession::STATUS_PENDING, RampSession::STATUS_PROCESSING], true)) {
            return $session;
        }

        if (! $session->provider_session_id) {
            return $session;
        }

        $providerStatus = $this->provider->getSessionStatus($session->provider_session_id);

        return DB::transaction(function () use ($session, $providerStatus) {
            /** @var RampSession $fresh */
            $fresh = RampSession::where('id', $session->id)->lockForUpdate()->first();

            if (in_array($fresh->status, [
                RampSession::STATUS_COMPLETED,
                RampSession::STATUS_FAILED,
                RampSession::STATUS_EXPIRED,
            ], true)) {
                return $fresh;
            }

            $fresh->update([
                'status'        => $providerStatus['status'],
                'crypto_amount' => $providerStatus['crypto_amount'] ?? $fresh->crypto_amount,
                'metadata'      => array_merge($fresh->metadata ?? [], $providerStatus['metadata']),
            ]);

            return $fresh;
        });
    }

    /**
     * Handle a webhook from a provider. Verifies the signature first (never
     * parses untrusted JSON before verification), then delegates payload shape
     * handling to the provider's own normalizeWebhookPayload().
     *
     * @throws InvalidWebhookSignatureException
     */
    public function handleWebhook(
        RampProviderInterface $provider,
        string $rawBody,
        string $signatureHeader,
    ): void {
        $validator = $provider->getWebhookValidator();
        if (! $validator($rawBody, $signatureHeader)) {
            throw new InvalidWebhookSignatureException();
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            throw new RuntimeException('Webhook body is not valid JSON');
        }

        $normalized = $provider->normalizeWebhookPayload($payload);
        if ($normalized === null) {
            return;
        }

        DB::transaction(function () use ($provider, $normalized, $payload) {
            /** @var RampSession|null $session */
            $session = RampSession::where('provider', $provider->getName())
                ->where('provider_session_id', $normalized['session_id'])
                ->lockForUpdate()
                ->first();

            if (! $session) {
                Log::warning('Ramp webhook: session not found', [
                    'provider'   => $provider->getName(),
                    'session_id' => $normalized['session_id'],
                ]);
                return;
            }

            if (in_array($session->status, [
                RampSession::STATUS_COMPLETED,
                RampSession::STATUS_FAILED,
                RampSession::STATUS_EXPIRED,
            ], true)) {
                Log::info('Ramp webhook skipped — session already terminal', [
                    'session_id' => $session->id,
                    'status'     => $session->status,
                ]);
                return;
            }

            $cryptoAmount = $normalized['crypto_amount'] !== null
                ? (float) $normalized['crypto_amount']
                : $session->crypto_amount;

            $session->update([
                'status'        => $normalized['status'],
                'crypto_amount' => $cryptoAmount,
                'metadata'      => array_merge($session->metadata ?? [], [
                    'webhook' => [
                        'received_at'        => now()->toIso8601String(),
                        'event'              => $payload['type'] ?? null,
                        'snapshot'           => $normalized['raw'],
                        'session_transition' => $session->getOriginal('status') . ' → ' . $normalized['status'],
                    ],
                ]),
            ]);

            Log::info('Ramp webhook processed', [
                'session_id'         => $session->id,
                'provider'           => $provider->getName(),
                'status'             => $normalized['status'],
                'stripe_event_type'  => $payload['type'] ?? null,
                'session_transition' => $session->getOriginal('status') . ' → ' . $normalized['status'],
            ]);
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, RampSession>
     */
    public function getUserSessions(User $user, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return RampSession::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    private function validateRampParams(string $type, string $fiatCurrency, string $fiatAmount, string $cryptoCurrency): void
    {
        if (! in_array($type, ['on', 'off'], true)) {
            throw new RuntimeException('Invalid transaction type. Use "on" for buying crypto or "off" for selling.');
        }

        $supported = $this->provider->getSupportedCurrencies();

        if (! in_array($fiatCurrency, $supported['fiatCurrencies'], true)) {
            throw new RuntimeException(
                "{$fiatCurrency} is not supported by {$this->provider->getName()}. "
                . 'Supported: ' . implode(', ', $supported['fiatCurrencies'])
            );
        }

        if (! in_array($cryptoCurrency, $supported['cryptoCurrencies'], true)) {
            throw new RuntimeException(
                "{$cryptoCurrency} is not available through {$this->provider->getName()}. "
                . 'Supported: ' . implode(', ', $supported['cryptoCurrencies'])
            );
        }

        /** @var numeric-string $minStr */
        $minStr = (string) $supported['limits']['minAmount'];
        /** @var numeric-string $maxStr */
        $maxStr = (string) $supported['limits']['maxAmount'];
        $min = bcadd($minStr, '0', 2);
        $max = bcadd($maxStr, '0', 2);
        /** @var numeric-string $fiatAmount */
        $amount = bcadd($fiatAmount, '0', 2);

        if (bccomp($amount, $min, 2) < 0 || bccomp($amount, $max, 2) > 0) {
            throw new RuntimeException("Amount must be between {$fiatCurrency} {$min} and {$fiatCurrency} {$max}.");
        }
    }
}
```

- [ ] **Step 2: Run PHPStan on the service**

Run: `XDEBUG_MODE=off ./vendor/bin/phpstan analyse app/Domain/Ramp/Services/RampService.php --level=8 --memory-limit=2G`
Expected: `[OK] No errors`.

- [ ] **Step 3: Run existing ramp tests to confirm nothing regressed**

Run: `./vendor/bin/pest tests/Feature/Api/Ramp/`
Expected: All pre-existing tests still pass. (Note: `handleWebhook` signature changed — any test that called it directly via `RampService::handleWebhook('onramper', $payloadArray, $sig)` would now fail. We address the webhook controller in Task 10, and add new tests there. If an existing test breaks on this signature change, note it and we'll fix it in Task 10's tests.)

- [ ] **Step 4: Commit**

```bash
git add app/Domain/Ramp/Services/RampService.php
git commit -m "refactor(ramp): make RampService webhook + validation provider-agnostic

- handleWebhook() now takes a RampProviderInterface instance, raw body,
  and signature header. Verifies signature first, decodes JSON after,
  then delegates to provider->normalizeWebhookPayload(). DB updates
  under lockForUpdate() with terminal-state idempotency.
- validateRampParams() reads from provider->getSupportedCurrencies()
  instead of global config, so Stripe's USDC-only restriction is
  enforced correctly (fixes 'user requests BTC on Stripe' silent leak).
- getSessionStatus() wraps the update in DB::transaction + lockForUpdate
  and re-checks terminal state after the remote call, fixing a race
  where a webhook arriving during the fetch could be clobbered.

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 9: Create `RampProviderRegistry` and bind it

**Files:**
- Create: `app/Domain/Ramp/Registries/RampProviderRegistry.php`
- Modify: `app/Providers/RampServiceProvider.php`

- [ ] **Step 1: Create the registry class**

Create `app/Domain/Ramp/Registries/RampProviderRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Registries;

use App\Domain\Ramp\Contracts\RampProviderInterface;

/**
 * Maps provider names (as used in webhook URL path segments and the
 * `provider` column of ramp_sessions) to provider instances.
 *
 * Used by RampWebhookController to resolve the correct provider for an
 * incoming webhook independently of config('ramp.default_provider'), so
 * webhooks for the non-active provider still land correctly during a swap.
 */
final class RampProviderRegistry
{
    /** @param array<string, RampProviderInterface> $providers */
    public function __construct(
        private readonly array $providers,
    ) {
    }

    public function resolve(string $name): ?RampProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->providers);
    }
}
```

- [ ] **Step 2: Bind it in `RampServiceProvider`**

Open `app/Providers/RampServiceProvider.php`. Add these imports at the top with the existing `use` statements:

```php
use App\Domain\Ramp\Registries\RampProviderRegistry;
```

Then add this binding inside `register()` after the existing `RampService` binding:

```php
        $this->app->singleton(RampProviderRegistry::class, function ($app) {
            return new RampProviderRegistry([
                'onramper'      => new OnramperProvider($app->make(OnramperClient::class)),
                'stripe_bridge' => new StripeBridgeProvider($app->make(StripeBridgeService::class)),
                'mock'          => new MockRampProvider(),
            ]);
        });
```

- [ ] **Step 3: Run PHPStan**

Run: `XDEBUG_MODE=off ./vendor/bin/phpstan analyse app/Domain/Ramp/Registries/ app/Providers/RampServiceProvider.php --level=8 --memory-limit=2G`
Expected: `[OK] No errors`.

- [ ] **Step 4: Commit**

```bash
git add app/Domain/Ramp/Registries/RampProviderRegistry.php app/Providers/RampServiceProvider.php
git commit -m "feat(ramp): add RampProviderRegistry for name-based provider resolution

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 10: Refactor `RampWebhookController` with raw body + registry

**Files:**
- Modify: `app/Http/Controllers/Api/V1/RampWebhookController.php`
- Create: `tests/Feature/Api/Ramp/RampWebhookRawBodyTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Api/Ramp/RampWebhookRawBodyTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\RampSession;
use App\Models\User;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    config([
        'ramp.default_provider'                 => 'stripe_bridge',
        'services.stripe.secret'                => 'sk_test_fake_key',
        'services.stripe.bridge_webhook_secret' => 'whsec_test_fake',
    ]);
});

function signStripeBody(string $body, string $secret = 'whsec_test_fake', ?int $timestamp = null): string
{
    $timestamp = $timestamp ?? time();
    $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    return "t={$timestamp},v1={$signature}";
}

it('returns 404 for an unknown provider name', function () {
    $response = $this->postJson('/api/v1/ramp/webhook/pretend_provider', []);

    $response->assertStatus(404);
    $response->assertJsonPath('error.code', 'UNKNOWN_PROVIDER');
});

it('returns 400 when the Stripe signature is invalid', function () {
    $response = $this->call(
        'POST',
        '/api/v1/ramp/webhook/stripe_bridge',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => 't=' . time() . ',v1=deadbeef'],
        '{"id":"evt_test","type":"crypto_onramp_session.updated"}'
    );

    $response->assertStatus(400);
    $response->assertJsonPath('error.code', 'INVALID_SIGNATURE');
});

it('returns 400 when the Stripe-Signature header is missing', function () {
    $response = $this->call(
        'POST',
        '/api/v1/ramp/webhook/stripe_bridge',
        [],
        [],
        [],
        [],
        '{"id":"evt_test"}'
    );

    $response->assertStatus(400);
});

it('returns 200 for a valid signature on an ignored event type', function () {
    $fixtures = require base_path('tests/Fixtures/stripe_bridge_webhooks.php');
    $body = json_encode($fixtures['unrelated_event']);

    $response = $this->call(
        'POST',
        '/api/v1/ramp/webhook/stripe_bridge',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => signStripeBody($body)],
        $body
    );

    $response->assertOk();
});

it('processes a valid session.completed event and updates the session row', function () {
    $user = User::factory()->create();
    $session = RampSession::create([
        'user_id'             => $user->id,
        'provider'            => 'stripe_bridge',
        'type'                => 'on',
        'fiat_currency'       => 'USD',
        'fiat_amount'         => 100.0,
        'crypto_currency'     => 'USDC',
        'wallet_address'      => '0xabcdef',
        'status'              => RampSession::STATUS_PENDING,
        'provider_session_id' => 'cos_test_abc123',
    ]);

    $fixtures = require base_path('tests/Fixtures/stripe_bridge_webhooks.php');
    $body = json_encode($fixtures['session_completed']);

    $response = $this->call(
        'POST',
        '/api/v1/ramp/webhook/stripe_bridge',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => signStripeBody($body)],
        $body
    );

    $response->assertOk();
    $session->refresh();
    expect($session->status)->toBe(RampSession::STATUS_COMPLETED)
        ->and($session->crypto_amount)->toBe(98.5);
});

it('preserves raw body bytes end-to-end (no JSON re-encoding)', function () {
    // This body has unusual whitespace and key ordering that Laravel's
    // decode+encode cycle would rewrite. If the signature was computed
    // over these exact bytes, only a raw-body-preserving controller passes.
    $body = "{\n  \"id\":\"evt_raw_test\",\n  \"type\":\"crypto_onramp_session.updated\",\n  \"data\":{\"object\":{\"id\":\"cos_raw_test\",\"status\":\"initialized\"}}\n}";
    $header = signStripeBody($body);

    $response = $this->call(
        'POST',
        '/api/v1/ramp/webhook/stripe_bridge',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => $header],
        $body
    );

    // The signature should verify successfully against the raw body.
    // Session won't be found, but the controller should have progressed
    // past signature verification — so 200, not 400.
    $response->assertOk();
});

it('idempotent: replaying a completed event on a terminal session is a no-op', function () {
    $user = User::factory()->create();
    $session = RampSession::create([
        'user_id'             => $user->id,
        'provider'            => 'stripe_bridge',
        'type'                => 'on',
        'fiat_currency'       => 'USD',
        'fiat_amount'         => 100.0,
        'crypto_currency'     => 'USDC',
        'wallet_address'      => '0xabcdef',
        'status'              => RampSession::STATUS_COMPLETED,
        'crypto_amount'       => 98.5,
        'provider_session_id' => 'cos_test_abc123',
    ]);

    $fixtures = require base_path('tests/Fixtures/stripe_bridge_webhooks.php');
    $body = json_encode($fixtures['session_completed']);

    $response = $this->call(
        'POST',
        '/api/v1/ramp/webhook/stripe_bridge',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => signStripeBody($body)],
        $body
    );

    $response->assertOk();
    $session->refresh();
    expect($session->status)->toBe(RampSession::STATUS_COMPLETED);
});
```

- [ ] **Step 2: Run the tests — they should fail (controller still uses old shape)**

Run: `./vendor/bin/pest tests/Feature/Api/Ramp/RampWebhookRawBodyTest.php`
Expected: Most tests FAIL (signature verification, JSON re-encoding, unknown provider handling all broken under the old controller).

- [ ] **Step 3: Replace the controller**

Open `app/Http/Controllers/Api/V1/RampWebhookController.php` and replace the file with:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ramp\Exceptions\InvalidWebhookSignatureException;
use App\Domain\Ramp\Registries\RampProviderRegistry;
use App\Domain\Ramp\Services\RampService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use RuntimeException;

class RampWebhookController extends Controller
{
    public function __construct(
        private readonly RampService $rampService,
        private readonly RampProviderRegistry $registry,
    ) {
    }

    #[OA\Post(
        path: '/api/v1/ramp/webhook/{provider}',
        operationId: 'v1RampWebhook',
        tags: ['Ramp'],
        summary: 'Ramp provider webhook (Stripe Bridge, Onramper, etc.)',
        parameters: [
            new OA\Parameter(name: 'provider', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['stripe_bridge', 'onramper', 'mock'])),
        ]
    )]
    #[OA\Response(response: 200, description: 'Webhook processed')]
    #[OA\Response(response: 400, description: 'Invalid signature or malformed body')]
    #[OA\Response(response: 404, description: 'Unknown provider')]
    #[OA\Response(response: 500, description: 'Processing error')]
    public function handle(Request $request, string $provider): JsonResponse
    {
        $providerInstance = $this->registry->resolve($provider);
        if (! $providerInstance) {
            return response()->json([
                'error' => ['code' => 'UNKNOWN_PROVIDER', 'message' => "Unknown ramp provider: {$provider}"],
            ], 404);
        }

        $rawBody = $request->getContent();
        $signatureHeader = (string) $request->header($providerInstance->getWebhookSignatureHeader(), '');

        try {
            $this->rampService->handleWebhook($providerInstance, $rawBody, $signatureHeader);
        } catch (InvalidWebhookSignatureException $e) {
            Log::warning('Ramp webhook signature rejected', [
                'provider' => $provider,
                'ip'       => $request->ip(),
            ]);

            return response()->json([
                'error' => ['code' => 'INVALID_SIGNATURE', 'message' => 'Webhook signature verification failed'],
            ], 400);
        } catch (RuntimeException $e) {
            Log::error('Ramp webhook processing failed', [
                'provider' => $provider,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'error' => ['code' => 'WEBHOOK_ERROR', 'message' => 'Webhook processing failed'],
            ], 500);
        }

        return response()->json(['status' => 'ok']);
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `./vendor/bin/pest tests/Feature/Api/Ramp/RampWebhookRawBodyTest.php`
Expected: All tests PASS.

- [ ] **Step 5: Run PHPStan on the controller**

Run: `XDEBUG_MODE=off ./vendor/bin/phpstan analyse app/Http/Controllers/Api/V1/RampWebhookController.php --level=8 --memory-limit=2G`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/V1/RampWebhookController.php tests/Feature/Api/Ramp/RampWebhookRawBodyTest.php
git commit -m "refactor(ramp): preserve raw body through webhook controller

- Reads \$request->getContent() instead of \$request->all(); webhook
  signatures must verify against exact bytes Stripe/Onramper signed
- Resolves provider via RampProviderRegistry (name-based, independent
  of config('ramp.default_provider'))
- Splits InvalidWebhookSignatureException (400) from RuntimeException
  (500) so signature failures and processing errors are distinguishable
  in logs and metrics
- Reads the provider-specific header name from provider->getWebhookSignatureHeader()

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 11: Route `RampController::supported()` through the provider interface

The controller currently has a hardcoded `if ($provider === 'stripe_bridge')` branch that calls `StripeBridgeService::getSupportedCurrencies()` directly. That bypass is a platform leak — now that `RampProviderInterface::getSupportedCurrencies()` returns the keyed shape, we can delete the branch entirely and route through the interface.

**Files:**
- Modify: `app/Http/Controllers/Api/V1/RampController.php`

- [ ] **Step 1: Write a test for the generic path**

Append to `tests/Feature/Api/Ramp/StripeBridgeRampTest.php`:

```php
it('GET /api/v1/ramp/supported returns stripe_bridge capabilities via the interface', function () {
    $user = \App\Models\User::factory()->create(['kyc_status' => 'approved']);
    \Laravel\Sanctum\Sanctum::actingAs($user, ['read', 'write', 'delete']);

    config(['ramp.default_provider' => 'stripe_bridge']);

    $response = $this->getJson('/api/v1/ramp/supported');

    $response->assertOk();
    $response->assertJsonPath('data.provider', 'stripe_bridge');
    $response->assertJsonPath('data.crypto_currencies', ['USDC']);
    expect($response->json('data.fiat_currencies'))->toContain('USD');
});
```

- [ ] **Step 2: Run the test to confirm the current state works (should still pass with the existing bypass)**

Run: `./vendor/bin/pest tests/Feature/Api/Ramp/StripeBridgeRampTest.php --filter="supported returns stripe_bridge"`
Expected: PASS. We want the test to still pass after the refactor — it's a regression check, not a failing spec.

- [ ] **Step 3: Replace `supported()` and remove the `StripeBridgeService` dependency**

Open `app/Http/Controllers/Api/V1/RampController.php`. Remove the `StripeBridgeService` import:

```php
use App\Domain\Ramp\Services\StripeBridgeService;
```

Remove the constructor dependency on `StripeBridgeService`:

```php
    public function __construct(
        private readonly RampService $rampService,
    ) {
    }
```

Replace the entire `supported()` method (currently ~40 lines) with the generic version:

```php
    public function supported(): JsonResponse
    {
        $providerName = (string) config('ramp.default_provider');
        $provider = app(\App\Domain\Ramp\Contracts\RampProviderInterface::class);
        $supported = $provider->getSupportedCurrencies();

        return response()->json([
            'data' => [
                'provider'          => $providerName,
                'fiat_currencies'   => $supported['fiatCurrencies'],
                'crypto_currencies' => $supported['cryptoCurrencies'],
                'modes'             => [
                    ['type' => 'on', 'label' => 'Buy Crypto'],
                    ['type' => 'off', 'label' => 'Sell Crypto'],
                ],
                'limits' => [
                    'min_amount'  => $supported['limits']['minAmount'],
                    'max_amount'  => $supported['limits']['maxAmount'],
                    'daily_limit' => $supported['limits']['dailyLimit'],
                ],
            ],
        ]);
    }
```

- [ ] **Step 4: Run the Stripe tests + general ramp tests**

Run: `./vendor/bin/pest tests/Feature/Api/Ramp/`
Expected: All tests PASS. The new `supported()` test still passes; no Stripe-specific branch needed.

- [ ] **Step 5: Run PHPStan**

Run: `XDEBUG_MODE=off ./vendor/bin/phpstan analyse app/Http/Controllers/Api/V1/RampController.php --level=8 --memory-limit=2G`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/V1/RampController.php tests/Feature/Api/Ramp/StripeBridgeRampTest.php
git commit -m "refactor(ramp): route RampController::supported through provider interface

Removes the hardcoded \`if (\$provider === 'stripe_bridge')\` branch and
drops the direct StripeBridgeService dependency from the controller.
All providers now expose capabilities via the same RampProviderInterface
method, so the controller no longer needs to know about any specific
provider implementation.

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 12: Shared provider-contract test

**Files:**
- Create: `tests/Feature/Api/Ramp/RampProviderContractTest.php`

- [ ] **Step 1: Create the contract test**

Create `tests/Feature/Api/Ramp/RampProviderContractTest.php`:

```php
<?php

declare(strict_types=1);

use App\Domain\Ramp\Contracts\RampProviderInterface;
use App\Domain\Ramp\Providers\MockRampProvider;
use App\Domain\Ramp\Providers\OnramperProvider;
use App\Domain\Ramp\Providers\StripeBridgeProvider;

uses(Tests\TestCase::class);

/**
 * Parameterized contract test — every RampProviderInterface implementation
 * must pass every assertion here. Adding a new provider = adding one line
 * to the dataset below.
 */
dataset('ramp_providers', [
    'mock'          => fn () => app(MockRampProvider::class),
    'onramper'      => fn () => app(OnramperProvider::class),
    'stripe_bridge' => fn () => app(StripeBridgeProvider::class),
]);

beforeEach(function () {
    config([
        'services.stripe.secret'                => 'sk_test_fake_key',
        'services.stripe.bridge_webhook_secret' => 'whsec_test_fake',
        'ramp.providers.onramper.api_key'       => 'fake_onramper_key',
        'ramp.providers.onramper.secret_key'    => 'fake_onramper_secret',
    ]);
});

it('returns a non-empty provider name', function (RampProviderInterface $provider) {
    expect($provider->getName())->toBeString()->not->toBeEmpty();
})->with('ramp_providers');

it('returns a non-empty webhook signature header name', function (RampProviderInterface $provider) {
    expect($provider->getWebhookSignatureHeader())->toBeString()->not->toBeEmpty();
})->with('ramp_providers');

it('returns supported currencies in the canonical keyed shape', function (RampProviderInterface $provider) {
    $supported = $provider->getSupportedCurrencies();

    expect($supported)
        ->toHaveKeys(['fiatCurrencies', 'cryptoCurrencies', 'modes', 'limits']);

    expect($supported['fiatCurrencies'])->toBeArray()->not->toBeEmpty();
    expect($supported['cryptoCurrencies'])->toBeArray()->not->toBeEmpty();
    expect($supported['limits'])->toHaveKeys(['minAmount', 'maxAmount', 'dailyLimit']);
    expect($supported['limits']['minAmount'])->toBeInt();
    expect($supported['limits']['maxAmount'])->toBeInt();
    expect($supported['limits']['dailyLimit'])->toBeInt();
})->with('ramp_providers');

it('getWebhookValidator returns a callable that takes (rawBody, signatureHeader)', function (RampProviderInterface $provider) {
    $validator = $provider->getWebhookValidator();

    expect(is_callable($validator))->toBeTrue();

    // Missing/empty signature must always reject (no matter the provider)
    expect($validator('{}', ''))->toBeFalse();
})->with('ramp_providers');

it('normalizeWebhookPayload returns null for an empty payload', function (RampProviderInterface $provider) {
    expect($provider->normalizeWebhookPayload([]))->toBeNull();
})->with('ramp_providers');

it('normalizeWebhookPayload returns null for garbage payload', function (RampProviderInterface $provider) {
    expect($provider->normalizeWebhookPayload(['unrelated' => 'junk']))->toBeNull();
})->with('ramp_providers');
```

- [ ] **Step 2: Run the contract tests**

Run: `./vendor/bin/pest tests/Feature/Api/Ramp/RampProviderContractTest.php`
Expected: All 18 test executions (6 tests × 3 providers) PASS.

- [ ] **Step 3: Run PHPStan on the test file**

Run: `XDEBUG_MODE=off ./vendor/bin/phpstan analyse tests/Feature/Api/Ramp/RampProviderContractTest.php --level=8 --memory-limit=2G`
Expected: `[OK] No errors`.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Api/Ramp/RampProviderContractTest.php
git commit -m "test(ramp): add parameterized provider-contract test suite

Every RampProviderInterface implementation must pass the same set of
contract tests. Adding a new provider is one line in the dataset;
the whole test suite automatically runs against it.

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 13: End-to-end integration tests for session + quotes + supported

**Files:**
- Modify: `tests/Feature/Api/Ramp/StripeBridgeRampTest.php`

- [ ] **Step 1: Append the session-creation test**

Append to `tests/Feature/Api/Ramp/StripeBridgeRampTest.php`:

```php
it('POST /api/v1/ramp/session persists stripe_session_id and stripe_client_secret', function () {
    $user = \App\Models\User::factory()->create(['kyc_status' => 'approved']);
    \Laravel\Sanctum\Sanctum::actingAs($user, ['read', 'write', 'delete']);

    config(['ramp.default_provider' => 'stripe_bridge']);

    Http::fake([
        'api.stripe.com/v1/crypto/onramp_sessions' => Http::response([
            'id'            => 'cos_test_created',
            'client_secret' => 'cs_live_secret_fake',
            'status'        => 'initialized',
        ], 200),
    ]);

    $response = $this->postJson('/api/v1/ramp/session', [
        'type'            => 'on',
        'fiat_currency'   => 'USD',
        'fiat_amount'     => 100,
        'crypto_currency' => 'USDC',
        'wallet_address'  => '0x1234567890abcdef1234567890abcdef12345678',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.provider', 'stripe_bridge');

    $session = \App\Models\RampSession::where('user_id', $user->id)->first();
    expect($session)->not->toBeNull()
        ->and($session->provider)->toBe('stripe_bridge')
        ->and($session->stripe_session_id)->toBe('cos_test_created')
        ->and($session->stripe_client_secret)->toBe('cs_live_secret_fake');
});
```

- [ ] **Step 2: Append the quotes test**

```php
it('GET /api/v1/ramp/quotes returns a single-element array with canonical payment methods', function () {
    $user = \App\Models\User::factory()->create(['kyc_status' => 'approved']);
    \Laravel\Sanctum\Sanctum::actingAs($user, ['read', 'write', 'delete']);

    config(['ramp.default_provider' => 'stripe_bridge']);

    Http::fake([
        'api.stripe.com/v1/crypto/onramp_sessions/quotes*' => Http::response([
            'source_amount'      => '100.00',
            'destination_amount' => '98.50000000',
            'fees'               => ['total_fee' => '1.50', 'network_fee' => '0.50'],
        ], 200),
    ]);

    $response = $this->getJson('/api/v1/ramp/quotes?type=on&fiat=USD&amount=100&crypto=USDC');

    $response->assertOk();
    $response->assertJsonPath('data.provider', 'stripe_bridge');
    $quotes = $response->json('data.quotes');
    expect($quotes)->toHaveCount(1);
    expect($quotes[0]['payment_methods'])->toBe(['card', 'bank_transfer']);
});
```

- [ ] **Step 3: Append the BTC validation test (provider-aware validation)**

```php
it('rejects BTC on Stripe with a provider-named error message', function () {
    $user = \App\Models\User::factory()->create(['kyc_status' => 'approved']);
    \Laravel\Sanctum\Sanctum::actingAs($user, ['read', 'write', 'delete']);

    config(['ramp.default_provider' => 'stripe_bridge']);

    $response = $this->postJson('/api/v1/ramp/session', [
        'type'            => 'on',
        'fiat_currency'   => 'USD',
        'fiat_amount'     => 100,
        'crypto_currency' => 'BTC',
        'wallet_address'  => 'bc1qxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    ]);

    $response->assertStatus(422);
    $errorMessage = $response->json('error.message');
    expect($errorMessage)->toContain('BTC')->toContain('stripe_bridge');
});
```

- [ ] **Step 4: Append the poll-after-webhook race regression test**

```php
it('getSessionStatus does not clobber a webhook-set terminal status', function () {
    $user = \App\Models\User::factory()->create(['kyc_status' => 'approved']);
    \Laravel\Sanctum\Sanctum::actingAs($user, ['read', 'write', 'delete']);

    config(['ramp.default_provider' => 'stripe_bridge']);

    // Seed a session that a webhook has already marked as completed
    $session = \App\Models\RampSession::create([
        'user_id'             => $user->id,
        'provider'            => 'stripe_bridge',
        'type'                => 'on',
        'fiat_currency'       => 'USD',
        'fiat_amount'         => 100.0,
        'crypto_currency'     => 'USDC',
        'wallet_address'      => '0xabcdef',
        'status'              => \App\Models\RampSession::STATUS_COMPLETED,
        'crypto_amount'       => 98.5,
        'provider_session_id' => 'cos_test_terminal',
    ]);

    // Stripe happens to still say payment_pending (the webhook is ahead of the poll)
    Http::fake([
        'api.stripe.com/v1/crypto/onramp_sessions/cos_test_terminal' => Http::response([
            'id'                 => 'cos_test_terminal',
            'status'             => 'payment_pending',
            'destination_amount' => null,
        ], 200),
    ]);

    $response = $this->getJson("/api/v1/ramp/session/{$session->id}");

    $response->assertOk();
    $session->refresh();
    // Must remain COMPLETED — the pending-path early return plus the terminal
    // state check inside the transaction prevent the clobber.
    expect($session->status)->toBe(\App\Models\RampSession::STATUS_COMPLETED)
        ->and($session->crypto_amount)->toBe(98.5);
});
```

- [ ] **Step 5: Append the non-custody regression test**

```php
it('non-custody: a successful completion webhook writes zero rows to wallet/ledger tables', function () {
    $user = \App\Models\User::factory()->create();
    $session = \App\Models\RampSession::create([
        'user_id'             => $user->id,
        'provider'            => 'stripe_bridge',
        'type'                => 'on',
        'fiat_currency'       => 'USD',
        'fiat_amount'         => 100.0,
        'crypto_currency'     => 'USDC',
        'wallet_address'      => '0xabcdef',
        'status'              => \App\Models\RampSession::STATUS_PENDING,
        'provider_session_id' => 'cos_test_noncustody',
    ]);

    // Count ledger / wallet rows before. Table names may need adjusting to match
    // the actual FinAegis ledger schema — use whatever table holds user balances.
    $balancesBefore = \Illuminate\Support\Facades\DB::table('account_balances')->count();
    $entriesBefore = \Illuminate\Support\Facades\DB::table('transactions')->count();

    $fixtures = require base_path('tests/Fixtures/stripe_bridge_webhooks.php');
    $fixtures['session_completed']['data']['object']['id'] = 'cos_test_noncustody';
    $body = json_encode($fixtures['session_completed']);

    $response = $this->call(
        'POST',
        '/api/v1/ramp/webhook/stripe_bridge',
        [],
        [],
        [],
        ['HTTP_STRIPE_SIGNATURE' => signStripeBody($body)],
        $body
    );

    $response->assertOk();

    expect(\Illuminate\Support\Facades\DB::table('account_balances')->count())->toBe($balancesBefore);
    expect(\Illuminate\Support\Facades\DB::table('transactions')->count())->toBe($entriesBefore);
})->skip('adjust table names to match actual ledger schema during implementation');
```

Note: the `skip()` call is intentional — the ledger/balance table names in FinAegis may differ from `account_balances` / `transactions`. During implementation, inspect the actual schema, update the table names, and remove the `->skip(...)` call. This is the one place where real table names can't be hard-coded from the spec alone.

- [ ] **Step 6: Run the full Stripe Bridge test file**

Run: `./vendor/bin/pest tests/Feature/Api/Ramp/StripeBridgeRampTest.php`
Expected: All tests PASS (non-custody test is skipped).

- [ ] **Step 7: Inspect actual ledger tables and un-skip the non-custody test**

Run: `ls database/migrations/ | grep -iE "account_balances|ledger|transactions|wallet"`
Note the actual table names that hold user balance state. Edit the non-custody test: replace `account_balances` and `transactions` with the real table names, and remove the `->skip(...)`.

Run: `./vendor/bin/pest tests/Feature/Api/Ramp/StripeBridgeRampTest.php --filter="non-custody"`
Expected: PASS. The ramp webhook path must never touch balance tables.

- [ ] **Step 8: Commit**

```bash
git add tests/Feature/Api/Ramp/StripeBridgeRampTest.php
git commit -m "test(ramp): add end-to-end Stripe Bridge integration tests

- Session creation persists stripe_session_id + stripe_client_secret
- Quotes endpoint returns single-element array with canonical payment methods
- BTC request on Stripe returns provider-named validation error
- Poll-after-webhook race does not clobber terminal state
- Non-custody regression: zero writes to balance/ledger tables on
  successful ramp completion

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 14: Config and env var documentation

**Files:**
- Modify: `config/services.php`
- Modify: `config/ramp.php`
- Modify: `.env.example`
- Modify: `.env.zelta.example`
- Modify: `.env.production.example`

- [ ] **Step 1: Verify `config/services.php` has both Stripe keys**

Open `config/services.php` and find the `'stripe' =>` entry. Ensure it looks like this (add the `secret` line if missing):

```php
    'stripe' => [
        'secret'                => env('STRIPE_SECRET'),
        'bridge_webhook_secret' => env('STRIPE_BRIDGE_WEBHOOK_SECRET'),
    ],
```

- [ ] **Step 2: Add the deprecation comment to `config/ramp.php`**

Open `config/ramp.php`. Find the `supported_fiat` and `supported_crypto` entries and add a comment above them:

```php
    /*
     * Mock-provider fallback defaults only. Production providers return
     * their own supported currency lists via RampProviderInterface::getSupportedCurrencies().
     * RampService::validateRampParams() reads from the provider, not from here.
     */
    'supported_fiat'   => ['USD', 'EUR', 'GBP'],
    'supported_crypto' => ['USDC', 'USDT', 'ETH', 'BTC'],
```

- [ ] **Step 3: Update `.env.example`**

Open `.env.example`. Find a location appropriate for Stripe env vars (likely near other payment provider keys). Add:

```env

# ----- Ramp provider selection -----
# Options: onramper, stripe_bridge, mock
RAMP_PROVIDER=mock

# ----- Stripe (shared across ramp, KYC checkout, future cards integration) -----
STRIPE_SECRET=
STRIPE_BRIDGE_WEBHOOK_SECRET=

# ----- Onramper (alternative ramp provider) -----
ONRAMPER_API_KEY=
ONRAMPER_SECRET_KEY=
ONRAMPER_BASE_URL=https://api.onramper.com
ONRAMPER_SUCCESS_REDIRECT_URL=
```

If any of these keys already exist in the file, leave the existing line alone and just add the ones that are missing.

- [ ] **Step 4: Repeat for `.env.zelta.example` and `.env.production.example`**

Apply the same additions to `.env.zelta.example` and `.env.production.example`. In `.env.production.example`, set `RAMP_PROVIDER=stripe_bridge` as the production default instead of `mock`.

- [ ] **Step 5: Commit**

```bash
git add config/services.php config/ramp.php .env.example .env.zelta.example .env.production.example
git commit -m "chore(ramp): document Stripe Bridge env vars and clarify ramp config

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 15: Full code quality run

- [ ] **Step 1: Run php-cs-fixer on all touched files**

Run: `./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php app/Domain/Ramp app/Http/Controllers/Api/V1/RampWebhookController.php app/Http/Controllers/Api/V1/RampController.php app/Providers/RampServiceProvider.php tests/Feature/Api/Ramp`
Expected: Fixes applied (or no changes if everything was already clean).

- [ ] **Step 2: Run PHPStan on the entire Ramp domain**

Run: `XDEBUG_MODE=off ./vendor/bin/phpstan analyse app/Domain/Ramp app/Http/Controllers/Api/V1/RampWebhookController.php app/Http/Controllers/Api/V1/RampController.php --level=8 --memory-limit=2G`
Expected: `[OK] No errors`.

- [ ] **Step 3: Run the full ramp test suite in parallel**

Run: `./vendor/bin/pest tests/Feature/Api/Ramp/ --parallel`
Expected: All tests PASS.

- [ ] **Step 4: Run PHPCS (CI uses v4)**

Run: `./vendor/bin/phpcs --standard=PSR12 app/Domain/Ramp app/Http/Controllers/Api/V1/RampWebhookController.php app/Http/Controllers/Api/V1/RampController.php app/Providers/RampServiceProvider.php`
Expected: No style errors.

- [ ] **Step 5: Commit any php-cs-fixer adjustments**

```bash
git add -u
git commit -m "style(ramp): apply php-cs-fixer to Stripe Bridge changes

Co-Authored-By: Claude <noreply@anthropic.com>"
```

If there is nothing to commit (all files were already clean), skip this step.

---

## Task 16: Write the mobile feedback note

**Files:**
- Create: `docs/MOBILE_FEEDBACK_STRIPE_BRIDGE.md`

- [ ] **Step 1: Create the feedback note**

Create `docs/MOBILE_FEEDBACK_STRIPE_BRIDGE.md`:

```markdown
# Mobile Feedback: Stripe Bridge Ramp Integration

**Date:** 2026-04-12
**Backend PR:** (link at PR open time)
**Handover doc:** `docs/BACKEND_HANDOVER_CARDS_KYC_RAMP.md` Section 3
**Design spec:** `docs/superpowers/specs/2026-04-12-stripe-bridge-ramp-design.md`

This note captures the decisions made while implementing Section 3 of the handover doc, deviations from the handover spec (with rationale), and end-to-end test instructions for flipping `EXPO_PUBLIC_USE_MOCK=false` against the dev environment.

## TL;DR

- No mobile code changes required.
- All 5 endpoint paths, request shapes, and response field names unchanged from what the mobile app already expects.
- `provider` returns `"stripe_bridge"` when active; `paymentMethods` returns `["card", "bank_transfer"]`; `quotes` is a single-element array; `statusLabel` values match the handover spec exactly.
- Webhook path is `POST /api/v1/ramp/webhook/stripe_bridge` (platform-generic), not `/webhooks/stripe/bridge` as the handover doc suggested. See deviation #1.

## Deviations from the handover doc

### 1. Webhook path

**Handover:** `POST /webhooks/stripe/bridge`
**Actual:** `POST /api/v1/ramp/webhook/stripe_bridge`

**Rationale:** FinAegis is an open-source platform; Zelta is one tenant. Provider-specific webhook routes fragment the platform and make it harder for other tenants to use the same code paths. Keeping one generic provider-agnostic route (`/api/v1/ramp/webhook/{provider}`) means every new ramp provider slots into the same seam. No mobile change needed — mobile doesn't call this route.

### 2. Feature flag

**Handover:** Add `STRIPE_BRIDGE_ENABLED` env var.
**Actual:** Did not add. Use existing `RAMP_PROVIDER` env var.

**Rationale:** `RAMP_PROVIDER` already selects the active ramp provider (`onramper | stripe_bridge | mock`). Adding a second orthogonal flag creates two overlapping sources of truth. Setting `RAMP_PROVIDER=stripe_bridge` in the dev/preview environment accomplishes the same dark-launch effect.

### 3. Env var naming

**Handover:** `STRIPE_SECRET_KEY`
**Actual:** `STRIPE_SECRET`

**Rationale:** Standard Laravel Stripe key naming, already used by other domains (future KYC checkout, future cards integration). `STRIPE_BRIDGE_WEBHOOK_SECRET` is kept as named in the handover — it's specific to the webhook signing secret for the ramp integration.

### 4. Stripe product naming

The integration targets **Stripe Crypto Onramp** (`/v1/crypto/onramp_sessions`), which is Stripe's public fiat ↔ crypto onramp product — distinct from the Bridge stablecoin infrastructure acquired in 2024. The handover doc and mobile code use the name "Stripe Bridge" colloquially.

**Mobile impact:** None. We kept `provider: "stripe_bridge"` in the mobile-facing API to preserve compatibility with the shipped mobile code. Internal backend logs and compliance documentation use the accurate Stripe product name.

### 5. Supported crypto list is per-provider, not global

The handover lists `cryptoCurrencies: ['USDC']` for Stripe. The backend now enforces this per-provider: requesting BTC/ETH/USDT through a Stripe-backed session returns a `422` validation error with a clear message naming the active provider (e.g. `"BTC is not available through stripe_bridge. Supported: USDC"`). Mobile's existing dropdown filter based on `cryptoCurrencies` from `GET /api/v1/ramp/supported` already handles this — just confirming.

### 6. `Stripe` instead of the longer `Stripe Bridge` in quote response

The `providerName` field in the quote response returns `"Stripe"` (from `StripeBridgeService::getQuote()`), not `"Stripe Bridge"`. This matches how Stripe refers to itself. Mobile treats this as an opaque display string — no gating.

## API contract confirmations

All five mobile-facing endpoints, unchanged:

| Method | Path | Notes |
|---|---|---|
| `GET` | `/api/v1/ramp/supported` | Returns `provider: "stripe_bridge"`, `cryptoCurrencies: ["USDC"]`, `fiatCurrencies: ["USD","EUR","GBP"]` |
| `GET` | `/api/v1/ramp/quotes` | Single-element `quotes` array; `paymentMethods: ["card","bank_transfer"]` |
| `POST` | `/api/v1/ramp/session` | Returns `checkoutUrl` for `expo-web-browser`; persists `stripe_session_id` + `stripe_client_secret` server-side |
| `GET` | `/api/v1/ramp/session/{id}` | Polls Stripe when session is non-terminal; webhook races are safe (terminal state respected under row lock) |
| `GET` | `/api/v1/ramp/sessions` | No change |

## Status mapping

Verbatim from `StripeBridgeService::mapStripeStatus()`:

| Stripe status | Mobile `RampSessionStatus` | `statusLabel` |
|---|---|---|
| `initialized` | `pending` | "Waiting for payment" |
| `payment_pending` | `processing` | "Payment processing" |
| `payment_complete` | `processing` | "Sending crypto" |
| `fulfilled` | `completed` | "Completed" |
| `payment_failed` | `failed` | "Payment failed" |
| `expired` | `expired` | "Session expired" |

Mobile polls every 5 seconds and auto-stops on terminal status (`completed`, `failed`, `expired`). No change needed.

## Known limitations

- **Stripe off-ramp (sell) support** may not be fully available in Stripe's public Crypto Onramp API. If `type: "off"` returns a `422`, that's why. Consider soft-gating the sell flow in the mobile UI until we confirm support is live. Will be verified during backend integration.
- **Session expiry** is set by Stripe (typical 30 minutes). Mobile's existing expired-state handling covers this.
- **Stripe event type naming** — backend uses `crypto_onramp_session.*` as the event type prefix. If Stripe's actual event naming is slightly different (e.g. `crypto_onramp.session.*`), the backend will adjust the prefix check and webhook processing will still work — this is a one-line fix with no mobile impact.

## End-to-end test instructions

1. **Dev environment URL:** (filled in when PR opens and deployment lands)
2. **Stripe test mode:** Backend will populate `STRIPE_SECRET=sk_test_...` and `STRIPE_BRIDGE_WEBHOOK_SECRET=whsec_...` on the dev environment. Stripe test mode is completely isolated from production.
3. **Test card:** `4242 4242 4242 4242`, any future expiry, any CVC, any postcode.
4. **Expected buy flow:**
   - Open Buy Crypto → enter `$50` USD → USDC → tap "Continue"
   - App opens Stripe hosted checkout via `expo-web-browser`
   - Enter test card details, complete payment
   - Close browser; app resumes
   - Mobile polls `GET /api/v1/ramp/session/{id}` every 5s
   - Status transitions: `pending → processing → completed` within ~30 seconds
   - `cryptoAmount` populated on the final `completed` poll
5. **Debug path:** `GET /api/v1/ramp/session/{id}` has a `metadata.webhook.snapshot` field containing the most recent Stripe event payload — use this to diagnose any transition that looks wrong.
6. **If the flow hangs on `processing`:** Stripe webhook may not be reaching the dev environment. Check the Stripe dashboard webhook delivery log (Developers → Webhooks → select the ramp endpoint → Events tab). If events are being sent but the dev env returns 4xx, backend team will investigate signature verification.

## Decisions log

| # | Topic | Decision | Platform rationale |
|---|---|---|---|
| 1 | Webhook route | Keep generic `POST /api/v1/ramp/webhook/{provider}` | Platform-generic, no per-provider carve-outs |
| 2 | Signature/payload abstraction | Raw body; `normalizeWebhookPayload()` on the interface; validator signature widened to `(rawBody, signatureHeader)` | Fixes leak for all providers, not just Stripe |
| 3 | Feature flag | `RAMP_PROVIDER` env only; drop `STRIPE_BRIDGE_ENABLED` | Single source of truth |
| 4 | Env var names | `STRIPE_SECRET` + `STRIPE_BRIDGE_WEBHOOK_SECRET` | Standard Laravel Stripe key shared across domains |
| 5 | Stripe product | Target Stripe Crypto Onramp; public identifier `stripe_bridge` preserved | Matches real API; mobile compatibility preserved |
| 6 | Supported crypto | Validated against active provider's capability list, not global config | Fixes silent leak where users could request BTC on a Stripe provider |
| 7 | Quote ID | Server-generated opaque ID (Stripe's quotes are stateless) | Matches Stripe's actual API |
| 8 | Off-ramp source | Strict pass-through; no server-side ledger mutation | Non-custody compliance |
| 9 | `getSessionStatus()` | Real Stripe GET + status mapping; wraps update in transaction + lockForUpdate | Fixes clobber-to-pending bug |
| 10 | Poll + webhook race | `DB::transaction` + `lockForUpdate` + terminal-state idempotency | Correctness for all providers |
| 11 | Tests | Parameterized contract test across all providers | Future providers inherit the suite |
| 12 | OpenAPI | Updated `RampController` annotations; first OA annotation on webhook controller | Platform documentation parity |

## Questions for the mobile team

None blocking. Two optional items worth considering for future iteration:
- Do you want the backend to expose session expiry (`expiresAt`) in the session response so mobile can show a countdown? Stripe sessions have an `expires_at` field we could propagate. Not currently in the contract.
- Do you want a separate polling endpoint that's cheaper than the full session fetch? Current `GET /session/{id}` hits Stripe on every non-terminal poll. If polling rate becomes a cost concern, we could add a lightweight "webhook-only" polling endpoint that reads from DB only.
```

- [ ] **Step 2: Commit**

```bash
git add docs/MOBILE_FEEDBACK_STRIPE_BRIDGE.md
git commit -m "docs(ramp): add Stripe Bridge mobile team feedback note

Captures deviations from the handover doc, API contract confirmations,
status mapping, and the 12-entry platform-first decisions log.

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Task 17: Final integration smoke check

- [ ] **Step 1: Run the complete ramp test suite one last time**

Run: `./vendor/bin/pest tests/Feature/Api/Ramp/ --parallel`
Expected: All tests PASS. No skipped tests (non-custody test must be un-skipped from Task 13).

- [ ] **Step 2: Run PHPStan on the whole domain**

Run: `XDEBUG_MODE=off ./vendor/bin/phpstan analyse app/Domain/Ramp app/Http/Controllers/Api/V1/RampController.php app/Http/Controllers/Api/V1/RampWebhookController.php app/Providers/RampServiceProvider.php --level=8 --memory-limit=2G`
Expected: `[OK] No errors`.

- [ ] **Step 3: Run the full test suite to catch any cross-domain regressions**

Run: `./vendor/bin/pest --parallel --stop-on-failure`
Expected: PASS. If any non-ramp test fails, investigate — it may be a bind/config ordering issue from `RampServiceProvider`. Do not proceed until green.

- [ ] **Step 4: Confirm the commit history is clean**

Run: `git log --oneline main..HEAD`
Expected: Roughly 15 commits, each scoped to one task. No WIP or fixup commits. If you see messy commits, use interactive rebase locally (but never `--amend` on commits that might have been pushed).

- [ ] **Step 5: Open the PR**

Follow the standard PR open flow per `CLAUDE.md` at the repo root:

```bash
gh pr create --title "feat(ramp): Stripe Bridge integration + platform-generic webhook abstraction" --body "$(cat <<'EOF'
## Summary
- Fixes the latent bugs in the existing Stripe Bridge ramp scaffolding so the integration works against real Stripe traffic (proper `t=,v1=` signature verification, event envelope normalization, real session status fetch).
- Widens `RampProviderInterface` so webhook signature verification, payload normalization, and capability queries are provider-owned — fixes three leaky abstractions that affected every provider, not just Stripe.
- All mobile-facing API contract and response shapes unchanged.

## Test plan
- [x] Pest feature tests pass (`./vendor/bin/pest tests/Feature/Api/Ramp/ --parallel`)
- [x] Parameterized provider-contract suite passes for all 3 providers
- [x] PHPStan level 8 clean on `app/Domain/Ramp` and touched controllers
- [x] php-cs-fixer + PHPCS v4 clean
- [x] Full suite runs without regressions (`./vendor/bin/pest --parallel`)
- [ ] Dev env end-to-end test with Stripe test card `4242 4242 4242 4242` (backend + mobile)

## Design & rationale
- Spec: `docs/superpowers/specs/2026-04-12-stripe-bridge-ramp-design.md`
- Plan: `docs/superpowers/plans/2026-04-12-stripe-bridge-ramp.md`
- Mobile team feedback note: `docs/MOBILE_FEEDBACK_STRIPE_BRIDGE.md`

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Summary of commits

Expected commit sequence (~15 commits), each atomic and reversible:

1. `feat(ramp): add InvalidWebhookSignatureException`
2. `test(ramp): add Stripe webhook event fixtures`
3. `refactor(ramp): widen RampProviderInterface with raw-body webhook shape`
4. `refactor(ramp): adapt MockRampProvider to widened interface`
5. `refactor(ramp): adapt OnramperProvider to widened interface`
6. `feat(ramp): add StripeBridgeService::getSession() for status polling`
7. `fix(ramp): implement proper Stripe signature verification and event normalization`
8. `refactor(ramp): make RampService webhook + validation provider-agnostic`
9. `feat(ramp): add RampProviderRegistry for name-based provider resolution`
10. `refactor(ramp): preserve raw body through webhook controller`
11. `refactor(ramp): route RampController::supported through provider interface`
12. `test(ramp): add parameterized provider-contract test suite`
13. `test(ramp): add end-to-end Stripe Bridge integration tests`
14. `chore(ramp): document Stripe Bridge env vars and clarify ramp config`
15. `style(ramp): apply php-cs-fixer` (optional, skip if clean)
16. `docs(ramp): add Stripe Bridge mobile team feedback note`

If Task 3 (interface widening) and Task 4/5/6 (provider adaptations) are split across commits, `main` is briefly in a state where the interface is ahead of the implementations. That's fine for local commits (CI runs against the PR branch as a whole), but do not push commit 3 alone — push commits 3–6 together, or keep the push until commits 7 onward are ready.
