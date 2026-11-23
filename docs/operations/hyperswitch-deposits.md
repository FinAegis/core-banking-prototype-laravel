# HyperSwitch Deposits Operator Runbook

This runbook is for operators. It covers the HyperSwitch rail for card deposits, which was wired in PRs #1118 and #1119. HyperSwitch is **experimental**, but it is connected to the real deposit flow. It is switched on through `HYPERSWITCH_ENABLED` and is off by default. While the flag is off, Stripe stays the deposit rail.

## When to use this runbook

- A user says "my card deposit succeeded but the balance never showed up". Either the webhook did not arrive, or the credit failed after the webhook was claimed.
- An intent is in `completion_failed`. PR #1119 added this state for a payment that succeeded at HyperSwitch while our credit or audit step threw an exception.
- You are checking a new environment before you set `HYPERSWITCH_ENABLED=true`.
- You are moving deposits back to Stripe after a HyperSwitch incident.

## Environment and credentials

These `.env` keys are required. `config/hyperswitch.php` reads them.

```ini
HYPERSWITCH_ENABLED=true                          # off by default; Stripe is the default rail
HYPERSWITCH_BASE_URL=https://sandbox.hyperswitch.io  # self-hosted or cloud URL in production
HYPERSWITCH_API_KEY=<server-side secret key>
HYPERSWITCH_PUBLISHABLE_KEY=<client-side key>
HYPERSWITCH_WEBHOOK_SECRET=<webhook signing secret>
HYPERSWITCH_PROFILE_ID=<business profile id>
HYPERSWITCH_RETURN_URL=https://<domain>/wallet    # falls back to url('/wallet') when empty
```

`php artisan ops:verify-env` checks these keys at deploy time. With `HYPERSWITCH_ENABLED=true`, it reports FAIL when `HYPERSWITCH_API_KEY` or `HYPERSWITCH_WEBHOOK_SECRET` is empty. With the flag off, the check reports SKIP and Stripe stays the rail.

**Webhook signature.** HyperSwitch signs the raw body with HMAC-SHA512 and sends the signature in the `x-webhook-signature-512` header. The controller compares it with `hash_equals`. An empty `HYPERSWITCH_WEBHOOK_SECRET` **fails closed** in every environment except `local` and `testing`, including staging and demo. Every webhook then gets 401, and the log says `HyperSwitch: HYPERSWITCH_WEBHOOK_SECRET is not set; webhook refused`. Only `local` and `testing` accept unsigned webhooks.

## HyperSwitch dashboard configuration

Point the webhook at this URL. On the `api.` subdomain the `/api` prefix is dropped.

```
https://<your-domain>/api/webhooks/hyperswitch
```

The route in `routes/api.php` calls `HyperSwitchWebhookController::handle`. Two event types change state, `payment_succeeded` and `payment_failed`. The handler only logs `payment_processing`, `refund_succeeded`, `refund_failed` and `dispute_opened`. Refunds and disputes are not automated, so you reconcile them by hand.

## How a deposit flows

1. `DepositController::store` validates `amount` (1 to 10,000) and `currency` (USD, EUR or GBP). It converts the amount to integer minor units with `bcmul` and never uses a float. When `config('hyperswitch.enabled')` is true, it calls `HyperSwitchPaymentService::startDeposit`. The JSON response has the same shape as on the Stripe path, plus `processor: hyperswitch`.
2. `startDeposit` creates the payment first, so a failed HyperSwitch call leaves nothing behind. It takes three steps.
   - It creates the HyperSwitch payment. The `deposit_uuid` travels in the payment metadata, so the webhook can match it later.
   - It starts the `PaymentDepositAggregate`.
   - It writes a `hyperswitch_deposit_intents` row with `status = pending` and records the amount, currency and account at creation. The intent lives on the **central default connection**, not on the tenant connection.
3. The client confirms the payment with HyperSwitch. `DepositController::confirm` does nothing on this rail, because the deposit completes later through the webhook.
4. The `payment_succeeded` webhook credits the account and completes the aggregate, as the next sections explain.

## Intent lifecycle

```
pending ──(payment_succeeded claim)──▶ processing ──▶ completed
   │                                       │
   │                                       └──(credit/persist threw)──▶ completion_failed   ← operator reconciliation
   └──(payment_failed)──▶ failed
```

`App\Domain\Payment\Models\HyperSwitchDepositIntent` has these statuses:

| Status | Meaning |
|---|---|
| `pending` | The intent is recorded and waits for the webhook. |
| `processing` | A webhook claimed the intent, and the credit and completion on the tenant side are running. |
| `completed` | The credit was applied and the deposit aggregate was completed. |
| `failed` | HyperSwitch reported `payment_failed`. The aggregate is marked failed and nothing is credited. |
| `completion_failed` | The payment **succeeded** at HyperSwitch and the claim was committed, but the credit or the aggregate persist **threw**. Failures are never disguised as completed (PR #1119). This state exists so that you can find them. |
| `reconciled` | An operator settled a `completion_failed` case by hand and marked it in the admin panel. |

## Webhook idempotency model

`payment_succeeded` is processed in two phases on purpose. The multi-connection rule in `CLAUDE.md` forbids one transaction that spans the default and tenant connections, because it would deadlock.

1. **The claim runs in a transaction on the default connection.** `processed_webhook_events` runs `firstOrCreate(provider='hyperswitch', event_id)`, so a replayed `event_id` does nothing. When HyperSwitch leaves out `event_id`, the key falls back to `hs_succeeded_<payment_id>` or `hs_failed_<payment_id>`. The intent row is read with `lockForUpdate` and must be `pending`. The transaction changes it to `processing`, which stops concurrent webhooks for the same payment. Two *different* event ids for one payment still credit exactly once, because the second waits for the row lock, then reads a status that is no longer pending and stops.
2. **The credit and audit run on the tenant connection after the claim is committed.** `AccountCreditService::credit` runs a row-locked transaction on the balance's own connection in integer minor units. The amount comes **from the stored intent, never from the webhook payload**, so a payload with a changed amount has no effect. Then the `PaymentDepositAggregate::completeDeposit` event is persisted, and the intent becomes `completed`. The credit runs first on purpose. If the persist fails, the result is a gap in the audit trail that can be reconciled, never lost money.

If step 2 throws, the intent becomes `completion_failed` and the log gets `Log::error('HyperSwitch: deposit completion failed after claim — intent marked completion_failed', …)`. The endpoint still returns 200. The dedupe row is already committed, so a HyperSwitch retry would do nothing, and recovery is the operator's job, not the provider's.

## Reconciling `completion_failed`

Find the affected intents:

```sql
SELECT * FROM hyperswitch_deposit_intents WHERE status = 'completion_failed';
```

Then work through each intent.

1. **Read the error.** Search the logs for `deposit completion failed after claim` with the `payment_id`. The exception message tells you which step threw.
2. **Decide whether the credit landed.** The credit runs before the aggregate persist.
   - If the exception points at the credit, for example an `account_balances` insert or a foreign key violation with SQLSTATE 1452, the **credit did not land**. Run both steps again.
   - If the exception points at the event store persist or the projectors, the **credit did land**. Only complete the aggregate. Do **not** credit again, because that would credit twice.
3. **Confirm the dedupe row.** `processed_webhook_events` should have a row with `provider='hyperswitch'` for the event. That row is the reason a webhook retry will not fix this for you.
4. **Credit and complete by hand** from tinker:
   ```php
   php artisan tinker
   >>> $i = \App\Domain\Payment\Models\HyperSwitchDepositIntent::where('hyperswitch_payment_id', '<payment_id>')->first();
   >>> // ONLY if the credit did not land (step 2):
   >>> app(\App\Domain\Account\Services\AccountCreditService::class)->credit($i->account_uuid, $i->amount_cents, $i->currency);
   >>> // Always: complete the deposit aggregate
   >>> \App\Domain\Payment\Aggregates\PaymentDepositAggregate::retrieve($i->deposit_uuid)->completeDeposit('hs_' . $i->hyperswitch_payment_id)->persist();
   >>> $i->update(['status' => \App\Domain\Payment\Models\HyperSwitchDepositIntent::STATUS_COMPLETED]);
   ```
5. **Verify the result.** The user's `account_balances` row for the intent's currency should show the amount, and the intent should read `completed`.

The admin panel lists the intents under Payments, filtered to `completion_failed` by default. Its "Mark reconciled" action sets the status to `reconciled` and records a note, the operator and the time. The action does not move money. Use it only after you have checked the balance and the deposit aggregate by hand, for example when you settled the case with a refund instead of a credit.

### The `account_balances` foreign key pitfall

`account_balances.asset_code` has a foreign key to `assets.code`. `AccountCreditService` creates the balance row on the first credit in a currency. If that asset, for example `EUR`, is not seeded in `assets`, the insert fails with SQLSTATE 1452 and the intent ends in `completion_failed`. During the wire-up this failure hid behind a caught exception that only reported "balance null". It is now reported loudly. Seed the asset and then run step 4 again. The MultiConnection tests must seed the asset for the same reason.

## Switching off HyperSwitch and going back to Stripe

```ini
HYPERSWITCH_ENABLED=false
```

Then run `php artisan config:cache` if the configuration is cached, and restart the workers. This has three effects.

- New card deposits go through the Stripe path in `DepositController` at once. No code change is needed, and the response shape stays the same.
- The webhook route stays registered, and the handlers do **not** check the flag. HyperSwitch deposits in flight, whose intents are still `pending`, therefore complete normally when their webhooks arrive. Do not delete pending intents during a rollback.
- Before you call the rollback done, sweep the `completion_failed` intents and the stale `pending` ones. An intent that has been pending for hours means the webhook never arrived. Check the delivery log in the HyperSwitch dashboard, or call `HyperSwitchPaymentService::getPaymentStatus($paymentId)` from tinker.

## Not yet wired

Routing intelligence, analytics, a connector management screen and refund and dispute automation are not wired. `HyperSwitchPaymentService::refund()` and `listConnectors()` exist, but nothing calls them. Refunds are a manual operation in the HyperSwitch dashboard, and the webhook only logs `refund_*` and `dispute_opened`.

## Files referenced

- `app/Domain/Payment/Services/HyperSwitch/HyperSwitchPaymentService.php` orchestrates the rail with `startDeposit`, `getPaymentStatus` and `refund`.
- `app/Domain/Payment/Services/HyperSwitch/HyperSwitchClient.php` wraps the REST API.
- `app/Domain/Payment/Models/HyperSwitchDepositIntent.php` holds the intent and its statuses.
- `app/Domain/Account/Services/AccountCreditService.php` credits the balance on the tenant connection.
- `app/Http/Controllers/DepositController.php` contains the opt-in routing branch.
- `app/Http/Controllers/Api/Webhook/HyperSwitchWebhookController.php` claims the webhook and credits the account.
- `config/hyperswitch.php` holds the configuration.
- `tests/MultiConnection/` covers the deadlock and the asset foreign key regressions.
