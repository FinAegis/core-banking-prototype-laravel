# Production Readiness Checklist — Zelta v7.15

> **Supersedes the September 2024 version** of this file (the Jumio/Santander-era,
> CTO-sign-off checklist). That document predated the non-custodial mobile-wallet
> pivot, Plan B subscriptions, Bridge.xyz ramp, the public MCP server, and the
> `ops:verify-env` deploy gate. This is the current go-live checklist for a Zelta
> v7.15 mobile-wallet production deployment.
>
> **Status legend**: `[x] done` · `[ ] open` (action required before go-live) ·
> `[blocked]` (depends on external work, tracked). Mark each item honestly — a
> checked box here is a claim someone can be held to.

## 0. Deploy gate — run this first

- [x] `php artisan ops:verify-env` exists on `main` and is the canonical preflight (PR #1131). Run it in CI/CD **before** traffic. Exit 1 = blocked; FAIL results block in production (or with `--strict`). It front-loads every lazy production guard (IAP pepper, pricing quote signer, Bridge webhook verifier, demo-mode toggles, conditional secrets) into one gate.
  ```bash
  php artisan ops:verify-env --json    # wire into the deploy pipeline as a hard gate
  ```
- [ ] CI/CD actually invokes `ops:verify-env` as a blocking step (verify the pipeline, not just the command's existence).

## 1. Core app config

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` set (all enforced by `ops:verify-env` core checks).
- [ ] `config:cache` / `route:cache` run on deploy; workers restarted after env changes.
- [ ] `REGISTRATION_ENABLED=false` (public registration off in production; users created via CLI).
- [ ] `LIGHTHOUSE_SECURITY_DISABLE_INTROSPECTION=true` (GraphQL introspection off — set in both prod templates, PR #1135).

## 2. Secrets & peppers (one-way — set once, never rotate where noted)

- [ ] `IAP_RECEIPT_PEPPER` set (`openssl rand -hex 32`). **One-way — NEVER rotate**: rotation permanently breaks post-erasure IAP webhook matching. `IapReceiptPseudonymiser` hard-throws on first use if empty. (`ops:verify-env` FAILs when empty.)
- [ ] `TRIAL_FINGERPRINT_PEPPER` set (trial-card fingerprint hashing).
- [ ] `PRICING_QUOTE_PEPPER` set (`QuoteSigner` refuses to sign without it).
- [ ] `BIOMETRIC_JWT_SECRET` set (≥32 bytes; mobile biometric login signing).
- [ ] Privy: `PRIVY_APP_ID`, `PRIVY_APP_SECRET`, `PRIVY_JWKS_URL`, `PRIVY_ISSUER` set when `MCP_WEB_PRIVY_LOGIN=true` (web `/login`); the mobile JWT path needs the issuer/app-id for `PrivyJwtVerifier`.

## 3. Demo/bypass toggles must be OFF in production

All FAILed by `ops:verify-env` when wrong:

- [ ] `APPLE_JWS_VERIFICATION_BYPASS=false` — staging-only; it is a full auth-bypass for the entire Apple IAP surface (ignored + logged ERROR in prod, but keep it false). Negative smoke test: POST a known-bad JWS to `/api/v1/subscription/iap/verify` in prod and confirm `ERR_SUB_001`, not a created subscription.
- [ ] `demo.mode=false`, `SANDBOX_MODE=false`, all `DEMO_*` feature toggles false.
- [ ] `KEY_MANAGEMENT_DEMO_MODE=false`, `REGTECH_DEMO_MODE=false`, `AI_DEMO_MODE=false` (these default to true — must be set false explicitly).
- [ ] `SHOW_PROMO_PAGES=false` (production shows the app landing page only).
- [ ] `MODULES_DISABLED` scoped for Zelta production (PR #1135) — `.env.zelta.example` curates 17 demo/showcase domains (Banking, Basket, Cgo, CrossChain, DeFi, FinancialInstitution, Governance, ISO20022, ISO8583, Interledger, Ledger, Lending, Microfinance, OpenBanking, PaymentRails, Stablecoin, Treasury). Exchange stays enabled (serves mobile `/exchange-rates`, `/convert`). Verify ~218 demo routes are removed and zero mobile/MCP/webhook routes are affected.
- [ ] `ADMIN_MODULES` pinned to the mobile-wallet scope on Zelta deployments.

## 4. Payment rails

- [ ] **Stripe** is the default deposit rail; keys + webhook configured and verified.
- [ ] **HyperSwitch** (`HYPERSWITCH_ENABLED`): OFF by default. If enabling, set `HYPERSWITCH_API_KEY` + `HYPERSWITCH_WEBHOOK_SECRET` (both FAILed by `ops:verify-env` when the flag is on and they're empty); configure the dashboard webhook → `/api/webhooks/hyperswitch`; know the `completion_failed` reconciliation path. Runbook: `docs/operations/hyperswitch-deposits.md`.
- [ ] **IAP subscriptions**: Apple/Google product-id maps configured; Apple Root CA G3 present at `storage/app/apple/AppleRootCA-G3.cer`; webhook endpoints registered (`/api/webhooks/apple/notifications`, `/api/webhooks/google/play`); revenue outbox sweep (`ProjectRevenueOutbox`, every 5 min) running. Runbook: `docs/operations/iap-subscriptions.md`.

## 5. Bridge.xyz ramp (fiat ↔ stablecoin)

Runbook: `docs/operations/bridge-ramp.md`. ADRs: `docs/adr/0005-*`, `docs/adr/0006-*`.

- [ ] `BRIDGE_API_KEY` set. Bridge authenticates with the **`Api-Key` header** (not `Authorization: Bearer`) — confirm this matches Bridge's current platform before go-live (`BridgeClient` notes the v0 pattern as of 2026-05).
- [ ] `BRIDGE_WEBHOOK_PUBLIC_KEY` set (per-endpoint RSA public key). With Bridge as the active provider, `ops:verify-env` FAILs when no webhook credential is present (verifier 401s every webhook → KYC/ramp never land).
- [ ] **Sandbox confirmation**: fire a real Bridge sandbox webhook and confirm `BridgeWebhookVerifier` returns 200, not 401. The asymmetric `v0` scheme (timestamp **units** and RSA padding) is **unverified against live bytes** — do not go live on assumption.
- [ ] Webhook endpoint is `active` in the Bridge dashboard (new webhooks start `disabled`).
- [ ] Dev-fee markup wired: `SubscriptionTierChanged` listener PATCHes `developer_fee_bps` (Free=75/Pro=0); queue worker for the `events` queue is running (the listener is `ShouldQueue`).

## 6. Non-custodial wallet send & sponsorship

Runbook: `docs/operations/wallet-sponsorship.md`.

- [ ] EVM sponsorship: `PIMLICO_API_KEY` + `PIMLICO_BUNDLER_URL` set. `WALLET_SEND_EVM_NETWORKS=polygon,base,arbitrum` (Ethereum L1 stays disabled — uncapped gas).
- [ ] Solana sponsorship: `WALLET_SOLANA_SPONSOR_SECRET_KEY` set (base58 64-byte ed25519) if 0-SOL wallets must send. `ops:verify-env` FAILs when the key is set but undecodable; SKIPs when unset (legacy sender-pays, which fails for 0-SOL wallets).
- [ ] **Sponsor balance monitoring**: `solana:check-sponsor-balance` scheduled hourly (it is, in `routes/console.php`); CRITICAL alert routes to Slack; sponsor account funded above `WALLET_SOLANA_SPONSOR_LOW_BALANCE_LAMPORTS` (default 0.1 SOL).
- [ ] Sponsorship caps reviewed: per-user 30/day, global 5000/day (`WALLET_SEND_*_DAILY_LIMIT`). Note these are **cache counters** — a Redis flush resets the day's counts.
- [ ] `HELIUS_API_KEY` set (Solana webhook sync, balances, confirmations) — `ops:verify-env` WARNs when empty.

## 7. Public MCP server

- [ ] Cloudflare CNAME for `mcp.*` resolves; `bootstrap/app.php` subdomain ordering correct (mcp before protocol subdomains).
- [ ] Per-tool kill-switches (`MCP_TOOL_*`) reviewed; payment-class tools (`payments:write`, `ramp:write`, `sms:send`) enforce the daily spending limit (`MCP_DEFAULT_DAILY_LIMIT_MINOR`, default $500). Spending limits on `ramp.start` + `sms.send` enforced as of PR #1134.
- [ ] Dev-portal tool count matches `config('mcp.tools')` (`developers/mcp-tools.blade.php`).

## 8. Observability & alerting

- [ ] `LOG_SLACK_WEBHOOK_URL` set — the default log stack auto-includes Slack when present, routing every `Log::critical`/`emergency` to Slack (PR #1133). `LOG_SLACK_LEVEL` defaults to `critical` (does not inherit `LOG_LEVEL`).
- [ ] `METRICS_TOKEN` (or `METRICS_ALLOWED_IPS`) set — `MetricsAccessMiddleware` gates `/api/monitoring/{metrics,prometheus}` + `/api/metrics/prometheus`; with neither configured it **fails closed (403) in production** (PR #1133). `/health`, `/ready`, `/alive` stay public for k8s probes.
- [ ] Error tracking (Sentry/Rollbar) + APM wired; alert thresholds set.
- [ ] Horizon running for queues (`events`, default, notifications); failed-job handling verified.

## 9. Data & backups

- [ ] **Scheduled DB backups are wired** (spatie/laravel-backup): `backup:run --only-db` runs daily at 01:30 UTC and `backup:clean` at 02:30 UTC, both production-only with `Log::critical` on failure (`routes/console.php`). DB-only by design — the event store is the ledger. Remaining ops steps before checking this off: set `BACKUP_DISK=s3` + the S3 bucket credentials in the prod `.env` (default is `local`, which keeps dumps on the same box — `ops:verify-env` WARNs about it), then **run one restore drill** against a real dump (see the Backup Verification section of `OPERATIONAL_RUNBOOK.md`; `backup:list` / `backup:monitor` cover ongoing verification). Managed-DB snapshots remain a sensible second layer, but are no longer the only layer.
- [ ] Database encryption at rest + TLS in transit (infra layer).
- [ ] GDPR erasure path verified, including `IapReceiptPseudonymiser` (requires the pepper from §2 — it hard-throws otherwise).

## 10. Mobile-specific

- [blocked] **Device attestation is OFF and intentionally so.** `MOBILE_ATTESTATION_ENABLED=false` in both prod templates. `config/mobile.php` documents: "DO NOT FLIP TO TRUE WITHOUT FIRST WIRING THE MOBILE-SUPPLIED CHALLENGE" — `BiometricJWTService::verifyDeviceAttestation()` passes an empty challenge to `AppleAttestationVerifier`, so iOS always fails until controllers plumb `device_challenge` through. **Blocked on mobile challenge plumbing** — coordinate with the mobile team; do not enable in isolation. (`ops:verify-env` FAILs if enabled without the Apple/Google attestation keys.)
- [ ] Reviewer/demo accounts provisioned for app-store review per `docs/operations/reviewer-accounts.md` (operator-only; expiry sweep at 00:10 UTC).
- [ ] Push notifications (FCM) configured for Helius/Alchemy webhook delivery.

## 11. Pre-launch verification

- [ ] `./vendor/bin/pest --parallel` green (note: CI skips a portion of the suite; run the MultiConnection job against real MySQL).
- [ ] `XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G` clean (Level 8).
- [ ] `php-cs-fixer` + PHPCS (v4.0.1) clean.
- [ ] Smoke tests against the deployed environment (deposit, IAP verify, wallet send, Bridge webhook) pass.

---

**Last Updated**: June 11, 2026 (v7.15 rewrite — supersedes the Sept 2024 version)
**Owner**: Platform / Ops
**Companion docs**: `docs/operations/*` runbooks, `CLAUDE.md`, `docs/10-OPERATIONS/OPERATIONAL_RUNBOOK.md`
