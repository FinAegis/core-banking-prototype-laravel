# Bridge.xyz Ramp Operator Runbook

This runbook is for operators. It explains how to diagnose and reconcile the Bridge.xyz ramp integration, which lets users move money in by bank transfer and receive USDC on Polygon. Only the on-ramp is available. The design decisions behind it are in `docs/adr/0005-bridge-xyz-over-stripe-crypto-onramp.md` and `docs/adr/0006-bridge-developer-fees-as-markup-mechanism.md`.

## When to use this runbook

- A mobile user says "I completed KYC but can't see the bank details". Virtual account provisioning failed or was deferred.
- A mobile user says "my deposit went missing". The webhook did not arrive or the session lookup failed.
- A mobile user says "I upgraded to Pro but I'm still being charged the markup". The developer fee update did not run.
- You are checking a new environment before you set `RAMP_PROVIDER=bridge`.
- You have just rotated the Bridge credentials.

## Environment and credentials

These `.env` keys are required. `.env.production.example` lists them too.

```ini
RAMP_PROVIDER=bridge                       # canonical; deprecated alias `stripe_bridge` still resolved
BRIDGE_API_KEY=<scoped production key from Bridge dashboard>
BRIDGE_WEBHOOK_PUBLIC_KEY=<per-endpoint RSA public key from the dashboard webhook; PEM or base64-of-PEM — NOT a secret>
BRIDGE_WEBHOOK_SECRET=                      # legacy HMAC only; leave blank on the current "Bridge by Stripe" platform
BRIDGE_API_BASE_URL=https://api.bridge.xyz # or https://api.sandbox.bridge.xyz when testing
BRIDGE_DEFAULT_DEV_FEE_BPS=75              # Free tier; Pro is 0 (per ADR-0006)
```

In `config/kyc.php`, `KYC_RAMP_PROVIDER` and `KYC_CARDS_PROVIDER` both default to `bridge`. `KYC_TRUSTCERT_PROVIDER` stays on `ondato`.

## Bridge dashboard configuration

Create one webhook endpoint with this URL. On the `api.` subdomain the `/api` prefix is dropped.

```
https://api.zelta.app/v1/webhooks/bridge      # or https://<your-domain>/api/v1/webhooks/bridge on the bare domain
```

Subscribe it to these `event_categories`: `customer`, `kyc_link`, `virtual_account`, `virtual_account.activity` and `transfer`. The one endpoint handles both the identity check events (`customer.kyc_link_*`) and the ramp events (`virtual_account.activity` and `transfer.*`). Each event is processed once, because `processed_webhook_events (provider='bridge', event_id)` records the events already seen.

**A new webhook starts as `disabled`.** Change it to `active` with a PUT request, or it delivers nothing. Then copy the endpoint's public key from the dashboard into `BRIDGE_WEBHOOK_PUBLIC_KEY`.

**Signature verification.** `BridgeWebhookVerifier` checks Bridge's current asymmetric scheme. The header is `X-Webhook-Signature: t=<unix_ms>,v0=<base64>`, and the signature is RSA-SHA256 over `<t>.<body>`, checked against `BRIDGE_WEBHOOK_PUBLIC_KEY`. The dashboard shows only this public key and no shared secret. The verifier also detects the legacy HMAC scheme (`v1` with `BRIDGE_WEBHOOK_SECRET`) and uses it as a fallback.

**Check the verifier against one real sandbox event before the first production deploy.** Fire a test event from the dashboard and expect 200, not 401. If you get 401, the likely cause is a difference in timestamp units or RSA padding. Adjust `BridgeWebhookVerifier`, whose tests are in `tests/Feature/Api/Bridge/BridgeWebhookVerifierTest.php`.

## Inspecting a single user

Run this first when you look into any Bridge-related report.

```bash
php artisan bridge:inspect-user user@example.com
php artisan bridge:inspect-user user@example.com --sessions=20  # last 20 ramp sessions
```

The command only reads data and never writes. It prints these sections:

| Section | What to look for |
|---|---|
| **User** | `id`, `uuid`, `email` and the Ondato `kyc_status`, which is separate from the Bridge identity check (§7.5) |
| **Bridge customer** | `bridge_customer_id`, `kyc_status`, `virtual_account_id`, a summary of the deposit details, `supported_rails`, `developer_fee_bps` and timestamps |
| **Polygon address** | Whether the user has a Polygon address. **Virtual account provisioning needs it.** If it is missing, provisioning was deferred, as described in the next section |
| **Recent ramp_sessions** | For each session, its id, type (`on` or `off`), source (`user_initiated` or `bridge_initiated`), status, fiat and crypto amounts, `provider_session_id` and `created_at` |

## Common scenarios

### Identity check approved but no virtual account (`virtualAccountReady=false`)

The usual cause is timing. The user finished the identity check before the mobile app finished wallet setup. When `customer.kyc_link_completed` arrived, there was no Polygon row in `blockchain_addresses`, so `BridgePostKycHandler` logged `deferring VA provisioning — no Polygon address yet`.

Try these fixes in this order.

1. **Wait for the automatic retry.** `BlockchainAddressBridgeObserver` starts provisioning as soon as a Polygon address is added. If the user reopens the app and wallet setup completes, the virtual account appears on its own. **Run `bridge:inspect-user` about 60 seconds after the user retries to confirm.**
2. **Trigger it by hand from tinker** if the user has the address but the observer did not fire. This can happen when the address existed earlier and the identity check finished through a different flow.
   ```php
   php artisan tinker
   >>> $customer = \App\Domain\Compliance\Kyc\Models\BridgeCustomer::where('user_id', $userId)->first();
   >>> app(\App\Domain\Compliance\Kyc\Services\BridgePostKycHandler::class)->tryProvisionVirtualAccount($customer);
   >>> // re-inspect with `bridge:inspect-user` to confirm
   ```
3. **As a last resort**, open a ticket with Bridge support if Bridge keeps rejecting the call. The log line is `BridgePostKycHandler: VA provisioning failed`. Never insert a made-up `virtual_account_id` by hand, because the webhook flows depend on the real Bridge id.

### A deposit did not show up in the app

Check these points in order.

1. **Did Bridge send the webhook?** Search the logs for the user's `bridge_customer_id` or `virtual_account_id`. If there is no webhook, check the delivery logs in the Bridge dashboard.
2. **Did our endpoint reject it?** Look for `Bridge webhook: invalid signature` (401) or `Bridge webhook: invalid JSON body` (400). An invalid signature means a wrong or stale `BRIDGE_WEBHOOK_PUBLIC_KEY`, or a change in the signature scheme as described above.
3. **Was the event skipped as a duplicate?** The log line `Bridge webhook: duplicate event ignored` means an event with that `id` was already processed. If the deposit really did not arrive, Bridge may have reused an `id`, so open a ticket.
4. **Was the session not found?** `Bridge ramp webhook: session not found` means the `virtual_account_id` in the payload matches none of our `bridge_customers` rows. Use `bridge:inspect-user` to compare the user's `virtual_account_id` with the one in the payload.
5. **Was it an unsolicited deposit?** A user can wire money without starting a session through `POST /api/v1/ramp/session`. The handler then creates a `ramp_sessions` row after the fact with `source: 'bridge_initiated'`. If that row is missing, the `bridge_customers` lookup by `virtual_account_id` failed, so check the inspect output for a mismatch.

### A Pro upgrade did not remove the markup

The `SubscriptionTierChanged` event triggers the `SyncBridgeDevFeeOnTierChange` listener, which runs on the `events` queue. Check these points in order.

1. **Is the queue running?** The listener implements `ShouldQueue`. If no worker processes the `events` queue, the update never happens.
2. **What did the listener log?** Look for `BridgeDeveloperFeeSync: dev fee updated` on success or `BridgeDeveloperFeeSync: PATCH failed` when Bridge rejected the update.
3. **Reconcile by hand** while the user is waiting:
   ```bash
   php artisan bridge:sync-dev-fee --email=user@example.com
   # Output: "user@example.com: developer_fee_bps now 0." (success) or
   #         "No bridge_customers row for ... (KYC not started)." (no-op)
   ```
4. **Run a batch backfill** before you change a feature flag or after a long outage:
   ```bash
   php artisan bridge:sync-dev-fee --all --dry-run   # preview
   php artisan bridge:sync-dev-fee --all              # apply
   ```

### Rotating the webhook public key

On the current platform the verification credential is a public RSA key for each endpoint, not a shared secret. Bridge has no self-service key rotation, so you rotate the key by creating a new webhook.

1. Create a new webhook endpoint in the Bridge dashboard with the same URL and categories. **Leave the old one active.** The new endpoint gets a fresh public key.
2. Set `BRIDGE_WEBHOOK_PUBLIC_KEY` in the deployed `.env` to the new key, as PEM or base64 of the PEM. Restart the workers.
3. Fire a test event from the new endpoint and expect 200. If you get `Bridge webhook: invalid signature` (401), the key is wrong, so go back to the old key.
4. When the new endpoint verifies cleanly, delete the old endpoint in the dashboard.

`BridgeWebhookVerifier::verify` lets requests through without a check only when both `BRIDGE_WEBHOOK_PUBLIC_KEY` and `BRIDGE_WEBHOOK_SECRET` are empty, and only outside production. In production with no credentials it returns `false`, and every webhook gets 401. **Make sure `BRIDGE_WEBHOOK_PUBLIC_KEY` is set before a new deployment goes live.**

## Note on the provider rename

The provider that used to be called `stripe_bridge` is now called `stripe_crypto_onramp`, as ADR-0005 explains. The old name mixed up Stripe Crypto Onramp and Bridge.xyz. The old string still works as a deprecated alias.

- `RAMP_PROVIDER=stripe_bridge` resolves to `StripeCryptoOnrampProvider` and logs a warning.
- `STRIPE_BRIDGE_WEBHOOK_SECRET` is still read when `STRIPE_CRYPTO_ONRAMP_WEBHOOK_SECRET` is unset.
- Old `ramp_sessions.provider='stripe_bridge'` rows still resolve through the registry alias.

Update deployed `.env` files to the new names when convenient. The alias is planned for removal in v1.1.

## Checks after rotating Bridge credentials

```bash
# 1. Update .env
BRIDGE_API_KEY=<new scoped key>
BRIDGE_WEBHOOK_PUBLIC_KEY=<new per-endpoint public key>

# 2. Restart workers (so the queued SyncBridgeDevFeeOnTierChange listener picks up the new BridgeClient)
php artisan queue:restart

# 3. Smoke-test API key with a no-op inspection
php artisan bridge:inspect-user <some staging user>
# Confirm the bridge_customer section renders without errors.

# 4. Smoke-test webhook signature with a sandbox event
# (or wait for the next live webhook and grep logs for invalid_signature)
```

## Escalation

- **The Bridge integration has been silently broken in production for more than five minutes.** Check for the log line `BridgePostKycHandler: VA provisioning failed`. If that is the cause, contact Bridge support with the list of affected `bridge_customer_id` values. You can get the list with `bridge_customers WHERE kyc_status='approved' AND virtual_account_id IS NULL ORDER BY updated_at DESC`.
- **You suspect the signature scheme changed.** Do not clear `BRIDGE_WEBHOOK_PUBLIC_KEY` in production to get around the check. Capture a real failing payload with its raw body and `X-Webhook-Signature` header, and check it against Bridge's documented signature format. Then fix `BridgeWebhookVerifier::verify` in code. Timestamp units and RSA padding are the usual causes.
- **A Pro user still sees the markup.** Run `bridge:sync-dev-fee --email=<addr>` first. If the developer fee was already 0, the problem is in how quotes are shown (`RampService::getQuotes`), not in Bridge.

## Files referenced

- `app/Domain/Compliance/Kyc/Models/BridgeCustomer.php` holds the state for each user.
- `app/Domain/Compliance/Kyc/Services/BridgePostKycHandler.php` provisions the virtual account and sends the WebSocket event and the push notification.
- `app/Domain/Compliance/Kyc/Services/BridgeDeveloperFeeSync.php` updates the developer fee.
- `app/Domain/Compliance/Kyc/Listeners/SyncBridgeDevFeeOnTierChange.php` triggers that update when the subscription tier changes.
- `app/Domain/Compliance/Kyc/Observers/BlockchainAddressBridgeObserver.php` retries provisioning when an address is added.
- `app/Http/Controllers/Api/V1/BridgeWebhookController.php` receives and dispatches the webhooks.
- `app/Http/Controllers/Api/V1/BridgeSetupController.php` serves the setup endpoints for the mobile app.
- `app/Infrastructure/Bridge/BridgeClient.php` wraps the HTTP API.
- `app/Infrastructure/Bridge/BridgeWebhookVerifier.php` verifies signatures with the asymmetric `X-Webhook-Signature` v0 scheme and the legacy HMAC fallback.
- `app/Console/Commands/BridgeInspectUserCommand.php` implements `bridge:inspect-user`.
- `app/Console/Commands/BridgeSyncDevFeeCommand.php` implements `bridge:sync-dev-fee`.
- `docs/adr/0005-bridge-xyz-over-stripe-crypto-onramp.md` and `docs/adr/0006-bridge-developer-fees-as-markup-mechanism.md` record the design decisions.
- `docs/BACKEND_HANDOVER_CARDS_KYC_RAMP.md` is the handover note for the mobile team.
