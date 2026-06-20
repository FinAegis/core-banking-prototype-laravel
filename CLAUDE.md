# CLAUDE.md

## Essential Commands

```bash
# Code quality (run before commit)
./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php
XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G
./vendor/bin/pest --parallel --stop-on-failure

# Development
php artisan serve                    # Start server
npm run dev                          # Vite dev server
php artisan l5-swagger:generate      # API docs

# Solana operations
php artisan solana:backfill                    # Register addresses for existing users
php artisan solana:sync                          # Push addresses to Helius webhook
php artisan solana:backfill-transactions       # Fetch historical tx from Helius API

# User & Admin management
php artisan user:create --admin      # Create user (--admin for admin role)
php artisan user:promote user@email  # Promote existing user to admin
php artisan user:demote user@email   # Remove admin role
php artisan user:admins              # List all admin users

# Reviewer / demo accounts (operator-only; requires admin role)
php artisan account:provision-reviewer --email=appreview@... --operator-email=admin@...
php artisan account:list-reviewers
php artisan account:disable-reviewer --email=X          # or --all-expired
php artisan account:purge-reviewer --email=X --confirm  # anonymizes email + disables

# Bridge.xyz ramp ops
php artisan bridge:inspect-user user@example.com         # print bridge_customer + va + ramp sessions
php artisan bridge:sync-dev-fee --email=user@example.com # reconcile dev_fee_bps with current tier
php artisan bridge:sync-dev-fee --all --dry-run          # batch backfill (preview mode)

# Deploy preflight (CI/CD gate)
php artisan ops:verify-env --json    # exit 1 = blocked; FAILs only block in production (or --strict)
```

## Architecture

- **Web3 Integration**: `app/Infrastructure/Web3/` (EthRpcClient, AbiEncoder) — also legacy `app/Domain/Relayer/Services/EthRpcClient.php`
- **ZK Circuits**: `storage/app/circuits/` (Circom sources + Solidity verifiers)
- **61 domains** in `app/Domain/` (DDD bounded contexts)
- **Payment Protocols**: x402 (Coinbase), MPP (Stripe/Tempo), AP2 (Google)
- **HyperSwitch** (`app/Domain/Payment/Services/HyperSwitch/`): EXPERIMENTAL but WIRED (PR #1118) — opt-in via `HYPERSWITCH_ENABLED` (off by default; Stripe is the default deposit rail). `DepositController` routes card deposits through `HyperSwitchPaymentService::startDeposit`; the webhook credits via `AccountCreditService` (amount from the stored intent, idempotent via `processed_webhook_events`, deadlock-safe — tenant-connection credit kept out of the default-connection claim txn). Intent status flows pending→processing→completed|`completion_failed` (the latter for operator reconciliation — failures are never disguised as completed). Routing-intelligence/analytics are NOT exposed yet. Webhook credit FK pitfall: `account_balances.asset_code`→`assets.code`, so MultiConnection tests must seed the asset
- **Packages**: `packages/zelta-sdk/` (Payment SDK), `packages/zelta-cli/` (CLI binary)
- **Event Sourcing**: Spatie v7.7+ with domain-specific tables
- **CQRS**: Command/Query Bus in `app/Infrastructure/`
- **GraphQL**: Lighthouse PHP, 45 domain schemas
- **Multi-Tenancy**: Team-based isolation (`UsesTenantConnection` trait)
- **Event Streaming**: Redis Streams publisher/consumer with DLQ + backpressure
- **Post-Quantum Crypto**: ML-KEM-768, ML-DSA-65, hybrid encryption
- **Stack**: PHP 8.4 / Laravel 12 / MySQL 8 / Redis / Pest / PHPStan Level 8

## Code Conventions

```php
<?php
declare(strict_types=1);
namespace App\Domain\Exchange\Services;
```

- Symfony Console 7.x: use constructor `parent::__construct('name')`, NOT `$defaultName` static property
- Eloquent: always set explicit `$table` when class name doesn't match (e.g. `WebSocketSubscription` → `websocket_subscriptions`)
- Import order: `App\Domain` → `App\Http` → `App\Models` → `Illuminate` → Third-party
- Commits: `feat:` / `fix:` / `test:` / `refactor:` + `Co-Authored-By: Claude <noreply@anthropic.com>`
- Tests: Always pass `['read', 'write', 'delete']` abilities to `Sanctum::actingAs()`

## CI/CD

| Issue | Fix |
|-------|-----|
| Cache counters (concurrent) | Use `Cache::add($key, 0, $ttl)` + `Cache::increment()` — never read-then-write |
| Service locator in hot paths | Inject via constructor, don't use `app()` — especially in latency-sensitive code |
| PHPStan type errors | Cast return types, add `@var` PHPDoc, null checks |
| PHPStan `->first()` nullable | Use `assert($x instanceof Model)` after expect not-null |
| PHPStan `json_encode` | Cast `(string) json_encode(...)` — returns `string\|false` |
| PHPStan `env()` in config | Cast `(string) env(...)` before `explode()` etc. |
| Unit tests use `config()` | Add `uses(Tests\TestCase::class)` — pure unit tests lack app container |
| Test scope 403s | Add abilities to `Sanctum::actingAs($user, ['read', 'write', 'delete'])` |
| Code style | `./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php` |
| PHPCS | `./vendor/bin/phpcbf --standard=PSR12 app/` |
| Financial arithmetic | Always use `bcmath` (`bcadd`, `bcsub`, `bcmul`, `bcdiv`) — NEVER `(float)` for money. Normalize with `bcadd($val, '0', 4)` |
| DB transactions | Wrap multi-table financial writes in `DB::transaction()` — use `lockForUpdate()` for balance checks |
| XML parsing | Always pass `LIBXML_NONET` to `SimpleXMLElement` when parsing external input (XXE prevention) |
| PHPCS version | CI uses PHPCS v4.0.1 — run `./vendor/bin/phpcs` locally to match before pushing |
| PHPStan `numeric-string` | bcmath requires `numeric-string` type — use `bcadd($val, '0', 4)` to normalize, not `(float)` cast |
| `assert()` as auth guard | Use `if (!$user instanceof User) return 401` — `assert()` compiled out with `zend.assertions=-1` |
| MariaDB UUID columns | Must be RFC 4122 (version=4 nibble, variant=10xx bits) — raw hashes rejected |
| Webhook auth bypass | Use `app()->environment('local', 'testing')` — never `return true` for non-prod |
| Solana addresses | Case-sensitive — never `strtolower()` (unlike EVM which lowercases) |
| Helius API key | Must be query param `?api-key=` — does NOT support Authorization header |
| Webhook metadata | Whitelist fields via `array_intersect_key()` — never store raw `$tx` payload |
| Bypass flag missing test | Every new bypass flag in `account_flags` needs a matching feature test in `tests/Feature/AccountProvisioning/Bypasses/` asserting both sides (flag set = allow, flag unset = enforce) |
| Multi-connection deadlock under DB::transaction | Do not wrap flows that touch UsesTenantConnection models in `DB::transaction()` — those models run on a separate MySQL session and will self-deadlock against the wrapping connection's row locks. Add a regression test in `tests/MultiConnection/`. |

```bash
gh pr checks <PR_NUMBER>              # Check PR status
gh run view <RUN_ID> --log-failed     # View failed logs
```

## Distribution Packages

Brand in UI stays "Zelta" — only distribution package identifiers use the `@finaegis` scope (the `@zelta` npm scope was already taken). PSR-4 namespaces (`Zelta\\`) and CLI bin name (`zelta`) are unchanged.

| Registry | Package | Tag trigger |
|---|---|---|
| npm | `@finaegis/cli` | `cli-v*` |
| npm | `@finaegis/sdk` | `js-sdk-v*` |
| npm | `@finaegis/payment-sdk` | (future) |
| Packagist | `finaegis/payment-sdk` | `sdk-v*` |
| Packagist | `finaegis/php-sdk` | `php-sdk-v*` |
| PyPI | `finaegis` | `py-sdk-v*` |

Required repo secrets for release workflows: `NPM_TOKEN` (must be npm **Automation** token — classic tokens get 403 under 2FA), `PYPI_TOKEN`, `PACKAGIST_USERNAME`, `PACKAGIST_TOKEN`, `MIRROR_PAT` (fine-grained PAT with Contents:write on `FinAegis/payment-sdk`, `FinAegis/cli`, `FinAegis/php-sdk`).

Packagist sources the three PHP packages from **split-mirror repos**, not the monorepo — Packagist only reads root `composer.json`. The `monorepo-split.yml` workflow auto-pushes `packages/zelta-sdk/`, `packages/zelta-cli/`, `sdks/php/`, and `packages/finaegis-mcp-stdio/` into their respective mirrors on every `main` push and release tag (via `splitsh/lite`). Mirror tags use a stripped prefix: `sdk-v1.0.1` → mirror tag `v1.0.1`.

## Admin Panel — Brand & Module Visibility

- Brand fallback in `AdminPanelProvider` is `Zelta` (production); demo deployments override with `BRAND_NAME`
- Auth pages read `config('brand.name')` — never hardcode "FinAegis" in `authentication-card-logo.blade.php` or `application-logo.blade.php`
- Footer "open-source" tagline only renders in demo mode or when `SHOW_PROMO_PAGES=true`
- Non-admin users hitting `/admin` redirect to `/dashboard` with a flash error via `App\Filament\Http\Middleware\RedirectNonAdmins` (replaces Filament's bundled `Authenticate` so non-admins don't get a bare 403)
- Resources gate by nav group via `App\Filament\Admin\Traits\RespectsModuleVisibility` (already on every Resource)
- Widgets gate by `$adminModule` (matching a nav group) via `App\Filament\Admin\Traits\WidgetRespectsModuleVisibility` — set on every widget under `app/Filament/Admin/Widgets/`. Widgets with their own `canView()` AND the module check via `static::adminModuleAllowsView()`
- Production envs default `SHOW_PROMO_PAGES=false`; Zelta also pins `ADMIN_MODULES` to the mobile-wallet scope in `.env.zelta.example`

## MCP Server (v7.11.0+)

- Public endpoint: `https://mcp.zelta.app/mcp` (subdomain handled in `bootstrap/app.php`)
- Subdomain routes: `app/Domain/MCP/Routes/api.php` — minimal middleware (no CSRF, no Sanctum, no web session)
- Tool catalog: `config/mcp.php` (`tools` key) — kill-switches per tool via `MCP_TOOL_*` env vars
- OAuth AS: Laravel Passport (existing) extended with DCR at `/oauth/register`
- Spec: `docs/superpowers/specs/2026-04-27-mcp-server-design.md`
- npm wrapper: `packages/finaegis-mcp-stdio/` published as `@finaegis/mcp`, mirrored to `FinAegis/mcp` on `mcp-v*` tags
- Public-directory submissions: `packages/finaegis-mcp-stdio/server.json` (Official MCP Registry, namespace `app.zelta/mcp`) + `smithery.yaml`. Runbook: `docs/operations/mcp-directory-submissions.md` — DNS verification, publish CLI, Connectors form values, status tracking

| Pitfall | Fix |
|---|---|
| Scope names use snake_case + `:` separator (`accounts:read`, `payments:write`) | Match exactly — Passport's `Scope::can()` is case-sensitive |
| Subdomain not resolving | Verify Cloudflare CNAME for `mcp.*` and the bootstrap branch ordering (mcp before protocol subdomains) |
| 401 with no `WWW-Authenticate: Bearer resource_metadata=...` | `McpOAuthGuard` not applied — check route middleware list |
| `Auth::user()` returns null inside a tool under MCP | Guard must call `Auth::shouldUse('api')` after token resolution; existing tools call `Auth::user()` against the default guard |
| `http://localhost` rejected at DCR | Use `127.0.0.1` (RFC 8252 §7.3) — our validator is strict on the literal IP |
| Float-money in catalog amounts | Saga converts major→minor via `bcmath`; never `(int)($amount * 100)` |
| Stale tool count in dev portal | `developers/mcp-tools.blade.php` — keep in sync with `config('mcp.tools')` count |
| Connectors Directory rejects "missing tool annotations" | Every catalog entry needs a `title`; `tools/list` derives `readOnlyHint`/`destructiveHint`/`idempotentHint` from `is_write` automatically (`JsonRpcRouter::handleToolsList`) |

## Wallet Send (v7.12.0+)

Non-custodial flow. Privy holds the keys (passkey-controlled smart accounts on EVM, device-bound ed25519 on Solana); the device signs every transaction. Backend never sees private key material.

- Auth bridge: `POST /api/v1/auth/privy-login` — JWT verified via JWKS (firebase/php-jwt), exchanged for a Sanctum token
- Web `/login` Privy email-OTP (gated by `MCP_WEB_PRIVY_LOGIN=true`): server-side REST proxy to `POST /api/v1/passwordless/{init,authenticate}` on `auth.privy.io`. Issues a Laravel session via `Auth::login()` and redirects to `intended()`. Replaces legacy Jetstream email+password when the flag is on. Same `User` table + `firstOrCreate(privy_user_id)` lookup as mobile, so cross-client account merging Just Works. See `app/Http/Controllers/Web/PrivyWebAuthController.php`
- Address registration: `POST /api/v1/wallet/addresses` — mirrors EVM smart-account address across polygon/base/arbitrum/ethereum, plus one Solana ed25519 row in `blockchain_addresses`
- Send: `POST /api/v1/wallet/transactions/prepare` returns unsigned payload, `POST /api/v1/wallet/transactions/submit` accepts the signed blob
- Sponsored-send guardrail: EVM gas is Pimlico-sponsored, so sends cost the platform money. `config/wallet.php` `sponsorship` caps per-user (30/day) + global (5000/day) sends — cache counters, reset 00:00 UTC, return HTTP 429 (`SEND_DAILY_LIMIT_REACHED` / `SEND_TEMPORARILY_UNAVAILABLE`). Ethereum L1 sends are disabled — `WALLET_SEND_EVM_NETWORKS` defaults to `polygon,base,arbitrum` (L1 gas is uncapped); a disabled network returns 422 `NETWORK_DISABLED`
- Wire contract: prepare/submit accept snake_case (canonical: `quote_id`, `intent_id`) and still accept legacy camelCase (`quoteId`, `intentId`). `evm.ownerPasskeyCredentialId` stays camelCase. `Idempotency-Key` is an HTTP header, not a body field.
- Solana fee-payer sponsorship: a non-custodial wallet holds SPL tokens but no SOL, so it cannot pay its own tx fee. When `WALLET_SOLANA_SPONSOR_SECRET_KEY` is set (base58 64-byte ed25519 secret) the sponsor account becomes the fee payer (account index 0) and `SolanaSendSubmitter` co-signs server-side; the device still signs the same opaque message bytes (mobile contract unchanged). Two-signer message — signatures ordered `[sponsor, sender]`. Unset = legacy single-signer (sender pays, fails for a 0-SOL wallet). `solana:check-sponsor-balance` runs hourly and alerts (CRITICAL + non-zero exit) when the sponsor SOL balance drops below `WALLET_SOLANA_SPONSOR_LOW_BALANCE_LAMPORTS`
- Confirmation: `HeliusTransactionProcessor` for Solana (existing webhook), `PollEvmWalletSendConfirmations` for EVM (cron, every minute)
- Operator commands: `php artisan privy:verify-jwt <token>`, `php artisan wallet:inspect-user <email>`
- Spec: `docs/superpowers/specs/2026-05-05-wallet-privy-noncustodial-design.md` (if present); see PR #1017

| Pitfall | Fix |
|---|---|
| `GuzzleHttp\ClientInterface` unbound | Bind to `Client::class` in `AppServiceProvider::register` — without it, `PrivyJwtVerifier` fails to resolve and `/privy-login` 500s |
| EVM address case | Always `strtolower()` for storage (matches our existing EVM convention) — Solana stays case-sensitive |
| Float-money amounts | Preparers validate `amount` against numeric-string + bcmath; never `(float)` for money |
| Idempotency key on the wrong field | It's an HTTP header (`Idempotency-Key`), not a body field — matches `/pay`/`/pay/card` |
| `quote_id` vs `quoteId` | prepare/submit normalise both spellings (`$request->merge` before `validate`) — snake_case is canonical, camelCase kept for older builds. Validation errors are keyed `quote_id`/`intent_id` |
| Adding a new send/receive asset | Add the case to `App\Domain\MobilePayment\Enums\PaymentAsset` (decimals + label) AND make sure `EvmTokens` / `SolanaTokens` have the contract / mint. The receive QR builder uses `SolanaTokens::mintFor()` per Solana Pay spec — `spl-token` must be the mint, not the symbol. v7.13.1 added USDT this way |
| Network casing on quote vs prepare | Both endpoints validate against `PaymentNetwork::values()` — case-sensitive: `SOLANA`/`TRON` uppercase, `polygon`/`base`/`arbitrum`/`ethereum` lowercase. Pre-existing enum inconsistency; quote response is `{ success, data: { quote_id, network, ... } }` (snake_case keys inside `data`). Request bodies on prepare/submit accept snake_case (canonical) or camelCase for the id field, but the `network` value itself is not normalised — mobile must send the exact enum value |

## IAP / Subscriptions (v7.13.0+)

Plan B mobile-driven subscriptions (Apple App Store + Google Play). Entry point: `POST /api/v1/subscription/iap/verify` (prefix is **singular** `subscription`). Domain: `app/Domain/Subscription/`. Persistence: `subscriptions`, `iap_subscriptions`, `processed_webhook_events`, `revenue_outbox_events`, `revenue_events`, `trial_card_fingerprints`, `subscription_consent_log`.

- All IAP conflict states surface as **HTTP 409 `ERR_SUB_002`** with `conflict.kind` (`two_stores_active` / `different_zelta_user` / `family_sharing_unsupported` / `stale_receipt`) + `attemptedSource` + `existingSubscription{ source, currentPeriodEndsAt }`. Mobile branches on `kind`; do not introduce new `ERR_SUB_*` codes for IAP conflicts.
- Apple JWS chain validation pins to Apple Root CA G3 at `storage/app/apple/AppleRootCA-G3.cer` (fingerprint `63343ABF…E9179`). `APPLE_JWS_VERIFICATION_BYPASS=true` is staging-only and rejected in production.
- Webhook ingestion is idempotent via `processed_webhook_events (provider, event_id)` unique index — both Apple App Store Server Notifications V2 and Google Play RTDN flow through the same pseudonymisation + outbox path.
- Receipts are HMAC-pseudonymised by `IapReceiptPseudonymiser` using `IAP_RECEIPT_PEPPER` before persistence or logging. **Never rotate the pepper** — it's one-way; rotation breaks recovery of all pre-rotation receipts.

| Pitfall | Fix |
|---|---|
| Dropping `existingSubscription` for stale-receipt / family-sharing cases | Mobile hard-requires the field for every `ERR_SUB_002`. Use `existingSubscriptionFromVerified()` to derive it from the receipt's reported source + expiry when there's no on-file subscription |
| New `ERR_SUB_*` code for an IAP conflict | Route through `ERR_SUB_002 { conflict: { kind } }` instead. Mobile's `subscriptionConflict.ts` returns null for any other code |
| `IAP_RECEIPT_PEPPER` empty in prod | `IapReceiptPseudonymiser::pseudonymise()` hard-throws `RuntimeException` on first call. Generate via `openssl rand -hex 32`; set before first verify request |
| Apple JWS verification bypass slips into prod | `APPLE_JWS_VERIFICATION_BYPASS=true` is the auth bypass for the entire Apple IAP surface. Guard at deployment time (env diff, smoke test that prod verifier rejects a known-bad JWS) |
| Privy `/passwordless/*` returns 403 "Must specify origin" | `PrivyEmailOtpClient` sends `Origin: <web origin>` from `config('privy.web_origin')` (env: `PRIVY_WEB_ORIGIN`, falls back to `app.url`). Origin must also be on Privy dashboard → Allowed origins. Mobile JWT path is unaffected |
| `POST /api/v1/notifications/register-device` returns 409 after a Privy account switch | `MobileDeviceService::registerDevice` only blocks reassignment for **credential-bound** devices (`biometric_enabled` or `passkey_enabled`) — those still throw `DeviceTakeoverAttemptException` → **HTTP 409 + `DEVICE_REGISTERED_TO_DIFFERENT_USER`**. Push-only devices (no bound credentials) are reassigned automatically on re-registration, with an audit row written to `device_reassignment_log`. Recovery for credential-bound devices: operator-only `php artisan mobile:reassign-device --device-id=X --to-user=Y --operator-email=Z --confirm` (never raw SQL) |

## Bridge.xyz Ramp (v7.15.0+)

Primary v1 fiat ↔ stablecoin rail (bank transfers in/out, USDC on Polygon). Domain: `app/Domain/Compliance/Kyc/` (Kyc abstraction lives under Compliance, not its own top-level domain) + `app/Infrastructure/Bridge/` (HTTP client + webhook verifier shared between Ramp + Kyc). Persistence: `bridge_customers`, `ramp_sessions` (gains `deposit_instructions` encrypted + `source` enum).

- **Webhook URL**: `POST /api/v1/webhooks/bridge` — single endpoint for both KYC (`customer.kyc_link_*`) and ramp (`virtual_account.activity`, `transfer.*`) events. Event-level dedupe via `processed_webhook_events (provider='bridge', event_id)`. Configure Bridge dashboard to POST here.
- **Setup endpoints** (mobile-facing, no `require.kyc` middleware): `GET /api/v1/user/bridge-setup-status` + `POST /api/v1/user/bridge-kyc-link`. Lazy Bridge customer creation on first kyc-link request with `Idempotency-Key: bridge_customer:{user_id}`.
- **Markup mechanism** (ADR-0006): per-customer `developer_fee_bps` on Bridge customer record — Free=75, Pro=0. Auto-PATCHed via `SubscriptionTierChanged` event listener wired in `KycServiceProvider::boot`. Operator command `bridge:sync-dev-fee` for manual reconciliation.
- **VA provisioning**: auto-triggered on `customer.kyc_link_completed` if user has a Polygon address in `blockchain_addresses`; if not, `BlockchainAddressBridgeObserver` retries when the address is registered later.
- **WS events** on `private-user.{userId}`: `bridge.kyc.completed`, `bridge.kyc.rejected`, `bridge.virtual_account.ready`. Push notification fallback fires on the two KYC terminals only (per the locked grilling decision in PR #1090 reply).
- ADRs: `docs/adr/0005-bridge-xyz-over-stripe-crypto-onramp.md` (why Bridge, not Stripe Crypto Onramp); `docs/adr/0006-bridge-developer-fees-as-markup-mechanism.md` (how the 0.75% Zelta markup physically flows). Ops runbook: `docs/operations/bridge-ramp.md`.

| Pitfall | Fix |
|---|---|
| `users.kyc_status` confused with Bridge KYC | `users.kyc_status` is **Ondato/TrustCert** (existing). Bridge KYC state lives in `bridge_customers.kyc_status` — partitioned per §7.5 of the brief. Never conflate the two |
| `blockchain_addresses.user_id` lookup returns no row | The FK is `user_uuid` (links to `users.uuid`), NOT `user_id`. `BridgePostKycHandler` and `BlockchainAddressBridgeObserver` both use `where('user_uuid', $user->uuid)` |
| Bridge webhook signature scheme | `BridgeWebhookVerifier` verifies Bridge's current **asymmetric** scheme: header `X-Webhook-Signature: t=<unix_ms>,v0=<base64>`, RSA-SHA256 over `<t>.<body>` against the per-endpoint public key in `BRIDGE_WEBHOOK_PUBLIC_KEY` (accepts raw PEM, `\n`-escaped, or base64-of-PEM). Legacy HMAC (`Bridge-Signature`/`X-Webhook-Signature: t=<sec>,v1=<hex>` keyed by `BRIDGE_WEBHOOK_SECRET`) kept as auto-detected fallback for tests/older tenants; inert in prod where no secret is set. Still confirm against a real sandbox payload before go-live (timestamp-units/RSA-padding unverified against live bytes) |
| `RAMP_PROVIDER=stripe_bridge` still in deployed `.env` | Recognised as a deprecated alias (logged warning) that resolves to `stripe_crypto_onramp` (the renamed scaffolding from ADR-0005). Update `.env` to the canonical name; alias removal in v1.1 |
| `BridgeWebhookVerifier` dev passthrough | Fires only when BOTH `BRIDGE_WEBHOOK_PUBLIC_KEY` and `BRIDGE_WEBHOOK_SECRET` are empty: accepts any well-formed request in non-production, fails closed (401) in production. Set `BRIDGE_WEBHOOK_PUBLIC_KEY` (the current platform credential) before going live |
| Bridge offramp (`type=off`) throws `OfframpNotAvailableException` | v1 is bank-rail onramp only per the brief. `POST /api/v1/ramp/session` maps it to **HTTP 422 + `OFFRAMP_NOT_AVAILABLE`** (never a 500). Offramp lands in v1.1 alongside SWIFT support and additional networks (Solana, Base, Arbitrum) |
| VA never gets created after KYC approval | User had no Polygon `blockchain_addresses` row at KYC-completion time. `BlockchainAddressBridgeObserver` auto-retries when the address is registered; if the row already exists, run `php artisan bridge:inspect-user --email=X` to confirm state and re-trigger by deleting + re-creating the address (or call `BridgePostKycHandler::tryProvisionVirtualAccount` from tinker) |
| Cross-domain ordering | KycServiceProvider attaches a second observer on `BlockchainAddress` (separate from Wallet/BlockchainAddressObserver which handles Helius/Solana sync). The two observers fire independently; don't merge their concerns |

## RAILGUN Non-Custodial Privacy (v7.16.0+)

RAILGUN privacy is **mid-migration** from custodial (v5.6.0 server-side bridge, seed = `hash_hmac(userId, app.key)`) to **non-custodial** (device holds all keys, like Wallet Send). Proving can't be delegated without surrendering custody, so the device proves on-device (embedded `@railgun-community/wallet` v10 + native Groth16 prover, Railway-style); the backend is reduced to support services. Design: `docs/superpowers/specs/2026-06-20-railgun-noncustodial-design.md`. Mobile guide: `docs/RAILGUN_MOBILE_INTEGRATION.md`. Infra runbook: `docs/operations/railgun-infra.md`.

- **Backend support endpoints** (all under `/api/v1/privacy/*`, `auth:sanctum`): `POST /wallet/register` (device registers its PUBLIC `0zk` address — server stores NO seed; `railgun_wallets.encrypted_mnemonic` is nullable), `GET /engine-config` (SDK-exact bootstrap: `FallbackProviderJsonConfig`, `NetworkName`, POI node URLs, `TXIDVersion` — directly consumable by `startRailgunEngine`/`loadProvider`).
- **RPC proxy**: `POST /api/v1/privacy/rpc/{network}` is authed by a **signed URL** (not Sanctum — the SDK's `loadProvider` takes a plain URL string, no header injection). `engine-config` mints it when `RAILGUN_RPC_UPSTREAM_*` is set; the key stays server-side. Method-whitelisted (reads + `eth_sendRawTransaction`), per-user `railgun-rpc` limiter keyed on the signed `u`.
- **Status**: backend Phase 0 + Phase 1 shipped; the on-device engine (mobile) is gated on a de-risking spike (incl. a Privy signature-determinism check that decides the seed model). Phase 3 will strip the custodial bridge + `DelegatedProofService` + `encrypted_mnemonic` — safe since there are no funded wallets yet.

| Pitfall | Fix |
|---|---|
| `artifact_base_url` from engine-config doesn't work | The v9/v10 SDK **hardcodes its IPFS artifact gateway** — `startRailgunEngine` does NOT consume `artifact_base_url`. The mirror is only used if the app's OWN `ArtifactStore.get()` fetches from it on a cache miss |
| `base` network rejected in railgun mode | RAILGUN supports `ethereum/polygon/arbitrum/bsc` only (bsc → `NetworkName` `"BNB_Chain"`). `GET /networks` is the runtime source of truth; the OpenAPI enum was corrected in v7.16.0 |
| Provider API key in `RAILGUN_RPC_*` | That value is served to clients. Use `RAILGUN_RPC_UPSTREAM_*` (proxied, key server-side) instead; `engine-config` defensively drops a client RPC URL that looks like it embeds a credential |
| RPC proxy returns 403 mid-session | The signed URL expired (TTL capped [60,900]s). The app must **refetch `engine-config`** and rebuild the provider |
| Editing custodial shield to "make it work" | Don't — the custodial shield-422 is deliberately unfixed; shield moves on-device in Phase 2. Adding a server-derived `shieldPrivateKey` would re-cement custody |

## Notes

- Feature pages: only visible when `SHOW_PROMO_PAGES=true` (demo mode); production shows app landing page only
- Sitemap: dynamic via `SitemapController`, gated by `SHOW_PROMO_PAGES` — no static sitemap.xml needed
- GraphQL schemas: must be imported in `graphql/schema.graphql` to be registered with Lighthouse
- DeFi connectors: use `UsesDeFiConfig` trait for shared `resolveTokenAddress()`/`getRpcUrl()` methods
- New packages: add PSR-4 to root `composer.json` autoload-dev, then `composer dump-autoload`
- Parallel agents: avoid touching `composer.json`, `bootstrap/app.php` from multiple agents (merge conflicts)
- Feature pages: always update version badge + features/index.blade.php when shipping new features
- Always work in feature branches
- Ensure GitHub Actions pass before merging
- Never create docs files unless explicitly requested
- Prefer editing existing files over creating new ones
- New domains: always add `#import {domain}.graphql` to `graphql/schema.graphql` — schemas are invisible without it
- New domains: update domain count in public views (welcome, about, pricing, developers) and CLAUDE.md
- New domains: add env vars to `.env.production.example` and `.env.zelta.example`
- Use Serena memories for deep architectural context when needed
- Solana constants: `SolanaTokens::KNOWN_MINTS` and `SolanaCacheKeys::balance()` in `app/Domain/Wallet/Constants/`
- Solana webhook: always uses Helius (`HeliusWebhookSyncService`), Alchemy handles EVM only
- Solana tx processor: `HeliusTransactionProcessor` handles all Solana transaction parsing
- EVM tx processor: `EvmTransactionProcessor` mirrors inbound EVM transfers to `blockchain_address_transactions` + `activity_feed_items` (counterpart of `HeliusTransactionProcessor`); `evm:backfill-transactions` seeds pre-mirror history via the Alchemy Transfers API
- Webhook controllers: Helius handles Solana, Alchemy handles EVM — both send FCM push via `PushNotificationService` and mirror transactions via their respective processors
- Inbound Solana dust: native-SOL transfers below `WALLET_SOLANA_DUST_MIN_INBOUND_SOL` (default 0.001 SOL) are recorded for audit but kept out of the activity feed + push (address-poisoning spam). Token transfers are never filtered
- Admin wallet visibility: `UserResource` has read-only relation managers for wallet addresses, blockchain transactions, and wallet sends — support opens a user to see their crypto activity
- Alchemy webhook signing keys: stored in `webhook_endpoints` table (managed by `AlchemyWebhookManager`), not env vars
- Test tables: use `Tests\Traits\CreatesSolanaTestTables` trait for in-memory SQLite schema in webhook/wallet tests
- Multi-connection tests: `tests/MultiConnection/` — runs against real MySQL with `database.force_real_tenant_connection=true` so models with `UsesTenantConnection` use a separate MySQL session. Required check on PRs. See `docs/superpowers/specs/2026-04-26-multi-connection-test-infrastructure-design.md`.
- Parallel agent merges: always check for duplicate `use` imports after merging agent branches
- Reviewer accounts: operator-only tool for app-store review submissions — see `docs/operations/reviewer-accounts.md`. Bypasses are scoped via `account_flags` table; the daily sweep runs at 00:10 UTC via `routes/console.php`.
