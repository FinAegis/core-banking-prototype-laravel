# HyperSwitch Deposits Operator Runbook

Operator-only runbook for the HyperSwitch card-deposit rail (PRs #1118/#1119). HyperSwitch is **experimental but wired**: opt-in via `HYPERSWITCH_ENABLED`, off by default — Stripe remains the default deposit rail when the flag is off.

## When to use this runbook

- A user reports *"my card deposit succeeded but the balance never showed up"* (webhook didn't land, or the credit failed after the claim).
- An intent is sitting in `completion_failed` (the loud-failure state added in PR #1119 — payment succeeded at HyperSwitch but our credit/audit step threw).
- Pre-prod sanity check before flipping `HYPERSWITCH_ENABLED=true` on a new environment.
- Rolling deposits back to Stripe after a HyperSwitch incident.

## Environment & credentials

Required `.env` keys (see `config/hyperswitch.php`):

```ini
HYPERSWITCH_ENABLED=true                          # off by default; Stripe is the default rail
HYPERSWITCH_BASE_URL=https://sandbox.hyperswitch.io  # self-hosted or cloud URL in production
HYPERSWITCH_API_KEY=<server-side secret key>
HYPERSWITCH_PUBLISHABLE_KEY=<client-side key>
HYPERSWITCH_WEBHOOK_SECRET=<webhook signing secret>
HYPERSWITCH_PROFILE_ID=<business profile id>
HYPERSWITCH_RETURN_URL=https://<domain>/wallet    # falls back to url('/wallet') when empty
```

`php artisan ops:verify-env` gates these at deploy time: with `HYPERSWITCH_ENABLED=true` it FAILs when `HYPERSWITCH_API_KEY` or `HYPERSWITCH_WEBHOOK_SECRET` is empty (with the flag off the check reports SKIP and Stripe stays the rail).

**Webhook signature**: HMAC-SHA512 over the raw body, sent in the `x-webhook-signature-512` header, verified with `hash_equals`. An empty `HYPERSWITCH_WEBHOOK_SECRET` **fails closed in production** (every webhook 401s, logged as `HyperSwitch: HYPERSWITCH_WEBHOOK_SECRET not set in production`) and passes open in non-production environments.

## HyperSwitch dashboard configuration

Point the webhook at (on the `api.` subdomain the `/api` prefix is dropped):

```
https://<your-domain>/api/webhooks/hyperswitch
```

Route: `routes/api.php` → `HyperSwitchWebhookController::handle`. Handled event types: `payment_succeeded`, `payment_failed` (both mutate state), `payment_processing`, `refund_succeeded`, `refund_failed`, `dispute_opened` (log-only today — refunds and disputes are NOT automated; reconcile manually).

## How a deposit flows

1. `DepositController::store` validates `amount` (1–10,000) + `currency` (USD/EUR/GBP), converts to integer minor units via `bcmul` (never float), and — when `config('hyperswitch.enabled')` — calls `HyperSwitchPaymentService::startDeposit`. The JSON response shape matches the Stripe path, plus `processor: hyperswitch`.
2. `startDeposit` (payment-first, so a failed HyperSwitch API call leaves nothing dangling):
   - creates the HyperSwitch payment (`deposit_uuid` round-trips in payment metadata so the webhook can correlate),
   - initiates the `PaymentDepositAggregate`,
   - writes a `hyperswitch_deposit_intents` row (`status = pending`, amount/currency/account captured at creation). The intent lives on the **central/default connection**, not the tenant connection.
3. The client confirms the payment with HyperSwitch; `DepositController::confirm` is a no-op on this rail (deposits complete asynchronously via the webhook).
4. `payment_succeeded` webhook credits the account and completes the aggregate (next section).

## Intent lifecycle

```
pending ──(payment_succeeded claim)──▶ processing ──▶ completed
   │                                       │
   │                                       └──(credit/persist threw)──▶ completion_failed   ← operator reconciliation
   └──(payment_failed)──▶ failed
```

Statuses on `App\Domain\Payment\Models\HyperSwitchDepositIntent`:

| Status | Meaning |
|---|---|
| `pending` | Intent recorded; awaiting the webhook |
| `processing` | A webhook claimed the intent; tenant-side credit + completion in flight |
| `completed` | Credit applied AND deposit aggregate completed |
| `failed` | HyperSwitch reported `payment_failed`; aggregate fail-marked, no credit |
| `completion_failed` | Payment **succeeded** at HyperSwitch, claim committed, but the credit or the aggregate persist **threw**. Failures are never disguised as completed (PR #1119) — this state exists precisely so you can find them |

## Webhook idempotency model

`payment_succeeded` processing is two-phase by design (multi-connection deadlock rule from CLAUDE.md — never one transaction spanning the default + tenant connections):

1. **Claim transaction (default connection)**: `processed_webhook_events` `firstOrCreate(provider='hyperswitch', event_id)` — a replayed `event_id` is a no-op. When HyperSwitch omits `event_id`, the dedupe key falls back to `hs_succeeded_<payment_id>` / `hs_failed_<payment_id>`. The intent row is read under `lockForUpdate` and must be `pending`; it is flipped to `processing` inside this transaction, which gates concurrent webhooks for the same payment (two *different* event_ids for one payment still credit exactly once — the second blocks on the row lock, then reads non-pending and bails).
2. **Credit + audit (tenant connection, after the claim committed)**: `AccountCreditService::credit` (row-locked transaction on the balance's own connection, integer minor units, amount **from the stored intent, never the webhook payload** — a tampered-amount payload changes nothing), then `PaymentDepositAggregate::completeDeposit` persist, then intent → `completed`. Credit-first is deliberate: a persist failure leaves a reconcilable audit gap, never lost money.

If step 2 throws: intent → `completion_failed`, `Log::error('HyperSwitch: deposit completion failed after claim — intent marked completion_failed', …)`, and the endpoint still returns 200 (the dedupe row is already committed; a HyperSwitch retry would be a no-op — recovery is on the operator, not the provider).

## Reconciling `completion_failed`

Find them:

```sql
SELECT * FROM hyperswitch_deposit_intents WHERE status = 'completion_failed';
```

Then per intent:

1. **Read the error.** Grep logs for `deposit completion failed after claim` with the `payment_id`. The exception message tells you which step threw.
2. **Determine whether the credit landed** (credit runs before the aggregate persist):
   - Exception points at the credit (e.g. an `account_balances` insert / FK violation, SQLSTATE 1452) → **credit did NOT land**. Re-run both steps.
   - Exception points at the event-store persist / projectors → **credit DID land**. Only complete the aggregate; do **NOT** re-credit (double-credit risk).
3. **Confirm the dedupe row** in `processed_webhook_events` (`provider='hyperswitch'`) exists for the event — it should; that's why a webhook retry won't fix this for you.
4. **Manual re-credit / completion** from tinker:
   ```php
   php artisan tinker
   >>> $i = \App\Domain\Payment\Models\HyperSwitchDepositIntent::where('hyperswitch_payment_id', '<payment_id>')->first();
   >>> // ONLY if the credit did not land (step 2):
   >>> app(\App\Domain\Account\Services\AccountCreditService::class)->credit($i->account_uuid, $i->amount_cents, $i->currency);
   >>> // Always: complete the deposit aggregate
   >>> \App\Domain\Payment\Aggregates\PaymentDepositAggregate::retrieve($i->deposit_uuid)->completeDeposit('hs_' . $i->hyperswitch_payment_id)->persist();
   >>> $i->update(['status' => \App\Domain\Payment\Models\HyperSwitchDepositIntent::STATUS_COMPLETED]);
   ```
5. **Verify**: the user's `account_balances` row for the intent's currency reflects the amount, and the intent reads `completed`.

### The `account_balances` FK pitfall

`account_balances.asset_code` has a foreign key to `assets.code`. `AccountCreditService` creates the balance row on first credit for a currency — if the asset (e.g. `EUR`) is not seeded in `assets`, the insert dies with SQLSTATE 1452 and the intent lands in `completion_failed`. This exact failure mode hid behind a swallowed catch ("balance null") during the wire-up; it is now loud. Fix: seed the asset, then re-run step 4. (MultiConnection tests must seed the asset for the same reason.)

## Kill switch / rollback to Stripe

```ini
HYPERSWITCH_ENABLED=false
```

then `php artisan config:cache` (if config is cached) and restart workers. Effects:

- New card deposits immediately route through the Stripe path in `DepositController` — no code change, same response shape.
- The webhook route stays registered and the handlers do **not** check the flag, so in-flight HyperSwitch deposits (intents still `pending`) complete normally when their webhooks arrive. Do not delete pending intents during rollback.
- Sweep `completion_failed` + stale `pending` intents (pending for hours = webhook never arrived; check the HyperSwitch dashboard delivery log, or `HyperSwitchPaymentService::getPaymentStatus($paymentId)` from tinker) before considering the rollback done.

## Not yet wired

Routing-intelligence/analytics, connector management UI, refund/dispute automation. `HyperSwitchPaymentService::refund()` and `listConnectors()` exist but nothing calls them — refunds are a manual dashboard operation today, and the webhook only logs `refund_*`/`dispute_opened`.

## Files referenced

- `app/Domain/Payment/Services/HyperSwitch/HyperSwitchPaymentService.php` — orchestration (`startDeposit`, `getPaymentStatus`, `refund`)
- `app/Domain/Payment/Services/HyperSwitch/HyperSwitchClient.php` — REST wrapper
- `app/Domain/Payment/Models/HyperSwitchDepositIntent.php` — intent + statuses
- `app/Domain/Account/Services/AccountCreditService.php` — tenant-connection balance credit
- `app/Http/Controllers/DepositController.php` — opt-in routing branch
- `app/Http/Controllers/Api/Webhook/HyperSwitchWebhookController.php` — webhook claim + credit
- `config/hyperswitch.php`
- `tests/MultiConnection/` — deadlock + asset-FK regression coverage
