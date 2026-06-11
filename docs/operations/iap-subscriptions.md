# IAP Subscriptions Operator Runbook

Operator-only runbook for the Plan B mobile-driven subscription rail (Apple App Store + Google Play, v7.13.0+). Domain: `app/Domain/Subscription/` (the `Iap/` subtree). Deliberately isolated from the Stripe-only `SubscriptionService` — neither path is a god-service.

## When to use this runbook

- A mobile user reports *"I paid in the App Store but I'm still on Free"* (verify rejected, or webhook/outbox lag).
- Mobile escalates an `ERR_SUB_002` conflict the user can't self-resolve.
- A GDPR erasure run aborts with `IAP_RECEIPT_PEPPER must be configured`.
- Revenue dashboards lag behind store activity (outbox rows stuck `pending`).
- Pre-prod checklist before an App Store / Play Store release that touches subscriptions.

## Environment & credentials

```ini
IAP_RECEIPT_PEPPER=<openssl rand -hex 32>     # one-way HMAC pepper — see "Never rotate" below
APPLE_JWS_VERIFICATION_BYPASS=false           # staging-only; ignored (and logged) in production
# Product-id maps (config/subscription.php → subscription.iap.{apple,google}.product_ids)
# Apple root CA: storage/app/apple/AppleRootCA-G3.cer (subscription.iap.apple.root_ca_path)
```

`php artisan ops:verify-env` gates the pepper (FAIL when empty), the Apple bypass flag (FAIL when true), and the root-CA file at deploy time.

## The verify endpoint

`POST /api/v1/subscription/iap/verify` (Sanctum auth, `throttle:10,1` per user) → `IapVerifyController` → `IapSubscriptionService::verify`. Processing sequence:

1. **Currency gate** — EUR only in v1.3.x → `ERR_CUR_001`.
2. **Plan resolution** — store `productId` mapped via `config('subscription.iap.{store}.product_ids')` → unknown id = `ERR_SUB_001`.
3. **Receipt verification** — Apple: JWS chain validation (below); Google: Play Developer API lookup of the purchase token. Rejection = `ERR_SUB_001`.
4. **Family Sharing** (Apple only) → `ERR_SUB_002` `kind=family_sharing_unsupported`.
5. **Account binding** — Apple `appAccountToken` must equal `users.uuid`; Google `obfuscatedExternalAccountId` must equal `sha256(users.uuid)`. Mismatch = `ERR_SUB_002` `kind=different_zelta_user`. (Absent token = accepted; the Sanctum bearer still scopes the request.)
6. **Multi-store conflict gate** — active Stripe sub, or active sub in the opposing store → `ERR_SUB_002` `kind=two_stores_active`.
7. **Persist** (one transaction, `lockForUpdate` on the existing row): create/reactivate `iap_subscriptions`, append `iap_subscription_events`, write `iap_receipts`, log withdrawal consent, enqueue the revenue outbox row. Same-store duplicates return an idempotent 200; a cross-store unique-index race (`uniq_iap_active_per_user`) is also treated as the idempotent path.

## ERR_SUB_002 conflict matrix

Every IAP conflict is **HTTP 409 `ERR_SUB_002`** with `conflict.kind` + `attemptedSource` + a **non-null** `existingSubscription{ source, currentPeriodEndsAt }`. Mobile (`subscriptionConflict.ts`) branches on `kind` and returns null for any other code — never introduce a new `ERR_SUB_*` code for an IAP conflict, and never drop `existingSubscription` (when there is no row on file, `existingSubscriptionFromVerified()` derives it from the receipt's reported source + expiry).

| `kind` | Trigger | Typical support resolution |
|---|---|---|
| `two_stores_active` | Active Stripe sub, or active sub in the other store | User cancels one side in that store/Stripe portal; backend never merges. Verify state via `subscriptions` / `iap_subscriptions` |
| `different_zelta_user` | Receipt's store subscription (or its account token) belongs to another Zelta account | User is signed into the wrong Zelta account, or shares a store account. They must use the original Zelta account, or cancel + repurchase from the new one after expiry |
| `family_sharing_unsupported` | Apple receipt is family-shared (`isFamilyShared`) | Unsupported by policy. The purchaser's own device/account must subscribe directly |
| `stale_receipt` | Known subscription in terminal state (`expired`/`refunded`/`cancelled`) AND receipt expiry in the past | User reinstalled with an old receipt. They should repurchase; mobile shows the resubscribe path |

## Apple JWS chain validation

`AppleReceiptVerifier` validates the x5c certificate chain of every signed transaction and pins the root to **Apple Root CA G3** at `storage/app/apple/AppleRootCA-G3.cer` (fingerprint `63343ABF…E9179`; verify with `openssl x509 -fingerprint -sha256 -inform der -in storage/app/apple/AppleRootCA-G3.cer`). Any chain not rooted there is rejected.

### The staging-only bypass — and why it must never reach production

`APPLE_JWS_VERIFICATION_BYPASS=true` skips signature/chain verification so staging can run without provisioned certs. **It is a full auth-bypass for the entire Apple IAP surface**: with it on, any authenticated user can POST a self-crafted "receipt" and forge a Pro subscription. Defense in depth:

- In production the flag is **ignored** — chain validation runs anyway and `iap.apple.jws.bypass_rejected_in_production` is logged at ERROR (alert on this; it means someone tried).
- Every honoured bypass (non-prod) logs `iap.apple.jws.signature_verify_bypassed` at WARNING.
- `ops:verify-env` FAILs the deploy when the flag is true.
- Post-deploy negative smoke test: POST a known-bad JWS to `/api/v1/subscription/iap/verify` in production and expect `ERR_SUB_001`, not a created subscription.

## Webhook ingestion (Apple V2 + Google RTDN)

Both store webhooks flow through the same dedupe → pseudonymised-lookup → outbox path. Both **return 200 even on processing errors** (stores retry non-2xx indefinitely; controllers log and acknowledge).

| | Apple | Google |
|---|---|---|
| Endpoint | `POST /api/webhooks/apple/notifications` | `POST /api/webhooks/google/play` |
| Payload | `{"signedPayload": "<JWS>"}` (App Store Server Notifications V2) | Pub/Sub push: `{"message":{"data":"<base64 RTDN JSON>"}}` |
| Auth | JWS chain validation (same pinned root as verify) | OIDC `Authorization: Bearer` JWT from `accounts.google.com`, `aud` claim checked |
| Dedupe | `processed_webhook_events (provider, event_id)` unique index | same |
| Post-erasure match | `IapReceiptPseudonymiser::fingerprint(originalTransactionId)` against `original_transaction_id_hash` | `fingerprint(purchaseToken)` |

Events update `iap_subscriptions` (renewals, cancellations, refunds, grace), append `iap_subscription_events`, and enqueue `revenue_outbox_events` rows (negative amounts for refunds per ADR-0004 sign-prefix).

## Revenue outbox re-dispatch

`ProjectRevenueOutbox` projects `pending` rows from `revenue_outbox_events` into `revenue_events`, idempotent on `(source_type, source_event_id, event_type)`. The sweep is scheduled **every five minutes** (`routes/console.php`, `withoutOverlapping`); job has `tries=5`, `backoff=30`.

The sweep skips rows with `attempts >= config('subscription.outbox.max_attempts')` (default 5). To recover stuck rows:

```bash
php artisan tinker
>>> # inspect
>>> \App\Domain\Subscription\Models\RevenueOutboxEvent::where('status','pending')->where('attempts','>=',5)->get(['id','source_type','event_kind','attempts']);
>>> # per-row re-dispatch BYPASSES the attempts filter (processRow runs unconditionally)
>>> \App\Domain\Subscription\Jobs\ProjectRevenueOutbox::dispatch($rowId);
>>> # or reset attempts and let the next sweep pick them up
>>> \App\Domain\Subscription\Models\RevenueOutboxEvent::whereIn('id', [...])->update(['attempts' => 0]);
```

A delivered row re-run is a no-op; unmapped event kinds are marked delivered with an `revenue_outbox.unmapped_event` info log.

## NEVER rotate `IAP_RECEIPT_PEPPER`

The pepper is one-way by construction:

1. On GDPR erasure, `IapReceiptPseudonymiser::pseudonymise()` **nulls the raw** `original_transaction_id` / purchase token / receipt blob and stores only `HMAC-SHA256(raw, pepper)` in `original_transaction_id_hash`.
2. Post-erasure store webhooks (REFUND, RENEWAL for a scrubbed user) are matched by computing `fingerprint(raw_from_webhook)` with the **current** pepper and comparing against stored hashes.

Rotate the pepper and every pre-rotation hash becomes permanently unmatchable — the raw value is gone, re-hashing is impossible, and refunds/renewals for erased users silently stop reconciling. There is no recovery path. On suspected pepper compromise the resolution is manual ops (accept the orphaned hashes, handle affected store events by hand), not rotation.

Corollaries:
- **Empty pepper in production/staging**: `pseudonymise()` hard-throws `RuntimeException` before wiping anything (an empty-pepper scrub would destroy the linkage the same way). Set the pepper **before** the first verify request and before any erasure run; `ops:verify-env` enforces this.
- Treat the pepper like an encryption key: secret manager only, never logs, never `.env` committed anywhere.

## Common support cases

- **"Paid but still Free"** — check `iap_subscriptions` for the user; if absent, the verify call failed (grep `iap.verify.receipt_rejected`); if present and active, check `SubscriptionProjection` / mobile cache. Renewal lag: check `processed_webhook_events` for the store's notification and the outbox row status.
- **Stale receipt loop** — mobile keeps re-sending an old receipt after reinstall; confirm the on-file row is terminal and the expiry is past, then direct the user to repurchase.
- **Two stores active** — user genuinely double-subscribed (e.g. Stripe web + Apple). We never auto-cancel; the user cancels one side. Refunds happen in the respective store.
- **Family sharing** — expected rejection; not a bug.
- **Sandbox receipts in prod** — Apple sandbox receipts are accepted but flagged `environment=sandbox` on `iap_receipts` (app-review traffic). Exclude `ENV_SANDBOX` from revenue queries.

## Files referenced

- `app/Domain/Subscription/Iap/IapSubscriptionService.php` — verify sequence + conflict matrix
- `app/Domain/Subscription/Iap/AppleReceiptVerifier.php` — JWS chain validation + bypass gate
- `app/Domain/Subscription/Iap/GooglePlayReceiptVerifier.php`
- `app/Domain/Subscription/Iap/IapReceiptPseudonymiser.php` — pepper rules, GDPR scrub
- `app/Domain/Subscription/Webhooks/AppleNotificationsWebhookController.php`
- `app/Domain/Subscription/Webhooks/GooglePlayWebhookController.php`
- `app/Domain/Subscription/Jobs/ProjectRevenueOutbox.php`
- `config/subscription.php` (`iap` block), `routes/api.php`, `routes/console.php`
- `docs/superpowers/specs/2026-05-10-slice-2-iap-design.md` — design source
