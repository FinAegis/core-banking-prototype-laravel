# Mobile Feedback: Stripe Bridge Ramp Integration

**Date:** 2026-04-12
**Backend PR:** (link at PR open time)
**Handover doc:** `docs/BACKEND_HANDOVER_CARDS_KYC_RAMP.md` Section 3
**Design spec:** `docs/superpowers/specs/2026-04-12-stripe-bridge-ramp-design.md`
**Implementation plan:** `docs/superpowers/plans/2026-04-12-stripe-bridge-ramp.md`

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

**Rationale:** `RAMP_PROVIDER` already selects the active ramp provider (`onramper | stripe_bridge | mock`). Adding a second orthogonal flag creates two overlapping sources of truth. Setting `RAMP_PROVIDER=stripe_bridge` in the dev/preview environment accomplishes the same effect without a second knob.

### 3. Env var naming

**Handover:** `STRIPE_SECRET_KEY`
**Actual:** `STRIPE_SECRET`

**Rationale:** Standard Laravel Stripe key naming, already used by other domains (future KYC checkout, future cards integration). `STRIPE_BRIDGE_WEBHOOK_SECRET` is kept as named in the handover — it's specific to the webhook signing secret for the ramp integration.

### 4. Stripe product naming

The integration targets **Stripe Crypto Onramp** (`/v1/crypto/onramp_sessions`), which is Stripe's public fiat ↔ crypto onramp product — distinct from the Bridge stablecoin infrastructure acquired in 2024. The handover doc and mobile code use the name "Stripe Bridge" colloquially.

**Mobile impact:** None. We kept `provider: "stripe_bridge"` in the mobile-facing API to preserve compatibility with the shipped mobile code. Internal backend logs and compliance documentation use the accurate Stripe product name.

### 5. Supported crypto list is per-provider, not global

The handover lists `cryptoCurrencies: ['USDC']` for Stripe. The backend now enforces this per-provider: requesting BTC/ETH/USDT through a Stripe-backed session returns a `422` validation error with a clear message naming the active provider (e.g. `"BTC is not available through stripe_bridge. Supported: USDC"`). Mobile's existing dropdown filter based on `cryptoCurrencies` from `GET /api/v1/ramp/supported` already handles this — just confirming.

### 6. Payment methods array contains two values

`StripeBridgeService::getQuote()` returns `paymentMethods: ['card', 'bank_transfer']`. The handover mentioned `['card', 'bank_transfer', 'link']` as the possible Stripe payment methods. Link is a Stripe hosted wallet / account product; whether it surfaces in Stripe's Crypto Onramp API depends on account settings. The backend returns whatever Stripe actually advertises for the session's quote. If mobile was rendering a pill for `link` that now never appears, that's not a bug — Stripe's quote response is authoritative.

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

- **Stripe off-ramp (sell) support** may not be fully available in Stripe's public Crypto Onramp API. If `type: "off"` returns a `422`, that's why. Consider soft-gating the sell flow in the mobile UI until we confirm support is live. Backend integration with Stripe test mode will verify this during go-live prep.
- **Session expiry** is set by Stripe (typical 30 minutes). Mobile's existing expired-state handling covers this.
- **Stripe event type naming** — backend uses `crypto_onramp_session.*` as the event type prefix. If Stripe's actual event naming is slightly different (e.g. `crypto_onramp.session.*`), the backend will adjust the prefix check in `StripeBridgeProvider::normalizeWebhookPayload()` and webhook processing will still work — this is a one-line fix with no mobile impact.
- **RampProviderRegistry eager instantiation** — a follow-up refactor is planned to make the registry resolve providers lazily. Today every ramp test needs fake API keys for all three providers even if it only exercises one. Not a mobile-visible issue.

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
