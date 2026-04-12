# Stripe Bridge Ramp Integration — Design

**Date:** 2026-04-12
**Status:** Design approved, ready for implementation plan
**Author:** FinAegis backend team
**Spec source:** `docs/BACKEND_HANDOVER_CARDS_KYC_RAMP.md` Section 3

## Context

The FinAegis platform exposes a provider-agnostic fiat ↔ crypto ramp subsystem at `/api/v1/ramp/*` with a `RampProviderInterface` abstraction and two existing providers (Onramper, Mock). A partial `StripeBridgeProvider` + `StripeBridgeService` scaffold already exists but has correctness bugs that would prevent it from working against real Stripe traffic. This spec fills those gaps.

The Stripe integration unlocks the longer-term Stripe Issuing cards roadmap (~6 month runway), so the work is treated as production-grade. FinAegis is not yet live — this integration will ship as part of go-live, not as a live provider swap.

The mobile client (Zelta) is already emitting `provider: "stripe_bridge"` identifiers and the `['card', 'bank_transfer', 'link']` payment method array. The mobile ↔ backend REST contract at `/api/v1/ramp/*` stays identical. Mobile never talks to Stripe directly.

### Product naming clarification

The handover doc and mobile code use the name "Stripe Bridge". The actual Stripe API being integrated is **Stripe Crypto Onramp** (`/v1/crypto/onramp_sessions`), which is Stripe's public fiat ↔ crypto onramp product — distinct from the Bridge stablecoin infrastructure acquired in 2024. We keep `stripe_bridge` as the public mobile-facing provider identifier (for compatibility with shipped mobile code) but internal docs and logs use the accurate Stripe product name.

### Platform posture

FinAegis is an open-source platform; Zelta is one tenant. All design decisions favor the platform-generic abstraction over tenant-specific carve-outs. Onramper is kept in the repo as a first-class alternative provider after this work lands, not deprecated.

## Goals

1. Correct the latent bugs in the existing `StripeBridgeProvider` + `StripeBridgeService` scaffolding so the integration works against real Stripe traffic.
2. Fix three leaky abstractions in the `RampProviderInterface` seam that currently couple `RampService` to Onramper-shaped payloads and body encoding.
3. Ship with an environment-gated rollout path via the existing `RAMP_PROVIDER` env variable (no code changes needed to switch providers).
4. Expand test coverage to a provider-contract shape so future ramp providers inherit the suite automatically.
5. Keep the mobile-facing REST contract, status enum, and response schema unchanged.
6. Maintain strict non-custody compliance: zero ledger/balance writes on the ramp code path.

## Non-goals

- Rewriting the Ramp domain as event-sourced (overkill for a provider swap).
- Adding provider-specific routes or tenant-specific env vars.
- Removing Onramper. It stays as an alternative provider.
- Building monitoring dashboards or alert rules (ops task for go-live).
- KYC payment or cards waitlist integration (handover sections 1 and 2 — separate work).

## Architecture

The domain boundary stays at `app/Domain/Ramp/`. The `RampProviderInterface` remains the central seam; this work widens the interface to three more capabilities and removes provider-specific logic from `RampService`. No new domains, no new routes, no new migrations.

### Existing scaffolding (keeping)

- `app/Domain/Ramp/Services/StripeBridgeService.php` — Stripe Crypto Onramp HTTP client with `createSession`, `getQuote`, `getSupportedCurrencies`, `mapStripeStatus`, `mapStripeStatusLabel`.
- `app/Domain/Ramp/Providers/StripeBridgeProvider.php` — implements `RampProviderInterface`.
- `app/Models/RampSession.php` + migration — already has `provider`, `provider_session_id`, `stripe_session_id`, `stripe_client_secret`, `metadata`, and the `STATUS_*` enum that matches the spec's status table.
- `app/Http/Controllers/Api/V1/RampController.php` — already branches on `config('ramp.default_provider')` in `supported()`.
- `config/ramp.php` — has a `providers.stripe_bridge` entry.
- `config/services.php` — has a `stripe.bridge_webhook_secret` entry.
- `app/Http/Controllers/Api/V1/RampWebhookController.php` — generic webhook route at `POST /api/v1/ramp/webhook/{provider}`.
- `app/Domain/Ramp/Services/RampService.php` — orchestrator injected with `RampProviderInterface`.

### Files changed

| File | Change | Approx LoC |
|---|---|---|
| `app/Domain/Ramp/Contracts/RampProviderInterface.php` | Add `normalizeWebhookPayload()`, `getWebhookSignatureHeader()`; widen `getWebhookValidator()` callable shape to `(string $rawBody, string $signatureHeader): bool` | ~15 |
| `app/Http/Controllers/Api/V1/RampWebhookController.php` | Resolve provider via new `RampProviderRegistry`. Read `$request->getContent()` (raw body) and the provider-specified header. Pass both to `RampService::handleWebhook`. Split 400/500 responses. | ~30 |
| `app/Domain/Ramp/Services/RampService.php` | New `handleWebhook` signature: accepts `RampProviderInterface`, raw body, signature header. Verify signature first, then decode JSON, then call `$provider->normalizeWebhookPayload()`. Wrap DB update in `DB::transaction()` + `lockForUpdate()`. `validateRampParams` reads from `$provider->getSupportedCurrencies()` instead of global `config('ramp.supported_*')`. Delete `normalizeWebhookStatus()` (provider owns the mapping). | ~60 |
| `app/Domain/Ramp/Providers/StripeBridgeProvider.php` | Fix `getWebhookValidator()` to parse `Stripe-Signature` header and verify `HMAC-SHA256(secret, "<ts>.<rawBody>")` with `hash_equals()`, 300-second replay window. Implement `getSessionStatus()` to call Stripe's GET endpoint. Add `normalizeWebhookPayload()` unwrapping `{type, data: {object}}` envelope. Add `getWebhookSignatureHeader()` returning `"Stripe-Signature"`. | ~80 |
| `app/Domain/Ramp/Providers/OnramperProvider.php` | Adapt `getWebhookValidator()` to two-arg shape. Add `normalizeWebhookPayload()` returning Onramper's current payload shape. Add `getWebhookSignatureHeader()` returning `"X-Onramper-Webhook-Signature"`. | ~25 |
| `app/Domain/Ramp/Providers/MockRampProvider.php` | Adapt to new interface shape. | ~15 |
| `app/Domain/Ramp/Services/StripeBridgeService.php` | Add `getSession(string $sessionId)` — `GET /v1/crypto/onramp_sessions/{id}`. Verify `createSession` off-ramp mode against Stripe's current API during implementation. | ~40 |
| `app/Domain/Ramp/Registries/RampProviderRegistry.php` | New class. Maps provider name → `RampProviderInterface` instance. Used by webhook controller to resolve provider from URL path segment. | ~40 |
| `app/Domain/Ramp/Exceptions/InvalidWebhookSignatureException.php` | New exception type. Distinguishes signature failures (400) from processing failures (500). | ~10 |
| `app/Providers/RampServiceProvider.php` (or existing ramp binding location) | Bind `RampProviderRegistry` with static provider map. `RampProviderInterface` binding (by `default_provider`) unchanged. | ~15 |
| `config/services.php` | Verify `stripe.secret` entry exists alongside `stripe.bridge_webhook_secret`. | ~5 |
| `config/ramp.php` | Comment clarifying `supported_fiat`/`supported_crypto` are mock-provider defaults only. | ~5 |
| `.env.example`, `.env.zelta.example`, `.env.production.example` | Add `STRIPE_SECRET`, `STRIPE_BRIDGE_WEBHOOK_SECRET` with comments. Clarify `RAMP_PROVIDER` options. | ~15 |
| `tests/Feature/Api/Ramp/RampProviderContractTest.php` | New shared contract test — any provider implementing `RampProviderInterface` passes. Parameterized via Pest dataset. | ~150 |
| `tests/Feature/Api/Ramp/StripeBridgeRampTest.php` | New Stripe-specific tests: signature scheme, event envelope, status mapping, validation errors, non-custody regression. | ~200 |
| `tests/Feature/Api/Ramp/RampWebhookRawBodyTest.php` | New tests covering controller refactor: raw body preservation, provider resolution, error code split. | ~100 |
| `tests/Fixtures/stripe_bridge_webhooks.php` | Realistic Stripe event fixtures (updated, completed, unknown type). | ~50 |

### Unchanged

- All 5 mobile-facing route paths and the webhook route path
- Response JSON field names (`provider`, `fiatAmount`, `cryptoAmount`, `quoteId`, `checkoutUrl`, `statusLabel`, etc.)
- `RampSession` model, `STATUS_*` constants, database migration
- `RampController` method bodies (the controller already branches on `default_provider`)

## Interface changes

### New `RampProviderInterface` shape

```php
interface RampProviderInterface
{
    public function createSession(array $params): array;

    /** Returns ['status' => STATUS_*, 'crypto_amount' => ?string, 'metadata' => array] */
    public function getSessionStatus(string $sessionId): array;

    /** Returns ['fiatCurrencies', 'cryptoCurrencies', 'modes', 'limits' => ['minAmount','maxAmount','dailyLimit']] */
    public function getSupportedCurrencies(): array;

    public function getQuotes(string $type, string $fiatCurrency, string $fiatAmount, string $cryptoCurrency): array;

    /**
     * Validates provider-specific signature scheme. Receives raw HTTP bytes
     * (not re-encoded) and the full signature header string.
     *
     * @return callable(string $rawBody, string $signatureHeader): bool
     */
    public function getWebhookValidator(): callable;

    /**
     * HTTP header name the validator should read from the incoming request.
     */
    public function getWebhookSignatureHeader(): string;

    /**
     * Unwraps a provider-specific webhook envelope into a canonical shape.
     * Returns null to ignore the event (not an error).
     *
     * @param  array<string, mixed>  $payload  Parsed JSON body
     * @return array{session_id: string, status: string, crypto_amount: ?string, raw: array<string, mixed>}|null
     */
    public function normalizeWebhookPayload(array $payload): ?array;

    public function getName(): string;
}
```

Key implications:
- Provider owns its payload shape. `RampService` never sees vendor-specific field names again.
- Provider owns its status vocabulary mapping. The stringly-typed `normalizeWebhookStatus()` match statement in `RampService` is deleted.
- Raw body + full header preserved end-to-end. Stripe's signature scheme (and any future provider that signs raw bytes) works without further interface changes.
- `null` from `normalizeWebhookPayload` is explicit "ignore this event" — returns 200 fast without a DB touch.

## Webhook controller refactor

Current controller calls `$request->all()` + passes through `json_encode(...)` before signature verification. This silently corrupts any signature computed over raw bytes (Stripe, newer providers). Even for Onramper it works by luck — Laravel's decode/encode happens to round-trip.

```php
public function handle(Request $request, string $provider): JsonResponse
{
    $providerInstance = $this->registry->resolve($provider);
    if (! $providerInstance) {
        return response()->json(['error' => ['code' => 'UNKNOWN_PROVIDER']], 404);
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
        return response()->json(['error' => ['code' => 'INVALID_SIGNATURE']], 400);
    } catch (RuntimeException $e) {
        Log::error('Ramp webhook processing failed', [
            'provider' => $provider,
            'error'    => $e->getMessage(),
        ]);
        return response()->json(['error' => ['code' => 'WEBHOOK_ERROR']], 500);
    }

    return response()->json(['received' => true]);
}
```

## `RampService::handleWebhook` refactor

```php
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
        return;  // ignored event type
    }

    DB::transaction(function () use ($provider, $normalized, $payload) {
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
            return;  // terminal — idempotent no-op
        }

        $session->update([
            'status'        => $normalized['status'],
            'crypto_amount' => $normalized['crypto_amount'] ?? $session->crypto_amount,
            'metadata'      => array_merge($session->metadata ?? [], [
                'webhook' => [
                    'received_at' => now()->toIso8601String(),
                    'event'       => $payload['type'] ?? null,
                    'snapshot'    => $normalized['raw'],
                ],
            ]),
        ]);
    });
}
```

## Stripe provider implementations

### Signature verification

Stripe header format: `Stripe-Signature: t=<ts>,v1=<hmac1>,v0=<legacy>[,...]`. Verify by computing `HMAC-SHA256(secret, "<ts>.<rawBody>")` and comparing against each `v1` entry with `hash_equals()`. 300-second replay window.

```php
public function getWebhookValidator(): callable
{
    return function (string $rawBody, string $signatureHeader): bool {
        $secret = (string) config('services.stripe.bridge_webhook_secret', '');
        if ($secret === '') {
            return ! app()->environment('production');
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $element) {
            [$key, $value] = array_pad(explode('=', trim($element), 2), 2, '');
            $parts[$key][] = $value;
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

        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    };
}
```

### Payload normalization

Events we care about: `crypto_onramp_session.updated`, `crypto_onramp_session.completed`. Everything else returns `null`.

```php
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
    $internalStatus = $this->service->mapStripeStatus($stripeStatus);

    $cryptoAmount = null;
    if (isset($object['destination_amount']) && is_numeric($object['destination_amount'])) {
        $cryptoAmount = bcadd((string) $object['destination_amount'], '0', 8);
    }

    return [
        'session_id'    => $sessionId,
        'status'        => $internalStatus,
        'crypto_amount' => $cryptoAmount,
        'raw'           => $object,
    ];
}
```

### Session fetch

Today `StripeBridgeProvider::getSessionStatus()` returns hardcoded `pending`, which causes `RampService::getSessionStatus()` to clobber webhook-updated DB state back to pending on the next poll. Fix by implementing a real Stripe GET:

```php
public function getSessionStatus(string $sessionId): array
{
    $stripeSession = $this->service->getSession($sessionId);

    return [
        'status'        => $this->service->mapStripeStatus($stripeSession['status']),
        'crypto_amount' => $stripeSession['destination_amount'],
        'metadata'      => [
            'provider'      => 'stripe_bridge',
            'stripe_status' => $stripeSession['status'],
        ],
    ];
}
```

`StripeBridgeService::getSession(string $sessionId): array` calls `GET https://api.stripe.com/v1/crypto/onramp_sessions/{id}` and returns `['status', 'destination_amount', 'raw']`, throwing `RuntimeException` on 4xx/5xx.

### Poll vs webhook race

`RampService::getSessionStatus()` already has logic to refresh from the provider when session status is pending/processing. Add a `lockForUpdate()` + re-check inside the transaction: if the session moved to a terminal state between the provider call and the DB write (e.g. webhook arrived), skip the write. ~5 lines.

### Off-ramp mode

`StripeBridgeService::createSession()` currently sets `mode => off_ramp` for `type === 'off'`. Verify against Stripe's current API during implementation. Stripe's public Crypto Onramp may not fully support off-ramp yet; if so, return a clear validation error from `createSession()` and document the limitation in the mobile feedback note. The public `type: 'on'|'off'` contract stays the same regardless.

## Provider-aware validation

`RampService::validateRampParams()` stops reading `config('ramp.supported_fiat')` / `config('ramp.supported_crypto')` and instead reads from `$this->provider->getSupportedCurrencies()`. Error messages name the active provider. Limits also come from the provider's response.

`config('ramp.supported_fiat')` and `config('ramp.supported_crypto')` remain in the config file as mock-provider defaults, with a comment clarifying their reduced scope.

## Config & environment variables

### `config/services.php`

```php
'stripe' => [
    'secret'                => env('STRIPE_SECRET'),
    'bridge_webhook_secret' => env('STRIPE_BRIDGE_WEBHOOK_SECRET'),
],
```

### `config/ramp.php`

No structural change. One comment added to `supported_fiat`/`supported_crypto` noting they're mock-provider defaults only.

### Env var docs

```env
# Ramp provider selection: onramper | stripe_bridge | mock
RAMP_PROVIDER=mock

# Stripe (shared with KYC checkout, future cards integration)
STRIPE_SECRET=sk_test_...
STRIPE_BRIDGE_WEBHOOK_SECRET=whsec_...

# Onramper (alternative ramp provider)
ONRAMPER_API_KEY=
ONRAMPER_SECRET_KEY=
ONRAMPER_BASE_URL=https://api.onramper.com
ONRAMPER_SUCCESS_REDIRECT_URL=
```

Updated in `.env.example`, `.env.zelta.example`, `.env.production.example`.

### Provider binding

A new `RampProviderRegistry` is bound in the ramp service provider with a static map of name → provider class. Used by the webhook controller for name-based resolution (since the webhook URL carries the provider name). The existing `RampProviderInterface` binding via `config('ramp.default_provider')` is unchanged — that binding drives mobile request handling.

## Database

**Zero migrations.** `ramp_sessions` already has every column needed. The `stripe_session_id` and `stripe_client_secret` columns are Stripe-specific on a generic table; they stay — generalizing them now would be scope creep, and the `metadata` JSON is the documented escape hatch for future providers.

## Testing strategy

### Contract tests (new, ~150 LoC)

`tests/Feature/Api/Ramp/RampProviderContractTest.php` — parameterized Pest dataset across all providers (`onramper`, `stripe_bridge`, `mock`). Every provider implementing `RampProviderInterface` must pass:

1. Returns a non-empty provider name.
2. `getSupportedCurrencies()` returns the canonical shape.
3. `createSession()` returns the canonical result shape.
4. `getSessionStatus()` returns the canonical status shape.
5. `getWebhookValidator()` rejects an invalid signature.
6. `normalizeWebhookPayload()` returns `null` for an unknown event.
7. `normalizeWebhookPayload()` returns the canonical shape for a known event.

Adding a future provider = adding one line to the dataset.

### Stripe-specific tests (new, ~200 LoC)

`tests/Feature/Api/Ramp/StripeBridgeRampTest.php`:

1. Accepts a valid `Stripe-Signature` with fresh timestamp.
2. Rejects a tampered body (valid header, mutated body).
3. Rejects an old timestamp (replay window check).
4. Rejects a header with no `v1=` entry.
5. `crypto_onramp_session.updated` event → status transitions in DB.
6. `crypto_onramp_session.completed` event → terminal status + `crypto_amount` written.
7. Unknown event type → 200 + no DB write.
8. Idempotency: replay on terminal state is a no-op.
9. Poll + webhook race: `lockForUpdate` + terminal check holds.
10. `GET /api/v1/ramp/supported` returns `provider: "stripe_bridge"` when configured.
11. `GET /api/v1/ramp/quotes` returns a single-element array with `paymentMethods: ['card', 'bank_transfer', 'link']`.
12. `POST /api/v1/ramp/session` persists `stripe_session_id` + `stripe_client_secret`.
13. `GET /api/v1/ramp/session/{id}` refreshes from Stripe when non-terminal.
14. `validateRampParams` rejects `BTC` when active provider is Stripe (provider-aware validation).
15. **Non-custody regression:** assert zero rows written to balance/ledger tables after a successful `crypto_onramp_session.completed` webhook.

### Webhook raw body tests (new, ~100 LoC)

`tests/Feature/Api/Ramp/RampWebhookRawBodyTest.php` — covers the controller refactor at HTTP level:

1. Raw body preserved end-to-end (unusual whitespace/key ordering test against a spy provider).
2. Unknown provider name → 404.
3. Missing signature header → 400.
4. Invalid JSON after valid signature → 500.
5. `InvalidWebhookSignatureException` → 400; `RuntimeException` → 500.
6. Rate limit still applies.

### Fixtures

`tests/Fixtures/stripe_bridge_webhooks.php` — three realistic Stripe event envelopes (updated, completed, unknown type) matching Stripe's actual documented JSON shape. Source of truth for what a real Stripe webhook looks like in this codebase.

### Out of scope for tests

- Real HTTP calls to `api.stripe.com` (all `Http::fake()`).
- Mobile integration (deliverable flip at the end).
- Onramper regression tests beyond the shared contract suite.

## Compliance guardrails (non-custody)

Hard invariants enforced by code review and by the regression test in the Stripe test suite:

- `RampService` does not call `WalletService`, `BalanceService`, or any ledger write. Ramp sessions track status metadata only.
- `getQuote()` passes Stripe's response fields through verbatim. No fee markup arithmetic anywhere in the ramp code path.
- The mobile-supplied `wallet_address` is passed to Stripe unmodified; the backend never derives wallet addresses for ramp sessions, never holds private keys, never signs anything.
- The non-custody regression test (item 15 in the Stripe test suite) asserts zero rows written to `wallet_balances`, `ledger_entries`, or any fund-holding table after a successful ramp completion webhook.

This matches the non-custody description we're submitting to Stripe's compliance team.

## Verification & handoff

Platform is not yet live; this ships as part of go-live.

1. Merge PR with CI green. `RAMP_PROVIDER` default stays `mock` for local/test runs.
2. Backend verification in Stripe test mode: operator sets `RAMP_PROVIDER=stripe_bridge`, `STRIPE_SECRET=sk_test_...`, `STRIPE_BRIDGE_WEBHOOK_SECRET=whsec_...` on the shared dev environment. Registers `<env>/api/v1/ramp/webhook/stripe_bridge` as a webhook in Stripe's dashboard. Runs the full flow through the REST API with test card `4242 4242 4242 4242`, confirms the session lifecycle lands in `completed`.
3. Mobile integration: mobile team flips `EXPO_PUBLIC_USE_MOCK=false`, points at the same environment, runs buy + sell flows. Discrepancies fed back and fixed.
4. Ships with go-live. `RAMP_PROVIDER=stripe_bridge` in production config. Onramper env vars remain in `.env.*.example` as an alternative for self-hosted deployments.

### Onramper's role

Onramper stays in the repo as a second first-class provider. Self-hosted FinAegis deployments can pick either via `RAMP_PROVIDER`. Both providers pass the contract test suite.

### Monitoring

Pre-launch: three structured log fields added (`provider`, `stripe_event_type`, `session_transition`). Dashboards and alert rules are a separate go-live ops task, not part of this PR. The structured logs ensure whoever builds those dashboards has the right fields to pivot on.

## Mobile feedback note

A companion doc (`docs/MOBILE_FEEDBACK_STRIPE_BRIDGE.md`) will be written when the PR is ready. Contents:

**1. Deviations from the handover doc (with rationale)**
- Webhook path: `POST /api/v1/ramp/webhook/stripe_bridge`, not `/webhooks/stripe/bridge`. Reason: FinAegis is platform-generic; provider-specific routes fragment the platform.
- `STRIPE_BRIDGE_ENABLED` flag not added. Reason: `RAMP_PROVIDER` env already handles per-environment gating.
- Env var: `STRIPE_SECRET`, not `STRIPE_SECRET_KEY`. Reason: standard Laravel Stripe key shared across future KYC checkout and cards integrations.
- Product naming: integration targets Stripe **Crypto Onramp** (`/v1/crypto/onramp_sessions`). `provider: "stripe_bridge"` preserved in the mobile-facing API for compatibility; backend logs and compliance docs use the accurate Stripe product name.
- Supported crypto list is now per-provider. Stripe Crypto Onramp currently supports USDC only. Mobile should filter its dropdown by `cryptoCurrencies` from `GET /api/v1/ramp/supported` (which it already does).

**2. API contract confirmations (no mobile changes needed)**
- All 5 endpoint paths, request shapes, and response field names unchanged.
- `provider` returns `"stripe_bridge"` when active.
- `paymentMethods` returns `["card", "bank_transfer", "link"]`.
- `quotes` is a single-element array.
- `statusLabel` values match the handover spec's table.
- `checkoutUrl` is the Stripe Crypto Onramp hosted URL; open in `expo-web-browser`.

**3. Status mapping (final)**

Verbatim from `StripeBridgeService::mapStripeStatus`, matching the handover spec's table.

**4. Known limitations**
- Stripe off-ramp (sell) support may not be fully available in Stripe's public Crypto Onramp API yet. If `type: "off"` returns a 422, that's why. Worth a soft-gate in the mobile UI until confirmed.
- Session expiry window is set by Stripe (typical 30 minutes). Mobile's existing expired-state handling covers this.

**5. End-to-end test instructions**
- Dev environment URL (filled in at PR time).
- Test card: `4242 4242 4242 4242`, any CVC, any future expiry.
- Expected flow: Buy Crypto → enter $50 USD → USDC → open checkout → complete test payment → return to app → polling `pending → processing → completed` within ~30s → `cryptoAmount` populated.
- Debug path: `GET /api/v1/ramp/session/{id}`, check `metadata.webhook.snapshot` for the most recent Stripe event payload.

**6. Decisions log**

Full 12-entry decisions table from the brainstorming session, with platform-first rationale per decision.

## Open implementation-time questions

These don't block the design; they're verification points for the implementation phase:

- **Stripe off-ramp mode parameter naming.** `StripeBridgeService::createSession()` sets `mode: off_ramp` for `type === 'off'`. Verify against Stripe's current API during implementation; adapt the request body if Stripe uses different field names, without changing the public `type` contract.
- **Stripe's hosted checkout URL format.** `StripeBridgeService::buildCheckoutUrl()` currently constructs `https://crypto-onramp.stripe.com/crypto/onramp/{client_secret}`. Confirm this is still the correct format; Stripe may return a `redirect_url` field directly.
- **Stripe event type naming.** The design uses `crypto_onramp_session.updated` / `.completed` as the event type prefix. Verify the exact event type names in Stripe's current Crypto Onramp docs (they may use `crypto_onramp.session.*` or another variant). Adjust the prefix check in `normalizeWebhookPayload()` accordingly — this is a one-line change that doesn't affect the design shape.
- **Quote TTL.** `RampService` hardcodes `validUntil = now + 60s`. Confirm Stripe's quote freshness window during implementation; adjust if needed.

## Decisions log

| # | Topic | Decision | Rationale |
|---|---|---|---|
| 1 | Webhook route | Keep generic `POST /api/v1/ramp/webhook/{provider}` | Platform-generic, no per-provider carve-outs |
| 2 | Signature/payload abstraction | Pass raw body; add `normalizeWebhookPayload()` to `RampProviderInterface`; widen validator to `(rawBody, signatureHeader)` | Fixes leak for all providers |
| 3 | Feature flag | Use existing `RAMP_PROVIDER` env; drop redundant `STRIPE_BRIDGE_ENABLED` | Single source of truth |
| 4 | Env var names | `STRIPE_SECRET` + `STRIPE_BRIDGE_WEBHOOK_SECRET` | Reuse standard Laravel Stripe key across domains |
| 5 | Stripe product | Target Stripe **Crypto Onramp**; public identifier `stripe_bridge` unchanged | Matches real API; mobile compat preserved |
| 6 | Supported crypto validation | Validate against the active provider's `getSupportedCurrencies()` | Today users can request BTC on a Stripe-backed provider — global config is a leak |
| 7 | Quote ID | Server-generated opaque ID (existing pattern) | Matches Stripe's stateless quote API |
| 8 | Off-ramp source | Strict pass-through; no server-side ledger mutation | Matches non-custody compliance |
| 9 | `getSessionStatus()` | Real Stripe `GET /v1/crypto/onramp_sessions/{id}` + status mapping | Fixes clobber-to-pending bug |
| 10 | Webhook race | `DB::transaction` + `lockForUpdate` + terminal-state idempotency | Robustness for all providers |
| 11 | Tests | Provider-contract test suite parameterized across providers | Future providers inherit tests |
| 12 | OpenAPI | Update existing `RampController` annotations; first OA annotation on webhook controller | Platform documentation parity |
