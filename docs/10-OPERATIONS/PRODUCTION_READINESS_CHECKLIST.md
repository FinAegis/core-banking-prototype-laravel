# Production Readiness Checklist — Zelta v7.18.1

> **Supersedes the September 2024 version** of this file (the Jumio/Santander-era,
> CTO-sign-off checklist). That document predated the non-custodial mobile-wallet
> pivot, Plan B subscriptions, Bridge.xyz ramp, the public MCP server, and the
> `ops:verify-env` deploy gate. This is the current go-live checklist for a Zelta
> mobile-wallet production deployment. It was rewritten for v7.15 and updated for
> v7.18.0 (virtual card pilot, card top-ups, registration settings) and v7.18.1
> (saved MCP spending limit).
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
- [ ] v7.18.0 upgrade steps done in this order (v7.18.1 adds no migrations or settings): `composer install --no-dev`, `npm ci && npm run build`, `php artisan migrate --force` (eight migrations), `php artisan tenants:migrate` on a multi-database tenancy setup (two tenant migrations), then restart the workers (`php artisan horizon:terminate`, or `supervisorctl restart all` under Supervisor) and run `php artisan ops:verify-env`.

## 1. Core app config

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` set (all enforced by `ops:verify-env` core checks).
- [ ] `config:cache` / `route:cache` run on deploy; workers restarted after env changes.
- [ ] `REGISTRATION_ENABLED=false` (the Fortify web registration form stays off in production).
- [ ] `REGISTRATION_API_ENABLED` set explicitly (`ops:verify-env` FAILs a production deploy that leaves it unset). It governs password sign-up, the first Privy login and the first social login; `.env.zelta.example` sets `true`. Set `REGISTRATION_API_INVITE_ONLY=true` for invitation-only sign-ups.
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
- [ ] `TRADING_ENABLED=false`. The exchange trading engine and the liquidity endpoints (`feature:trading.enabled`) stay off in production. The liquidity add, remove and rebalance flow was removed in v7.18.0.
- [ ] `ZK_PROVIDER=demo` and `MERKLE_PROVIDER=demo`. `ops:verify-env` FAILs `ZK_PROVIDER=railgun` in production, because it turns on the custodial RAILGUN path. It also FAILs when the two values differ. Both env templates still set `MERKLE_PROVIDER=railgun`, so change it before deploying. See `docs/operations/railgun-infra.md`.

## 4. Payment rails

- [ ] **Stripe** card deposits on the web wallet follow `WEB_CARD_DEPOSITS_ENABLED`, which `.env.zelta.example` sets to `false` (the mobile app uses Bridge and IAP). If you turn them on, configure and verify the Stripe keys and webhook.
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

- [ ] EVM sponsorship: `PIMLICO_API_KEY` set, `PIMLICO_BUNDLER_URL` left empty (derived per chain). `WALLET_SEND_EVM_NETWORKS=polygon,base,arbitrum` (Ethereum L1 stays disabled — uncapped gas).
- [ ] Solana sponsorship: `WALLET_SOLANA_SPONSOR_SECRET_KEY` set (base58 64-byte ed25519) if 0-SOL wallets must send. `ops:verify-env` FAILs when the key is set but undecodable; SKIPs when unset (legacy sender-pays, which fails for 0-SOL wallets).
- [ ] **Sponsor balance monitoring**: `solana:check-sponsor-balance` scheduled hourly (it is, in `routes/console.php`); CRITICAL alert routes to Slack; sponsor account funded above `WALLET_SOLANA_SPONSOR_LOW_BALANCE_LAMPORTS` (default 0.1 SOL).
- [ ] Sponsorship caps reviewed: per-user 30/day, global 5000/day (`WALLET_SEND_*_DAILY_LIMIT`). Note these are **cache counters** — a Redis flush resets the day's counts.
- [ ] `HELIUS_API_KEY` set (Solana webhook sync, balances, confirmations) — `ops:verify-env` WARNs when empty.

## 7. Public MCP server

- [ ] Cloudflare CNAME for `mcp.*` resolves; `bootstrap/app.php` subdomain ordering correct (mcp before protocol subdomains).
- [ ] Per-tool kill-switches (`MCP_TOOL_*`) reviewed. The catalog has 14 tools; production exposes 10, because `account.create`, `payment.transfer`, `exchange.trade` and `sms.send` default to off outside local, testing and demo. `ops:verify-env` FAILs while `sms.send` is on in production unless `MCP_TOOL_SMS_SEND_ACKNOWLEDGED=true`.
- [ ] Spending limits understood. The payment tools (`payment.transfer`, `ramp.start`, `sms.send`) reserve against a daily limit in `mcp_token_policies`. Since v7.18.1 the limit the person picks on the consent screen is saved for their approval of the app, so refreshed tokens share one allowance (#1252). A missing or unknown choice gets `MCP_DEFAULT_DAILY_LIMIT_MINOR` ($500). A token without a user has no policy, and its payment calls still fail with `NO_POLICY`.
- [ ] Dev-portal tool count matches `config('mcp.tools')` (`developers/mcp-tools.blade.php`).

## 8. Observability & alerting

- [ ] `LOG_SLACK_WEBHOOK_URL` set — the default log stack auto-includes Slack when present, routing every `Log::critical`/`emergency` to Slack (PR #1133). `LOG_SLACK_LEVEL` defaults to `critical` (does not inherit `LOG_LEVEL`).
- [ ] `METRICS_TOKEN` (or `METRICS_ALLOWED_IPS`) set — `MetricsAccessMiddleware` gates `/api/monitoring/{metrics,prometheus}` + `/api/metrics/prometheus`; with neither configured it **fails closed (403) in production** (PR #1133). `/api/monitoring/health`, `/api/monitoring/ready` and `/api/monitoring/alive` stay public for k8s probes.
- [ ] Error tracking (Sentry/Rollbar) + APM wired; alert thresholds set.
- [x] Queue workers cover **every** managed queue (`config/queue.php` `managed_queues`), enforced on every PR by `tests/Feature/Ops/QueueCoverageTest.php` (PR 0A). Production runs **Supervisor** off `etc/supervisor.conf` (not Horizon). **After deploying the config you MUST run on the server:** `supervisorctl reread && supervisorctl update && supervisorctl restart all`, then confirm with `supervisorctl status` that `finaegis-transfers-worker`, `finaegis-mobile-worker`, and `finaegis-aux-worker` are RUNNING — a `git pull` alone does not restart workers. Failed-job handling: `database-uuids`, 1-week retention.
- [ ] Run `php artisan ops:verify-env` as a manual deploy step after `git pull`, **before** `migrate` — deploy is a manual `git pull` + manual artisan steps, so the `deploy.yml` gate is not in the live path and env guards (peppers, demo-modes, RAILGUN/SOC-2 provider checks) only fire when the command is run.

## 9. Data & backups

- [ ] **Scheduled DB backups are wired** (spatie/laravel-backup): `backup:run --only-db` runs daily at 01:30 UTC and `backup:clean` at 02:30 UTC, both production-only with `Log::critical` on failure (`routes/console.php`). DB-only by design — the event store is the ledger. Remaining ops steps before checking this off: set `BACKUP_DISK=s3` + the S3 bucket credentials in the prod `.env` (default is `local`, which keeps dumps on the same box — `ops:verify-env` FAILs that in production and WARNs elsewhere), then **run one restore drill** against a real dump (see the Backup Verification section of `OPERATIONAL_RUNBOOK.md`; `backup:list` / `backup:monitor` cover ongoing verification). Managed-DB snapshots remain a sensible second layer, but are no longer the only layer.
- [ ] Database encryption at rest + TLS in transit (infra layer).
- [ ] GDPR erasure path verified, including `IapReceiptPseudonymiser` (requires the pepper from §2 — it hard-throws otherwise).

## 10. Mobile-specific

- [blocked] **Device attestation is OFF and intentionally so.** `MOBILE_ATTESTATION_ENABLED=false` in both prod templates. `config/mobile.php` documents: "DO NOT FLIP TO TRUE WITHOUT FIRST WIRING THE MOBILE-SUPPLIED CHALLENGE" — `BiometricJWTService::verifyDeviceAttestation()` passes an empty challenge to `AppleAttestationVerifier`, so iOS always fails until controllers plumb `device_challenge` through. **Blocked on mobile challenge plumbing** — coordinate with the mobile team; do not enable in isolation. (`ops:verify-env` FAILs if enabled without the Apple/Google attestation keys.)
- [ ] Reviewer/demo accounts provisioned for app-store review per `docs/operations/reviewer-accounts.md` (operator-only; expiry sweep at 00:10 UTC).
- [ ] Push notifications (FCM) configured for Helius/Alchemy webhook delivery.

## 11. Virtual cards (v7.18.0)

Cards run through the provider layer in `app/Domain/CardIssuance/Providers/`. Lepo Finance is the first provider. Named third-party providers: Integration adapters for third-party APIs; no partnership or endorsement implied. Any card services will be provided by licensed third parties under their own terms.

- [ ] `CARD_PROVIDERS_ENABLED` decided. Empty means cards are not offered. `.env.zelta.example` sets `lepo` with empty Lepo keys, so either fill the keys below or clear the value; otherwise `ops:verify-env` FAILs.
- [ ] Pilot settings: `LEPO_API_KEY` and `LEPO_WEBHOOK_SECRET` set (`ops:verify-env` FAILs when Lepo is in use and either is empty); `LEPO_CARD_TYPE_ID_VIRTUAL` set (WARN when empty); `CARD_PROVIDER_LEPO_PILOT_ONLY=true` with `CARD_PROVIDER_LEPO_PILOT_USERS` listing the pilot users (WARN when empty, because nobody can get a card).
- [ ] Lepo webhook endpoint `POST /api/v1/webhooks/lepo` registered in the Lepo partner portal.
- [ ] `CARD_TOPUPS_ENABLED=false` until Lepo Finance answers the open questions about how the money moves. The `AdvanceLepoTopUps` job is scheduled every minute and does nothing while the flag is off. Operators use `cards:inspect-user` and `cards:resolve-topup` for a top-up that stopped with an unclear result.
- [ ] A shared cache store (Redis) is configured. 3-D Secure approval codes are kept encrypted in the cache for at most ten minutes and are never written to the database or the logs.
- [ ] `CARD_ISSUER=demo` for the older issuer adapter layer. Do not set `CARD_ISSUER=lepo`, because that switch has no Lepo binding. With `CARD_ISSUER=fincard`, `fincard:reconcile-balances` runs hourly, `fincard:inspect-user` is the support command and the webhook is `POST /api/v1/webhooks/fincard`.

## 12. Pre-launch verification

- [ ] `./vendor/bin/pest --parallel` green (note: CI skips a portion of the suite; run the MultiConnection job against real MySQL).
- [ ] `XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G` clean (Level 8).
- [ ] `php-cs-fixer` + PHPCS (v4.0.1) clean.
- [ ] Smoke tests against the deployed environment (deposit, IAP verify, wallet send, Bridge webhook) pass.

---

**Last Updated**: November 24, 2026 (v7.18.1 update of the June 2026 v7.15 rewrite, which superseded the Sept 2024 version)
**Owner**: Platform / Ops
**Companion docs**: `docs/operations/*` runbooks, `CLAUDE.md`, `docs/10-OPERATIONS/OPERATIONAL_RUNBOOK.md`
