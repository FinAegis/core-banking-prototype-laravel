# IAP Subscriptions Operator Runbook

This runbook is for operators. It covers mobile subscriptions bought through Apple App Store and Google Play in-app purchases (IAP), which shipped in v7.13.0 as part of Plan B. The code is in the `Iap/` folder of `app/Domain/Subscription/`. It is kept separate from the Stripe-only `SubscriptionService` on purpose, so that neither path grows into one large service.

## When to use this runbook

- A mobile user says "I paid in the App Store but I'm still on Free". The verify call was rejected, or the webhook or the outbox is lagging.
- The mobile team escalates an `ERR_SUB_002` conflict that the user cannot resolve alone.
- A GDPR erasure run stops with `IAP_RECEIPT_PEPPER must be configured`.
- Revenue dashboards lag behind store activity because outbox rows are stuck in `pending`.
- You are preparing an App Store or Play Store release that touches subscriptions.

## Environment and credentials

```ini
IAP_RECEIPT_PEPPER=<openssl rand -hex 32>     # one-way HMAC pepper — see "Never rotate" below
APPLE_JWS_VERIFICATION_BYPASS=false           # staging-only; ignored (and logged) in production
# Product-id maps (config/subscription.php → subscription.iap.{apple,google}.product_ids)
# Apple root CA: storage/app/apple/AppleRootCA-G3.cer (subscription.iap.apple.root_ca_path)
```

`php artisan ops:verify-env` checks three things at deploy time. It reports FAIL when the pepper is empty or when the Apple bypass flag is true. It also checks that the root certificate file exists and matches the pinned fingerprint.

## The verify endpoint

The mobile app calls `POST /api/v1/subscription/iap/verify`. It needs Sanctum authentication and is limited to 10 requests per minute per user (`throttle:10,1`). `IapVerifyController` passes the request to `IapSubscriptionService::verify`, which runs these steps in order.

1. **Currency check.** v1.3.x accepts only EUR. Any other currency returns `ERR_CUR_001`.
2. **Plan lookup.** The store `productId` is looked up in `config('subscription.iap.{store}.product_ids')`. An unknown id returns `ERR_SUB_001`.
3. **Receipt verification.** For Apple, the JWS certificate chain is validated as described below. For Google, the purchase token is looked up through the Play Developer API. A rejected receipt returns `ERR_SUB_001`.
4. **Family Sharing check.** This applies to Apple only. A family-shared purchase returns `ERR_SUB_002` with `kind=family_sharing_unsupported`.
5. **Account binding.** Apple's `appAccountToken` must equal `users.uuid`. Google's `obfuscatedExternalAccountId` must equal `sha256(users.uuid)`. A mismatch returns `ERR_SUB_002` with `kind=different_zelta_user`. A missing token is accepted, because the Sanctum bearer token still scopes the request to the user.
6. **Conflict with another store.** An active Stripe subscription or an active subscription in the other store returns `ERR_SUB_002` with `kind=two_stores_active`.
7. **Saving.** One transaction, with `lockForUpdate` on any existing row, does all the writes. It creates or reactivates the `iap_subscriptions` row, appends to `iap_subscription_events`, writes `iap_receipts`, logs the withdrawal consent and adds the revenue outbox row. A duplicate from the same store returns an idempotent 200. A race on the unique index across stores (`uniq_iap_active_per_user`) is also treated as the idempotent case.

## ERR_SUB_002 conflict matrix

Every IAP conflict is **HTTP 409 `ERR_SUB_002`**. The response carries `conflict.kind`, `attemptedSource` and a **non-null** `existingSubscription{ source, currentPeriodEndsAt }`. The mobile app (`subscriptionConflict.ts`) branches on `kind` and returns null for any other code. Never add a new `ERR_SUB_*` code for an IAP conflict, and never leave out `existingSubscription`. When no subscription row is on file, `existingSubscriptionFromVerified()` builds it from the source and expiry that the receipt reports.

| `kind` | Trigger | Typical support resolution |
|---|---|---|
| `two_stores_active` | The user has an active Stripe subscription or an active subscription in the other store. | The user cancels one of them in that store or in the Stripe portal. The backend never merges them. Check the state in `subscriptions` and `iap_subscriptions`. |
| `different_zelta_user` | The store subscription, or its account token, belongs to another Zelta account. | The user is signed in to the wrong Zelta account or shares a store account. They must use the original Zelta account, or cancel and buy again from the new account after the old subscription expires. |
| `family_sharing_unsupported` | The Apple receipt is family-shared (`isFamilyShared`). | Family Sharing is not supported by policy. The purchaser must subscribe directly from their own device and account. |
| `stale_receipt` | A known subscription is in a final state (`expired`, `refunded` or `cancelled`) and the receipt's expiry is in the past. | The user reinstalled the app with an old receipt. They should buy again, and the mobile app shows the resubscribe path. |

## Apple JWS chain validation

`AppleReceiptVerifier` validates the x5c certificate chain of every signed transaction. It pins the root to **Apple Root CA G3** at `storage/app/apple/AppleRootCA-G3.cer`, with the fingerprint `63343ABF…E9179`. You can check the fingerprint with `openssl x509 -fingerprint -sha256 -inform der -in storage/app/apple/AppleRootCA-G3.cer`. Any chain with a different root is rejected.

### The staging-only bypass, and why it must never reach production

`APPLE_JWS_VERIFICATION_BYPASS=true` skips signature and chain verification, so that staging can run without provisioned certificates. **It turns off authentication for the whole Apple IAP surface.** With it on, any signed-in user could send a self-made "receipt" and get a Pro subscription for free. Several layers protect against this.

- In production the flag is **ignored**. Chain validation runs anyway, and `iap.apple.jws.bypass_rejected_in_production` is logged at ERROR. Alert on this line, because it means someone tried to use the bypass.
- Outside production, every bypassed check logs `iap.apple.jws.signature_verify_bypassed` at WARNING.
- `ops:verify-env` reports FAIL for the deploy when the flag is true.
- After a deploy, run a negative smoke test. Send a known-bad JWS to `/api/v1/subscription/iap/verify` in production and expect `ERR_SUB_001`, not a new subscription.

## Webhook ingestion for Apple V2 and Google RTDN

Both store webhooks go through the same path. The event is deduplicated, the subscription is found by its pseudonymised key, and an outbox row is written. Both controllers **return 200 even when processing fails**, because the stores retry any non-2xx answer without end. The controllers log the error and acknowledge the event.

| | Apple | Google |
|---|---|---|
| Endpoint | `POST /api/webhooks/apple/notifications` | `POST /api/webhooks/google/play` |
| Payload | `{"signedPayload": "<JWS>"}` (App Store Server Notifications V2) | Pub/Sub push: `{"message":{"data":"<base64 RTDN JSON>"}}` |
| Authentication | JWS chain validation with the same pinned root as verify | An OIDC `Authorization: Bearer` JWT from `accounts.google.com`, with the `aud` claim checked |
| Deduplication | `processed_webhook_events (provider, event_id)` unique index | The same |
| Matching after erasure | `IapReceiptPseudonymiser::fingerprint(originalTransactionId)` against `original_transaction_id_hash` | `fingerprint(purchaseToken)` |

The events update `iap_subscriptions` for renewals, cancellations, refunds and grace periods. They append to `iap_subscription_events` and add `revenue_outbox_events` rows. Refunds get negative amounts, following the sign convention in ADR-0004.

## Revenue outbox re-dispatch

`ProjectRevenueOutbox` copies `pending` rows from `revenue_outbox_events` into `revenue_events`. It is idempotent on `(source_type, source_event_id, event_type)`. `routes/console.php` runs the sweep **every five minutes** with `withoutOverlapping`, and the job has `tries=5` and `backoff=30`.

The sweep skips rows with `attempts >= config('subscription.outbox.max_attempts')`, which defaults to 5. To recover stuck rows:

```bash
php artisan tinker
>>> # inspect
>>> \App\Domain\Subscription\Models\RevenueOutboxEvent::where('status','pending')->where('attempts','>=',5)->get(['id','source_type','event_kind','attempts']);
>>> # per-row re-dispatch BYPASSES the attempts filter (processRow runs unconditionally)
>>> \App\Domain\Subscription\Jobs\ProjectRevenueOutbox::dispatch($rowId);
>>> # or reset attempts and let the next sweep pick them up
>>> \App\Domain\Subscription\Models\RevenueOutboxEvent::whereIn('id', [...])->update(['attempts' => 0]);
```

Running a delivered row again does nothing. Event kinds without a mapping are marked delivered and logged at info level as `revenue_outbox.unmapped_event`.

## Never rotate `IAP_RECEIPT_PEPPER`

The pepper works in one direction only.

1. On a GDPR erasure, `IapReceiptPseudonymiser::pseudonymise()` **sets the raw** `original_transaction_id`, purchase token and receipt blob to null. It keeps only `HMAC-SHA256(raw, pepper)` in `original_transaction_id_hash`.
2. When a store webhook later arrives for an erased user, for example a refund or a renewal, the code computes `fingerprint(raw_from_webhook)` with the **current** pepper and compares it with the stored hashes.

If you rotate the pepper, no hash made before the rotation can ever be matched again. The raw value is gone, so the hashes cannot be recomputed, and refunds and renewals for erased users silently stop reconciling. There is no way to recover from this. If you suspect the pepper is compromised, handle it by hand. Accept the orphaned hashes and process the affected store events manually instead of rotating.

Two more rules follow from this.
- **An empty pepper in production or staging** makes `pseudonymise()` throw a `RuntimeException` before it deletes anything, because a scrub with an empty pepper would destroy the link in the same way. Set the pepper **before** the first verify request and before any erasure run. `ops:verify-env` enforces this.
- Treat the pepper like an encryption key. Keep it only in the secret manager, never log it and never commit a `.env` that contains it.

## Common support cases

- **"Paid but still Free."** Look for the user's row in `iap_subscriptions`. If there is none, the verify call failed, so search the logs for `iap.verify.receipt_rejected`. If the row exists and is active, check `SubscriptionProjection` and the mobile cache. For a renewal that lags, check `processed_webhook_events` for the store notification and look at the status of the outbox row.
- **A stale receipt loop.** The mobile app keeps sending an old receipt after a reinstall. Confirm that the row on file is in a final state and its expiry is in the past, and then tell the user to buy again.
- **Two stores active.** The user really subscribed twice, for example on the Stripe website and through Apple. The backend never cancels automatically, so the user cancels one of them. Refunds happen in the store where the user paid.
- **Family Sharing.** This rejection is expected and is not a bug.
- **Sandbox receipts in production.** Apple sandbox receipts are accepted, because app review uses them, but `iap_receipts` marks them with `environment=sandbox`. Leave `ENV_SANDBOX` out of revenue queries.

## Files referenced

- `app/Domain/Subscription/Iap/IapSubscriptionService.php` runs the verify sequence and the conflict matrix.
- `app/Domain/Subscription/Iap/AppleReceiptVerifier.php` validates the JWS chain and guards the bypass.
- `app/Domain/Subscription/Iap/GooglePlayReceiptVerifier.php` verifies Google Play purchases.
- `app/Domain/Subscription/Iap/IapReceiptPseudonymiser.php` applies the pepper rules and the GDPR scrub.
- `app/Domain/Subscription/Webhooks/AppleNotificationsWebhookController.php` receives Apple notifications.
- `app/Domain/Subscription/Webhooks/GooglePlayWebhookController.php` receives Google Play notifications.
- `app/Domain/Subscription/Jobs/ProjectRevenueOutbox.php` projects the outbox.
- `config/subscription.php` (the `iap` block), `routes/api.php` and `routes/console.php` hold the configuration, routes and schedule.
- `docs/superpowers/specs/2026-05-10-slice-2-iap-design.md` is the design source.
