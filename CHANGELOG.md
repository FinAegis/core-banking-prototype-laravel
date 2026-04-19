# Changelog

All notable changes to the FinAegis Core Banking Platform will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [7.10.9] - 2026-04-19

### Changed
- **Website + docs sync for v7.10.8** — Version badges, developer-portal "Released" banner, FAQ, changelog page, VERSION_ROADMAP, and per-feature docs now reference v7.10.8 and the newly public registry install commands

### Notes
- Pure documentation release — no runtime, API, or dependency changes

---

## [7.10.8] - 2026-04-19

**Public SDK distribution.** Every SDK and the CLI now install from public registries. Prior `@zelta/*` references were aspirational — the npm `@zelta` scope was owned by a third party, so nothing actually shipped. This release rebuilds distribution under the `@finaegis` scope we control and wires up the Symfony/Laravel-style split-mirror pattern so Packagist can read the three PHP packages out of the monorepo.

### Added
- **Public registry packages (first real releases):**
  - `npm install -g @finaegis/cli` — PHAR bundled, requires PHP 8.4+
  - `npm install @finaegis/sdk` — JavaScript/TypeScript SDK
  - `pip install finaegis` — Python SDK
  - `composer require finaegis/payment-sdk` — x402 + MPP payment SDK (PHP)
  - `composer require finaegis/php-sdk` — banking API SDK (PHP)
- `.github/workflows/monorepo-split.yml` — splitsh/lite-based auto-mirror of `packages/zelta-{sdk,cli}/` and `sdks/php/` into dedicated Packagist-readable repos on every `main` push and release tag
- `.github/workflows/sdk-{javascript,python,php}-release.yml` — tag-triggered registry publishing for each SDK
- Mirror repos created: `github.com/FinAegis/payment-sdk`, `github.com/FinAegis/cli`, `github.com/FinAegis/php-sdk`

### Changed
- **npm scope migration**: `@zelta/cli` → `@finaegis/cli`, `@zelta/sdk` → `@finaegis/sdk` (third-party owns `@zelta` on npm). Brand name "Zelta" unchanged in UI
- **Packagist vendor migration**: `zelta/payment-sdk` → `finaegis/payment-sdk`, `zelta/cli` → `finaegis/cli`
- Developer portal (examples, sdks pages) + partner docs now show real public install commands instead of monorepo path dependencies
- `sdk-release.yml` / `sdk-php-release.yml` Packagist notifications target the split-mirror URLs

### Fixed
- `cli-release.yml` — replaced decade-old Box 2 installer with direct PHAR download from `box-project/box` (#937)
- `cli-release.yml` — PHAR bundles `vendor/` (was only `app/` + `config/`; Symfony Console missing at runtime) (#939)
- `cli-release.yml` — npm `version` field is valid semver (was raw tag name like `cli-v0.2.1`) (#936)
- `cli-release.yml` — PHAR artifact is now uploaded from `build-phar` and downloaded in `publish-npm` so the published tarball actually contains the binary (#936)
- `monorepo-split.yml` — pinned `splitsh/lite` to v1.0.1 (v2.0.0 shipped no binaries) (#940)
- `monorepo-split.yml` — bypassed `actions/checkout`'s credential helper so URL-embedded PAT works for cross-repo push (#941)
- `sdks/javascript/src/types.ts` — `PaginatedResponse<T>.links` no longer violates parent `ApiResponse<T[]>.links` type (#936)
- `packages/zelta-sdk/composer.json` — dropped hardcoded `version: 1.0.0` field; Packagist reads from git tags (#942)
- `packages/zelta-cli/composer.json` — `minimum-stability: dev` + `prefer-stable: true` so the path-repo `finaegis/payment-sdk` resolves when no stable tag is indexed yet (#943)

### Notes
- Zero breaking changes to library APIs. PSR-4 namespaces (`Zelta\\`, `FinAegis\\`), CLI binary name (`zelta`), and user-facing brand ("Zelta CLI", "Zelta SDK") are unchanged
- New repo secret required for split workflow: `MIRROR_PAT` — fine-grained PAT with `Contents: write` on the three mirror repos
- `NPM_TOKEN` must be an npm **Automation** token (classic tokens 403 under 2FA)

---

## [7.10.7] - 2026-04-15

### Changed
- **Safe-Major Composer Trio** — Upgraded `laravel/tinker` v2→v3, `resend/resend-php` v0→v1, `darkaonline/l5-swagger` v10→v11 (PRs #922–#923)
- swagger-php v5 strictness fix: moved misattached `#[OA\Get]` annotation on `X402StatusController::supported()`

### Fixed
- Pre-existing OpenAPI duplicate `@OA\Response(response: 200)` on `wellKnown()` surfaced by swagger-php v5

---

## [7.10.6] - 2026-04-14

### Changed
- **Composer Dependency Sweep** — 230 packages upgraded within existing semver ranges (PR #919)
- Notable: `laravel/framework` 12.55→12.56, `phpstan` 2.1.42→2.1.47, `php-cs-fixer` 3.94→3.95, `aws/aws-sdk-php` 3.373→3.379
- php-cs-fixer 3.95.1 repo-wide reformat (65 files, whitespace-only)

### Fixed
- 6 PHPStan errors surfaced by Larastan 3.9.5 rule tightening (dead null coalesces, Collection invariance, `toThrow()` typing)

---

## [7.10.5] - 2026-04-14

### Changed
- **npm Dependency Sweep** — Semver-safe `npm update` + lockfile dedup (~500 lines removed) (PR #918)
- Notable: `autoprefixer` 10.4→10.5, `postcss` 8.5.8→8.5.9, `@ledgerhq/hw-transport-webusb` 6.32→6.33

### Fixed
- Cleared remaining moderate npm audit advisory (`follow-redirects` transitive dedup)

---

## [7.10.4] - 2026-04-14

### Fixed
- **Frontend Security Patch** — Bumped `axios` ^1.13→^1.15 closing 5 critical SSRF advisories (GHSA-3p68-rc4w-qgx5) (PR #916)
- Bumped `vite` ^6.4.1→^6.4.2 closing 1 high path traversal advisory
- Both packages are dev-only; no runtime code changes

---

## [7.10.3] - 2026-04-14

### Fixed
- **Onboarding Welcome Modal Fix** — Removed broken `startTour()` stub that threw `TypeError` on every new registration (PR #914)
- Stripped dead "Take Tour" header button and `startOnboarding()` `.catch` fallback

---

## [7.10.2] - 2026-04-13

### Fixed
- **Deployment Pipeline Fix** — First deployable release since v7.10.0 (PR #905)
- Deploy workflow OOM: unit tests now run in batched PHP processes via `bin/pest-batch`
- `BackpressureHandlerTest` isolation: use array cache driver in pre-deployment validation
- `.env.example` / `.env.zelta.example` dotenv parse errors (unquoted whitespace values)

---

## [7.10.1] - 2026-04-13

### Added
- **Stripe Bridge Ramp** — Working Stripe Crypto Onramp integration replacing scaffolding from v7.10.0 (PR #903)
- Platform-generic `RampProviderInterface` with `normalizeWebhookPayload()` and signature validation
- Lazy `RampProviderRegistry` with factory closures
- Parameterized provider-contract test suite (6 tests per provider)
- Non-custody regression test (zero ledger/transaction row growth on ramp webhook)

### Fixed
- Stripe Crypto Onramp signature verification (`t=<ts>,v1=<hmac>` parsing with HMAC-SHA256)
- Race-safe webhook processing (`DB::transaction()` + `lockForUpdate()` + terminal-state idempotency)
- Real session status fetch (was returning hardcoded `pending`)
- Provider-aware capability validation (BTC through Stripe returns 422 naming the active provider)

### Removed
- Legacy `StripeBridgeWebhookController` and its 9 dead tests

---

## [7.10.0] - 2026-04-08

### Added
- **Webhook Architecture Refactor** — Per-user DB-stored webhook endpoints with encrypted signing keys (PR #902)
- `AlchemyWebhookManager`: auto-provisions per-user Alchemy webhooks with 100K address sharding
- `SmartAccountObserver`: auto-registers EVM addresses on SmartAccount creation
- Async webhook processing via `ProcessAlchemyWebhookJob` and `ProcessHeliusWebhookJob`
- `evm:sync-webhooks` command for bulk EVM address sync
- **Card Pre-Order Waitlist** — POST join (race-safe with `lockForUpdate`) + GET status
- **Paid KYC Verification** — 3 payment methods (wallet deduction, Stripe card, IAP) with `VerificationPayment` audit table
- `RequireKycVerification` middleware blocking Level 0 users from financial endpoints
- **Stripe Bridge Ramp** — Replaces Onramper for fiat on/off-ramp

### Changed
- Provider separation: Alchemy handles EVM only, Helius handles Solana only
- Unique `(tx_hash, chain)` constraint, Cache-based per-tx dedup, spam filtering, reorg detection
- All financial amounts in Ramp domain converted to `bcmath`
- `stripe_client_secret` encrypted at rest via Laravel `encrypted` cast

---

## [7.9.0] - 2026-04-04

### Added
- **Solana Wallet Integration** — Balances wired to `/wallet/balances` and `/wallet/state` endpoints
- Helius webhook stores Solana transactions in activity feed
- FCM push notifications for Solana transaction events
- Alchemy Solana RPC migration (`AlchemyWebhookSyncService` for Solana address management)
- `solana:sync` command with `--provider` flag (replaces `helius:sync`)
- Pre-warm balance cache, transaction backfill command, `/wallet/home` route

### Changed
- Extracted `HeliusTransactionProcessor` for cleaner webhook handling
- Extracted shared Solana constants (`SolanaTokens`, `SolanaCacheKeys`)
- SEO overhaul across all public-facing pages (meta descriptions, branding, schema.org)

### Fixed
- 181 pre-existing PHPStan Level 8 errors resolved
- RFC 4122 UUID generation for MariaDB compatibility
- Duplicate processing prevention per address in Helius webhook
- Helius API key restored as query param (their API requires it)

---

## [7.8.4] - 2026-04-01

### Added
- **GoPlus Address Screening** — Authenticated mode with `app_key`/`app_secret` for on-chain risk scoring
- Multi-layer address screening: OFAC SDN list + GoPlus API for Solana and EVM addresses

### Fixed
- Missing schema.org markup and breadcrumbs on subproduct pages
- Shared layout cleanup: broken social links, branding, redundant config calls
- Code example fixes: double `/api/api/` URLs, `<?php` HTML escaping, env var names
- Blade `@yield` / `@section`/`@show` SEO default fix
- Tenant context verification skipped for global Mobile jobs

---

## [7.8.3] - 2026-04-01

### Fixed
- **Log Spam** — MCP tool, custodian, and exchange rate provider registration logs demoted from `info` to `debug`
- **Blade Compile Error** — `@hasSection('seo')` with `<x-schema>` components caused ParseError; replaced with `@yield('seo', default)`

---

## [7.8.2] - 2026-04-01

### Fixed
- API user registration no longer blocked by Fortify web registration gate (mobile users can always register)
- CRON expression: `fraud.batch.schedule` changed from `'hourly'` to `'0 * * * *'`
- Log rotation: stack channel defaults to `daily` with 14-day retention
- CORS: `X-Tenant-ID` added to allowed headers
- Migration FK fix: `consents.tpp_id` type uuid→string to match `tpp_registrations` FK
- PHPStan `numeric-string` type in `PaymentRailRouter`

### Changed
- **Developer Portal** — Honest SDK install commands, standardized `@zelta/sdk` naming, consolidated rate limits, OpenAPI spec link, Hello World quick start

---

## [7.8.1] - 2026-03-31

### Added
- **Public Changelog** — `/changelog` page with reverse-chronological release timeline (v7.0.0–v7.8.0)

### Changed
- GCU page migrated to `layouts.public` with unified navy/teal brand palette
- Platform page: removed 14 duplicate module cards, replaced with "56 Domain Modules" CTA
- `/features/gcu` now 301 redirects to `/gcu`
- Sitemap and footer updated

---

## [7.8.0] - 2026-03-31

### Added
- **7 New Domains** — ISO 20022 Message Engine, Open Banking PSD2, ISO 8583 Card Network Processor, US Payment Rails (ACH/Fedwire/RTP/FedNow), Interledger Protocol, Double-Entry Ledger, Microfinance Suite
- SEPA Direct Debit/Credit Transfer, intelligent payment routing with ML-style scoring
- Developer Experience: sandbox provisioning, webhook testing, API key management
- 7 new feature detail pages with professional copywriting
- All 15 STRIDE threat model findings resolved (security hardening)
- Production readiness: Helm chart v1.7.0, benchmark commands, env review
- Device attestation wiring, recovery shard improvements, card PATCH route

### Changed
- PHPCS v4.0.1 compatibility
- Domain count raised to 56, GraphQL schemas to 43
- ~500 new tests across 7 domains

### Fixed
- npm audit clean, Railgun bridge patched
- Professional copywriting pass on headlines, CTAs, meta descriptions
- SDK install commands fixed, compliance language updated

---

## [6.7.0] - 2026-03-26

### Added
- **A2A Protocol** — Agent Card at `/.well-known/agent.json` with 5 skills, streaming + push notifications
- Task lifecycle: `tasks/send`, `tasks/get`, `tasks/{id}/cancel`, `tasks/list` with 6-state machine
- SDK Packagist workflow (tag `sdk-v1.0.0` to publish `zelta/payment-sdk`)
- API Sandbox at `/developers/sandbox` with pre-built test examples
- 7 smoke tests for critical page loads and API health

---

## [6.6.0] - 2026-03-26

### Added
- **Zelta CLI v0.2.0** — 25 commands across 8 resource groups (payments, SMS, API management)
- **Zelta Payment SDK** — Composer-installable package with transparent x402/MPP auto-retry
- **Solana HSM Signer** — Ed25519 hardware signing + verifier for production x402 payments
- **WebSocket Payment Gate** — Paid channel subscriptions with `ws.payment` middleware
- Protocol-specific subdomain routing (`x402.api.*` / `mpp.api.*`)
- `.well-known/x402-configuration` discovery endpoint
- CLI distribution pipeline: PHAR build, npm `@zelta/cli`, Homebrew, GitHub release
- SMS demo seeding + mobile rewards auto-creation

---

## [6.5.0] - 2026-03-24

### Added
- **VertexSMS Integration** — SMS service with dynamic EUR→USDC pricing, DLR webhook handler
- MPP-gated `POST /api/v1/sms/send` — agents pay per-SMS via any rail (USDC, Stripe, Lightning)
- x402 USDC rail adapter bridging Coinbase x402 into MPP
- MCP tool `sms.send` for AI agent discovery
- Stripe Connect support for direct settlement to service providers
- Settlement reconciliation reports by rail and country
- AP2 SMS mandates for enterprise campaigns
- **Mobile Launch Readiness** — Quest auto-completion triggers, Apple App Attest, Google Play Integrity, JIT Funding wiring

### Fixed
- DLR handler: `DB::transaction` + `lockForUpdate` + forward-only state machine
- E.164 phone number validation, zero-rate pricing guard

---

## [6.4.3] - 2026-03-24

### Fixed
- **Swagger 403** — `public/docs/` directory conflicted with L5-Swagger `/docs` route; moved Postman collections to `public/postman/`
- Regenerated api-docs.json (2.7MB)

---

## [6.4.2] - 2026-03-23

### Added
- **HyperSwitch Payment Orchestration** — Full REST API client for 150+ payment processors
- `HyperSwitchPaymentService` with deposit/refund and customer mapping
- `HyperSwitchWebhookController` with HMAC-SHA512 verification
- Config: `config/hyperswitch.php` (sandbox + production, routing strategy)

---

## [6.4.1] - 2026-03-23

### Added
- **Helius Webhook Auto-Sync** — `HeliusWebhookSyncService` auto-registers new Solana addresses
- `BlockchainAddressObserver` with async (queued) dispatch
- `Cache::lock` for concurrent address additions
- Reserved address blocklist (System Program, Token Program, USDC mint)
- `helius:sync` command for bulk address sync

### Fixed
- Missing `module.json` for 5 domains (Ramp, Referral, Rewards, VirtualsAgent, VisaCli)

---

## [6.4.0] - 2026-03-23

### Added
- **Machine Payments Protocol (MPP)** — New `MachinePay` domain with Stripe SPT, Tempo, Lightning, Card rails
- `MppPaymentGateMiddleware` with `WWW-Authenticate: Payment` / `Authorization: Payment` headers
- HMAC-SHA256 challenge binding, RFC 9457 error responses
- 2 MCP tools (`mpp.payment`, `mpp.discovery`)
- **AP2 Mandates** — Cart, Intent, Payment mandates with Verifiable Digital Credentials (SD-JWT-VC)
- `AP2PaymentBridgeService` wrapping x402 + MPP as payment methods
- **Solana Launch** — `solana:mainnet` + `solana:devnet` in X402Network enum, Helius webhook for balance monitoring
- Legal positioning: platform disclaimers, non-custodial language

### Changed
- Transaction-level locking on settlement idempotency
- HMAC key separation (derived keys, never reuse app key)
- Admin-only resource monetization, blocked sensitive paths
- 7 dependabot PRs merged

---

## [6.0.0] - 2026-03-17

### Added
- **Developer Portal** — Plugin development guide, GraphQL API docs, Redis Streams docs, MCP/AI Agent tools docs
- **Plugin Marketplace** — Public browse/search/filter, detail pages with permissions and security reviews, 2 example plugins
- **Domain Completeness** — Webhook encrypted secrets + HMAC signing, Newsletter campaign lifecycle, Activity service, Contact ticket workflow, Performance KPI reports
- 4 sub-product detail pages (Exchange, Lending, Stablecoins, Treasury) rebranded to Zelta

### Changed
- Complete Zelta rebrand across 71 blade templates (353 replacements)
- New OG images, favicons (14 sizes), SEO metadata, JSON-LD schemas
- RPC caching (~90% Alchemy reduction) + WebSocket balance events

### Fixed
- HMAC bypass, email injection, LIKE injection, URL validation
- Flaky `UserOperationSigningServiceTest`

---

## [5.14.0] - 2026-03-15

### Added
- **RPC Optimization** — Cache `eth_blockNumber`, `eth_gasPrice`, `getMaxPriorityFeePerGas` with 15s TTL (~90% Alchemy call reduction)
- **WebSocket Balance Events** — Push-based balance updates replacing 60s mobile polling (`wallet.balance_updated`, `wallet.state_changed`, `privacy.balance_updated`)
- **Alchemy Address Activity Webhook** — `POST /api/webhooks/alchemy/address-activity` with HMAC-SHA256 verification for near-instant balance notifications
- Plugin development guide at `/developers/plugins`

### Changed
- Balance cache TTL increased from 30s to 120s
- `MobileWalletController` fixed to use `WalletBalanceProviderInterface`

---

## [5.12.0] - 2026-03-09

### Added
- **Onramper Integration** — Provider-agnostic fiat on/off-ramp aggregator with session management (PRs #722–#723)
- **Foodo Insights** — Restaurant analytics dashboard with frontend review and mobile polish (PRs #720–#721)
- **Referral System** — Code generation and sponsorship rewards with referral tracking (PR #715)
- **Gas Sponsorship** — Free user transactions via gas sponsorship service (PR #714)
- **Banners API** — Admin panel for promotional carousel with CRUD endpoints (PR #713)
- **V1 Notifications API** — Paginated list with type filtering and unread count (PR #712)
- **On/Off Ramp API** — Provider-agnostic session management for fiat ramp operations (PR #716)
- **Website Launch** — Env-driven `SHOW_PROMO_PAGES` flag, homepage content refresh (PRs #702–#706)

### Changed
- **Design System v2** — Complete frontend overhaul across all pages (PRs #726–#734):
  - Dark hero sections with gradient backgrounds on all feature pages
  - Consistent typography, breadcrumbs, and scroll animations
  - Inner page migration: content pages, CGO, secondary pages
  - Button sizing (`btn-lg`), table scroll, accessibility (`aria-hidden`)
  - CGO cards, hero subtitle color fixes
- **Mobile UX Polish** — Configurable copy, CTA labels, idempotency, response enrichment (PRs #717–#719)
- **Ramp Security** — Status constants, null safety, modes, API-first Onramper integration (PRs #724–#725)

### Fixed
- **CI Pipeline Green** — Comprehensive test suite fixes (PR #735):
  - Added Sanctum abilities (`['read', 'write', 'delete']`) to 50+ test files
  - Relaxed N+1 query thresholds for CI middleware overhead
  - Fixed basket performance test timing edge case (`subMonth()` vs `subDays()`)
  - Fixed MobileV11CompatibilityTest assertions to match API response shape
- Daily log rotation for all log channels (PR #711)
- Swagger generation errors and GraphQL test failures (PR #710)
- Default ERC-4337 SimpleAccountFactory addresses in relayer config (PR #709)

## [5.7.0] - 2026-02-28

### Added
- **Rewards/Gamification Domain** — Complete rewards system with XP, levels, quests, points shop, and daily streaks
  - `RewardsService` with race-safe quest completion and item redemption using `DB::transaction()` + `lockForUpdate()`
  - `RewardProfile`, `RewardQuest`, `RewardShopItem`, `RewardQuestCompletion`, `RewardRedemption` models with UUID primary keys
  - `RewardsController` with 5 REST endpoints: profile, quests, quest completion, shop, item redemption
  - Level progression system (XP thresholds: level×100, MAX_LEVEL=999)
  - Streak tracking with separate read-only computation and write-side persistence
  - Specific error codes: `QUEST_NOT_FOUND`, `QUEST_ALREADY_COMPLETED`, `ITEM_NOT_FOUND`, `ITEM_OUT_OF_STOCK`, `INSUFFICIENT_POINTS`
  - 16 feature tests covering all reward flows, edge cases, and race conditions
- **Recent Recipients** — `GET /api/v1/wallet/recent-recipients` endpoint returning deduplicated send history
- **Notification Unread Count** — `GET /api/v1/notifications/unread-count` endpoint for badge display
- **Route Aliases** — Mobile-friendly v1 paths for `create-account`, `estimate-fee`, `data-export`
- 3 new edge case tests (inactive quest, inactive item, nonexistent UUID item)

### Changed
- **WebAuthn/FIDO2 Security Hardening** (PasskeyAuthenticationService):
  - Added rpIdHash validation (SHA-256 of rpId compared with authData per WebAuthn §7.1)
  - Added User Presence (UP) and User Verification (UV) flag checks
  - Added COSE algorithm validation (only ES256/alg=-7 accepted)
  - Added COSE curve validation (only P-256/crv=1 with 32-byte coordinate check)
  - Added origin validation in assertion flow
  - Fixed rpId consistency — uses `config('mobile.webauthn.rp_id')` everywhere
  - Fixed exception message leak — generic error to client, details logged server-side
  - Registration challenge moved to `/register-challenge` to resolve route conflict
  - Removed insecure GET challenge route
- **Rewards Race Condition Fixes**:
  - Duplicate-completion check moved inside `DB::transaction()` with `lockForUpdate()` on profile row
  - Balance and stock checks moved inside `DB::transaction()` with pessimistic locking
  - Streak initialization fixed for null `last_activity_date` (starts at 1)
  - Level-up loop capped at MAX_LEVEL=999
- POST mutation routes now use `api.rate_limit:mutation` tier (not query)
- Rate limiting added to `unread-count` and `data-export` endpoints
- `idempotency` middleware added to `create-account` alias
- `estimate-fee` alias changed from GET to POST
- `whereUuid('id')` constraints added to rewards routes
- `PaymentIntentStatus` enum used in `recentRecipients` query (replaces raw strings)
- Added `shop_item_id` index to `reward_redemptions` migration

### Fixed
- Passkey registration route conflict — two `POST /challenge` routes shadowed each other
- Streak mutation on read — profile view no longer modifies streak without persisting

---

## [5.6.0] - 2026-02-28

### Added
- **RAILGUN Privacy Protocol Integration** — Production-ready privacy layer replacing demo implementations
  - `RailgunBridgeClient` — HTTP client for Node.js RAILGUN bridge service
  - `RailgunMerkleTreeService` — Implements `MerkleTreeServiceInterface` via bridge
  - `RailgunZkProverService` — Implements `ZkProverInterface` via bridge
  - `RailgunPrivacyService` — Orchestrator for shield/unshield/transfer flows
  - `RailgunWallet` model — Stores encrypted RAILGUN wallet data per user
  - `ShieldedBalance` model — Cached shielded token balances
  - Chain support: Ethereum (1), Polygon (137), Arbitrum (42161), BSC (56) — Base NOT supported
  - 57 unit and feature tests with `Http::fake()` bridge mocking
- Node.js RAILGUN Bridge service specification (`infrastructure/railgun-bridge/`)
- `RAILGUN_BRIDGE_URL`, `RAILGUN_BRIDGE_SECRET`, `RAILGUN_BRIDGE_TIMEOUT` env vars
- `ZK_PROVIDER=railgun` and `MERKLE_PROVIDER=railgun` config options

### Changed
- `config/privacy.php` — Added `railgun` section with bridge configuration
- `PrivacyServiceProvider` — Added `'railgun'` case for ZK prover and Merkle tree bindings
- `PrivacyController` — Delegates to `RailgunPrivacyService` when RAILGUN mode is active
- `.env.production.example` and `.env.zelta.example` — Added RAILGUN environment variables
- Privacy network list updated — removed `base` (not supported by RAILGUN)

---

## [5.5.0] - 2026-02-21

### Added
- **ERC-4337 Pimlico v2 Production Integration** — bundler, paymaster, smart account factory
- Marqeta webhook Basic Auth + HMAC signature verification
- `.env.zelta.example` synced with all production environment variables

### Changed
- Platform hardening: IdempotencyMiddleware wiring, E2E banking tests, multi-tenancy isolation tests
- Dependabot triage: 4 safe upgrades merged, 5 breaking PRs closed with ignore rules
- CI reliability improvements

---

## [5.4.1] - 2026-02-21

### Changed
- Dependabot triage (PRs #642-#659)
- IdempotencyMiddleware applied to ~24 financial mutation routes
- E2E banking flow tests (6 scenarios)
- Multi-tenancy isolation tests (5 scenarios)
- Documentation "prototype" → "platform" refresh

---

## [5.4.0] - 2026-02-21

### Added
- **Ondato KYC** — Identity verification with TrustCert linkage
- **Chainalysis Sanctions Adapter** — Real-time screening integration
- **Marqeta Card Issuing Adapter** — Virtual/physical card management
- Firebase FCM v1 migration (from legacy API)

### Fixed
- X402 and mobile test hardening
- CVE patches for dependencies

---

## [5.2.0] - 2026-02-19

### Added
- **X402 Protocol** — HTTP 402 native micropayments with USDC on Base L2
- Payment gate middleware for per-endpoint API monetization
- Facilitator integration with EIP-3009/Permit2 payment schemes
- AI agent autonomous payments with spending limits
- GraphQL X402 domain schema
- MCP payment tool for AI workflows

---

## [5.1.6] - 2026-02-21

### Changed
- Copyright year update
- Accessibility improvements
- CSP headers hardening
- Email configuration defaults

---

## [5.1.5] - 2026-02-21

### Changed
- Upgraded `darkaonline/l5-swagger` from v9 to v10 — pulls in `zircote/swagger-php` v6, drops transitive `doctrine/annotations` dependency
- Added `doctrine/annotations ^2.0` as direct dependency — required for existing 10,385 `@OA\` docblock annotations until attribute migration (planned v5.2.0)
- Renamed plugin directories from kebab-case to PascalCase (`audit-exporter` → `AuditExporter`, `dashboard-widget` → `DashboardWidget`, `webhook-notifier` → `WebhookNotifier`) — fixes PSR-4 autoloading violations

### Added
- `.env.production.example` — production environment template with demo mode disabled, real service drivers (Pimlico bundler, Alchemy balance provider), Redis sessions/queues, HTTPS enforcement, HSM enabled

### Fixed
- PasskeyAuthenticationServiceTest expected `$result['token']` but service returns `access_token`/`refresh_token` after v5.1.4 token format change
- OpenAPI `@OA\Info` version updated from 5.0.0 to 5.1.5

---

## [5.1.4] - 2026-02-18

### Added
- Proper refresh token mechanism with token rotation — access tokens (short-lived, role-based abilities) paired with refresh tokens (`['refresh']` ability, 30-day lifetime) using Sanctum's `abilities` column; no DB migration needed
- `POST /api/auth/refresh` now accepts refresh token via request body (`refresh_token`) or `Authorization: Bearer` header — endpoint moved out of `auth:sanctum` middleware so it works after access tokens expire
- Token pair rotation on refresh — old access + refresh tokens are revoked before issuing new pair, preventing replay attacks
- `refresh_token`, `refresh_expires_in` fields in login, register, passkey auth, and refresh responses
- `sanctum.refresh_token_expiration` config (default: 30 days / 43200 minutes)
- `createTokenPair()` and `createRefreshToken()` methods in `HasApiScopes` trait
- 5 new security tests: refresh after access token expiry, reject access tokens for refresh, reject expired refresh tokens, token rotation revocation, missing token handling
- Session limit enforcement now excludes refresh tokens from count

### Fixed
- PHPStan type error in `config/sanctum.php` — `explode()` received `bool|string` from `env()`, now cast to `(string)`
- OpenAPI/Swagger annotations for login and register endpoints missing `refresh_token` and `refresh_expires_in` fields

---

## [5.1.3] - 2026-02-17

### Fixed
- `POST /api/v1/relayer/account` required `owner_address` — unusable during mobile onboarding when user has no wallet; now optional with server-side derivation from authenticated user
- Register endpoint response inconsistency — returned flat `{ message, user: {subset}, access_token }` instead of standard `{ success, data: { user, access_token, token_type, expires_in } }` envelope; now matches login format with full User model
- Passkey authenticate response missing `user` object and `expires_in` — mobile had incomplete session data after passkey auth
- `TransactionRateLimitMiddleware` crashed (500) on relayer endpoints — `incrementCounters()` lacked null-coalesce fallback for unknown transaction types like `relayer`

### Added
- `POST /api/auth/refresh` — token refresh endpoint (route existed but method was missing); revokes current token and issues a new one
- `POST /api/auth/logout-all` — revoke all tokens across all devices (route existed but method was missing)
- `expires_in` field in register and passkey auth responses for consistent token lifetime visibility

---

## [5.1.2] - 2026-02-16

### Fixed
- Production landing page CSS broken — `public/build/` is gitignored so Vite-compiled CSS missed `/app` page classes; replaced `@vite()` with pre-compiled standalone Tailwind CSS (`public/css/app-landing.css`) generated via Tailwind CLI
- CSP violation blocking Tailwind CDN — initial CDN fix was rejected by Content Security Policy `script-src`; resolved by self-hosting pre-compiled CSS (no external scripts needed)

---

## [5.1.1] - 2026-02-16

### Added
- Mobile app landing page at `/app` — futuristic dark-theme teaser page with email signup, feature cards (stablecoin payments, transaction shielding, Super KYC), Shamir's Secret Sharing explainer, platform architecture section, FAQ, and App Store/Google Play badges (Coming Soon)

### Fixed
- Flaky Azure HSM OAuth token caching test in CI — race condition with parallel Redis cache; replaced `Cache::get()` assertion with `Http::assertSent()` + `Http::assertSentCount()` to avoid shared mutable state

---

## [5.1.0] - 2026-02-16

### Added

#### Mobile API Completeness — 21 New Endpoints
- 11 privacy endpoints: shielded balances, total balance, transactions, shield/unshield/transfer, viewing key, proof of innocence (generate + verify), SRS URL/status
- 4 commerce endpoints: merchant detail, payment request detail/cancel, recent payments
- 3 card issuance endpoints: create card, card detail, card transactions
- 2 mobile endpoints: app status (public), bulk device removal
- 1 wallet endpoint: transaction quote with recipient validation
- 7 new feature test files with 42 tests covering all new endpoints

#### GraphQL 33-Domain Full Coverage
- 9 remaining domain schemas and resolvers added (completing 33-domain coverage)
- 14-domain GraphQL integration test suite

#### Blockchain Address Models
- `BlockchainAddress` and `BlockchainTransaction` Eloquent models with UUID support
- `blockchain_addresses` and `blockchain_address_transactions` migration
- `BlockchainWalletController` for address/transaction management

### Changed

#### Test Quality
- 97 ReflectionClass-based structural tests converted to behavioral assertions
- 9 pre-existing test failures resolved across event commands, GraphQL mutations, and projector health
- Azure Key Vault HSM test hardened with `Http::preventStrayRequests()` and explicit URL schemes

#### CI/CD Hardening
- k6 load test step made non-blocking in CI pipeline
- Per-scenario k6 thresholds for CI environment
- PHPStan Laravel bootstrap restored for CI
- PHPCS code quality violations resolved across codebase
- Composer autoload redundancy removed

### Fixed
- PHPStan generic types for `BlockchainAddress`/`BlockchainTransaction` BelongsTo relationships
- MariaDB timestamp compatibility in 4 migration files
- Swagger/OpenAPI documentation regenerated with all new endpoints
- axios CVE-2025-27152 resolved (upgrade to 1.13.5)
- Migration foreign key type mismatches and composer.lock sync
- Documentation accuracy: version references, domain counts, stale metrics

### Security
- axios upgraded to 1.13.5 to resolve CVE (npm overrides)

---

## [5.0.1] - 2026-02-13

### Added

#### GraphQL Schema Expansion — 19 New Domains (33 total)
- Privacy, RegTech, Governance, Asset, KeyManagement, Relayer, Banking, Commerce, Custodian, AI domain schemas
- 16 new query resolvers and 31 new mutation resolvers
- All 21 GraphQL mutations refactored to use CQRS WorkflowStub/Service patterns instead of direct Eloquent

#### Plugin Marketplace Admin UI
- Filament admin page with search/filter, enable/disable/scan actions, stats bar, security scan results

#### OpenAPI Annotations — 100% Controller Coverage
- `@OA` docblocks added to 52 remaining controllers (143+ total annotated endpoints)

### Changed

#### CI/CD — PHP 8.4 Upgrade
- All 10 GitHub Actions workflow files updated from PHP 8.3 to PHP 8.4
- `composer.json` minimum PHP requirement bumped to `^8.4`

#### Test Suite Quality
- 97 structural test files converted from `class_exists()`/`method_exists()` stubs to ReflectionClass-based behavioral assertions

#### Documentation Refresh
- `docs/01-VISION/ROADMAP.md` rewritten for v5.0.0 (41 domains, current capabilities)
- `docs/06-DEVELOPMENT/DOMAIN_MANAGEMENT.md` updated from 29 to 41 domains with categorized registry
- `docs/02-ARCHITECTURE/ARCHITECTURE.md` updated to v5.0 (33 GraphQL domains, streaming, plugins)
- `docs/ARCHITECTURAL_ROADMAP.md` GraphQL metrics updated to 33 domains
- `docs/VERSION_ROADMAP.md` v5.0.0 GraphQL expansion documented
- `docs/README.md` GraphQL count corrected to 33 domains
- `docs/IMPLEMENTATION_PLAN.md` marked as historical (v1.x era)
- `docs/BACKEND_UPGRADE_PLAN_v2.4.md` marked as COMPLETED
- `docs/MOBILE_APP_SPECIFICATION.md` version context added
- `docs/06-DEVELOPMENT/CLAUDE.md` updated to v5.0.0 with new domain entries
- GraphQL domain count corrected from 14 to 33 across 12 files (23 occurrences)

#### Website Updates
- Stablecoins & Treasury sub-products changed from "COMING SOON" to "Available"
- SDK version references updated from v3.0.0 to v5.0.0 across all packages
- Feature pages updated to reflect v5.0.0 implementation reality
- Prototype disclaimers added to partner and bank integration pages
- Blade views updated with accurate stats and status badges

### Fixed
- Serena memory files updated with correct GraphQL domain counts and lists

---

## [5.0.0] - 2026-02-13

### Added

#### Event Streaming Architecture (MAJOR)
- `EventStreamPublisher` — Publish domain events to Redis Streams with XADD, batch publishing, stream trimming (MAXLEN)
- `EventStreamConsumer` — Consumer group support with XREADGROUP, message acknowledgment (XACK), idle message claiming (XAUTOCLAIM), pending message tracking
- `config/event-streaming.php` — 15 domain stream mappings, retention policy, consumer group configuration, block timeout settings
- `EventStreamMonitorCommand` — `event-stream:monitor` Artisan command with `--domain` filter and `--json` output

#### Live Dashboard Foundation
- `LiveMetricsService` — Real-time metrics aggregation: domain health, event throughput, stream status, system health, projector lag with 10-second cache
- `LiveDashboardController` — 5 REST endpoints under `/api/v1/monitoring/live-dashboard` (metrics, domain-health, event-throughput, stream-status, projector-lag)

#### Multi-Channel Notification System
- `NotificationService` — Multi-channel notifications (email, push, in-app, webhook, SMS) with pluggable channel handlers, batch queue/flush, event trigger templates for 7 domain events
- Injectable `LoggerInterface` for unit-testable design

#### API Gateway Pattern
- `ApiGatewayMiddleware` — Unified gateway adding X-Request-Id tracing, X-API-Version, X-Gateway-Timing, X-Powered-By headers to all API responses

#### Tests
- EventStreamPublisher structure tests (class existence, method verification)
- EventStreamConsumer structure tests (7 method verifications)
- LiveMetricsService structure tests (6 method verifications)
- EventStreamMonitorCommand unit tests
- NotificationService functional tests (8 tests: instantiation, channel registration, send/queue/flush, event triggers, defaults)
- ApiGatewayMiddleware instantiation test

### Breaking Changes
- **MAJOR version**: This is a major version release introducing streaming architecture patterns
- Redis Streams dependency for event streaming features (requires Redis 5.0+)

---

## [4.3.0] - 2026-02-13

### Added

#### GraphQL Expansion — 4 New Domains
- Fraud domain: `FraudCase` type, `fraudCase`/`fraudCases` queries, `escalateFraudCase` mutation
- Mobile domain: `MobileDevice` type, `mobileDevice`/`mobileDevices` queries
- MobilePayment domain: `PaymentIntent` type, `paymentIntent`/`paymentIntents` queries, `createPaymentIntent` mutation
- TrustCert domain: `Certificate` type, `certificate`/`certificates` queries

#### Dashboard Widget Plugin
- `DomainHealthWidget` — Filament StatsOverviewWidget showing account, wallet, order, payment, event, and alert counts
- Cached database queries with 60-second TTL

#### Enhanced CLI Commands
- `graphql:schema-check` — Validate GraphQL schema consistency, report type/query/mutation coverage, detect unguarded operations
- `plugin:verify` — Verify plugin manifest integrity, entry point existence, and security scan for dangerous function calls
- `domain:status` — Show domain health overview with model/service/event/projector counts across 15 domains

#### GraphQL Security Hardening
- `GraphQLRateLimitMiddleware` — Separate rate limits for GraphQL (30/min guest, 120/min authenticated), configurable via `GRAPHQL_RATE_LIMIT_*` env vars
- `GraphQLQueryCostMiddleware` — Per-query cost analysis with depth penalty, configurable max cost via `GRAPHQL_MAX_QUERY_COST` env var
- Introspection control via `LIGHTHOUSE_SECURITY_DISABLE_INTROSPECTION` env var (set `true` for production)
- Rate limit and cost headers in responses (`X-RateLimit-*`, `X-GraphQL-Cost`)

#### Tests
- 4 GraphQL integration tests (Fraud, Mobile, MobilePayment, TrustCert)
- 3 CLI command unit tests (schema-check, plugin-verify, domain-status)
- GraphQL security middleware instantiation tests

---

## [4.2.0] - 2026-02-13

### Added

#### Real-time GraphQL Subscriptions
- Enabled WebSocket subscription broadcaster in Lighthouse configuration
- `orderMatched` subscription — real-time order match notifications with optional pair filter
- `portfolioRebalanced` subscription — portfolio rebalance events with optional portfolio_id filter
- `paymentStatusChanged` subscription — payment status updates with optional payment_id filter
- `bridgeTransferCompleted` subscription — cross-chain transfer completion with optional transfer_id filter

#### Plugin Event Hook System
- `PluginHookInterface` contract — `getHookName()`, `handle()`, `getPriority()` methods
- `PluginHookManager` — registration, dispatch with priority sorting, error isolation
- 17 hook points across 6 categories: Account, Payment, Compliance, Wallet, Exchange, System

#### Example Plugins
- **Webhook Notifier** — sends HTTP POST webhooks with HMAC signatures on configured events
- **Audit Exporter** — exports audit logs to JSON/CSV with `--format`, `--days`, `--output` options

#### Core Domain Mutation Expansion
- Account: `freezeAccount`, `unfreezeAccount` mutations
- Wallet: `createWallet`, `transferFunds` mutations
- Exchange: `placeOrder`, `cancelOrder` mutations
- Compliance: `submitKycDocument`, `triggerAmlCheck` mutations
- 8 new mutation resolver classes

#### Tests
- Plugin hook manager unit tests (register/dispatch, hook points, unregister, summary)
- Subscription resolver instantiation tests
- Core domain mutation feature tests (freeze/unfreeze, authentication rejection)

---

## [4.1.0] - 2026-02-13

### Added

#### GraphQL Expansion — 6 New Domains
- Treasury domain: `AssetAllocation` type, `portfolio`/`portfolios` queries, `createPortfolio`/`rebalancePortfolio` mutations
- Payment domain: `PaymentTransaction` type, `payment`/`payments` queries, `initiatePayment` mutation
- Lending domain: `LoanApplication` type, `loanApplication`/`loanApplications` queries, `applyForLoan`/`approveLoan` mutations
- Stablecoin domain: `StablecoinReserve` type, `stablecoinReserve`/`stablecoinReserves` queries, `mintStablecoin`/`redeemStablecoin` mutations
- CrossChain domain: `BridgeTransaction` type, `bridgeTransaction`/`bridgeTransactions` queries, `initiateBridgeTransfer` mutation
- DeFi domain: `DeFiPosition` type, `defiPosition`/`defiPositions` queries, `openPosition`/`closePosition` mutations

#### Event Replay Filtering Enhancement
- Added `--event-type` filter option to `event:replay` command for replaying specific event classes
- Added `--aggregate-id` filter option to `event:replay` command for replaying specific aggregates
- Selective replay support without full domain replay

#### Projector Health Monitoring
- `ProjectorHealthService` — track all registered projectors, detect stale/failed status
- `projector:health` Artisan command with `--domain` and `--stale-only` options
- REST endpoint at `/api/monitoring/projector-health` with cached status
- Stale projector detection endpoint at `/api/monitoring/projector-health/stale`

#### Integration Tests
- 6 GraphQL domain integration tests (Treasury, Payment, Lending, Stablecoin, CrossChain, DeFi)
- Event replay filter unit tests
- Projector health service unit tests

---

## [4.0.0] - 2026-02-13

### Added

#### Event Store v2 — Domain Table Activation (Phase 1, PR #517)
- `EventRouter` with `EventRouterInterface` — routes events to domain-specific tables by namespace
- `EventPartitioningService` — domain-based partitioning monitoring and verification
- Activated domain partitioning strategy in `config/event-store.php` with 21 domain table mappings
- Integrated EventRouter into `TenantAwareStoredEventRepository` and `EventStoreService`

#### Event Store v2 — Migration Tooling (Phase 2, PR #518)
- `EventMigrationService` — batch migrate events from `stored_events` to domain-specific tables
- `EventMigrationValidator` — count consistency, ordering, and aggregate validation
- `EventMigration` model for tracking migration runs with progress
- `event:migrate` command with `--domain`, `--batch`, `--dry-run`, `--verify` options
- `event:migrate-rollback` command for failed migration rollback
- `EventMigrationStatusWidget` for Filament admin dashboard

#### Event Store v2 — Versioning & Upcasting (Phase 3, PR #519)
- `EventUpcasterInterface` and `AbstractEventUpcaster` — event schema evolution contracts
- `EventUpcastingService` — chained upcasting (v1→v2→v3) with batch processing
- `EventVersionRegistry` — tracks event versions and upcaster chains
- `event:upcast` command with `--domain`, `--event`, `--batch`, `--persist` options
- `event:versions` command to list all event versions

#### GraphQL API — Foundation (Phase 4, PR #520)
- Installed `nuwave/lighthouse` for schema-first GraphQL at `/graphql` endpoint
- Account domain: types, queries (by ID, UUID, paginated), `createAccount` mutation
- `@guard(sanctum)` authentication on all operations
- Custom `@tenant` directive for multi-tenant scoping

#### GraphQL API — Core Domains (Phase 5, PR #521)
- Wallet domain: `MultiSigWallet` queries with chain/status filtering
- Exchange domain: `ExchangeOrder`, `Trade`, `OrderBook` types with pagination
- Compliance domain: `KycVerification`, `ComplianceAlert`, `ComplianceCase` types
- Subscription infrastructure: `OrderBookUpdated`, `TradeExecuted`, `WalletBalanceUpdated`
- `AccountDataLoader` and `WalletDataLoader` for N+1 query prevention
- `GraphQLExceptionHandler` for structured error responses

#### Plugin Marketplace — Foundation (Phase 6, PR #522)
- `PluginManager` — full lifecycle management (install/remove/enable/disable/update/discover)
- `PluginDependencyResolver` — semver constraints (`^`, `~`, `>=`, exact) and circular detection
- `PluginLoader` — filesystem discovery and service provider booting
- `PluginManifest` — parse/validate plugin.json manifests
- `Plugin` model with UUID, soft deletes, permission tracking
- Commands: `plugin:install`, `plugin:remove`, `plugin:list`, `plugin:enable`, `plugin:disable`, `plugin:create`
- Plugin scaffold generator with directory structure and ServiceProvider stub
- Added `Plugins\` PSR-4 autoload namespace

#### Plugin Marketplace — Sandboxing & API (Phase 7, PR #523)
- `PluginPermissions` — 12 permission categories with descriptions and validation
- `PluginSandbox` — runtime permission enforcement with strict mode
- `PluginSecurityScanner` — static analysis detecting 15 dangerous code patterns
- `PluginMarketplaceController` — REST API for plugin CRUD, scanning, and discovery
- `PluginResource` — Filament admin panel for plugin management
- `PluginReview` model for security review tracking

### Changed
- Event Store partitioning strategy changed from `none` to `domain` in config
- Disabled Lighthouse schema cache in test environment

---

## [3.5.0] - 2026-02-12

### Added

#### SOC 2 Type II Preparation (Phase 1, PRs #511-#512)
- `EvidenceCollectionService` — automated SOC 2 evidence collection with config snapshots and integrity hashing
- `AccessReviewService` — periodic access review automation with demo mode
- `IncidentResponseService` — security incident lifecycle management (create, update, resolve, postmortem)
- `SecurityIncident` model with status tracking and resolution workflow
- `Soc2DashboardController` with SOC 2 readiness overview API endpoints
- `soc2:evidence-collect` and `soc2:access-review` artisan commands
- `compliance-certification.php` config for SOC 2, PCI DSS, multi-region, and GDPR settings

#### PCI DSS Readiness (Phase 2, PR #513)
- `DataClassificationService` — data classification with sensitivity levels (public/internal/confidential/restricted)
- `EncryptionVerificationService` — verification suite for at-rest, in-transit, key strength, and algorithm compliance
- `KeyRotationService` — key rotation tracking, scheduling, and automated rotation with dry-run mode
- `DataClassification` and `KeyRotationSchedule` models with tenant awareness
- `pci:classify-data`, `pci:verify-encryption`, `pci:rotate-keys` artisan commands
- 3 migrations for data classifications and key rotation schedules

#### Multi-Region Deployment (Phase 3, PR #514)
- `DataResidencyService` — data residency enforcement with region-to-disk mapping and compliance verification
- `RegionAwareStorageService` — region-aware storage with disk selection and access verification
- `GeoRoutingController` — geo-routing API endpoints for nearest region, latency probing, and failover
- `DataTransferLog` model for cross-region transfer audit trail
- `multi-region.php` config for region definitions, storage mapping, and geo-routing settings
- 2 migrations for data transfer logs and geo-routing config

#### GDPR Enhanced Compliance (Phase 4, PR #515)
- `BreachNotificationService` — GDPR Articles 33/34 breach reporting with 72-hour deadline tracking
- `ConsentManagementService` — granular consent management with immutable audit trail and coverage statistics
- `DataProcessingRegisterService` — Article 30 Records of Processing Activities (ROPA) with completeness checking
- `DataRetentionService` — automated retention policy enforcement (delete/archive/anonymize) with dry-run mode
- `DpiaService` — Data Protection Impact Assessments (Article 35) with risk scoring and approval workflow
- 5 models: `ConsentRecord`, `DataBreach`, `DataProtectionAssessment`, `ProcessingActivity`, `RetentionPolicy`
- 6 event-sourcing events: `BreachDetected`, `BreachAuthorityNotified`, `BreachSubjectsNotified`, `ConsentRecorded`, `ConsentRevoked`, `RetentionPolicyEnforced`
- `GdprEnhancedController` with 14 API endpoints under `/compliance/gdpr/v2/`
- `gdpr:breach-check`, `gdpr:retention-enforce`, `gdpr:register-export` artisan commands
- 5 migrations for processing activities, assessments, breaches, consents, and retention policies

### Fixed
- Duplicate `uses(Tests\TestCase::class)` declarations in Certification test directory (Pest.php global binding conflict)
- `SecurityAuditServiceTest` check count updated from 8 to 10 to match current security check inventory

## [3.4.0] - 2026-02-12

### Added

#### API Version Middleware (Phase 1, PR #503)
- `ApiVersionMiddleware` — lightweight after-middleware that detects API version from URL path and sets response headers
- `X-API-Version` response header on all API responses
- RFC 8594 `Deprecation` and `Sunset` headers for deprecated API versions
- `config/api-versioning.php` — version registry with `supported`, `deprecated`, `deprecated_at`, and `sunset` fields
- Registered as global middleware for the `api` middleware group

#### Partner Tier-Aware Rate Limiting (Phase 2, PR #504)
- BaaS partner tier detection in `ApiRateLimitMiddleware` — partners get tier-based per-minute limits (Starter: 60, Growth: 300, Enterprise: 1000 req/min)
- Type multipliers for different endpoint categories (query: 1.0, transaction: 0.5, auth: 0.1, webhook: 2.0)
- Monthly API call limit enforcement via `PartnerUsageMeteringService` with `MONTHLY_LIMIT_EXCEEDED` error response
- `X-Monthly-Limit`, `X-Monthly-Used`, `X-Monthly-Reset-At` response headers for partner requests
- `partner_tiers` configuration section in `config/rate_limiting.php`

#### SDK Generation Command (Phase 3, PR #505)
- `php artisan sdk:generate {language}` — generates typed SDK packages from OpenAPI spec
- `SdkGeneratorService::generateFromSpec()` — parses OpenAPI spec and generates client libraries with endpoint stubs and typed models
- Supports TypeScript, Python, Java, Go, C#, and PHP output
- Generated SDKs include typed client class, model definitions, and README with endpoint listing

#### OpenAPI Annotations — High Priority (Phase 4, PRs #506-#508)
- **EnhancedRegulatoryController**: 14 methods annotated (Compliance tag)
- **ComplianceController**: 12 methods annotated (Compliance tag)
- **AuditController**: 10 methods annotated (Audit tag)
- **FraudDetectionController**: 10 methods annotated (Fraud Detection tag)
- **RiskAnalysisController**: 8 methods annotated (Risk Management tag)
- **ModuleController**: 6 methods annotated (Module Management tag)
- **PasskeyController**: 3 methods annotated (WebAuthn tag)
- **AccountDeletionController**: 1 method annotated (Account Deletion tag)
- 7 new OpenAPI tags: Compliance, Audit, Fraud Detection, Risk Management, Module Management, WebAuthn, Account Deletion

#### OpenAPI Annotations — Medium Priority (Phase 5, PR #509)
- **V2/BankIntegrationController**: 10 methods annotated (Banking V2 tag)
- **BlockchainWalletController**: 9 methods annotated (Blockchain Wallets tag)
- **V2/ComplianceController**: 8 methods annotated (Compliance V2 tag)
- **MobileRelayerController**: 8 methods annotated (Relayer tag)
- **MobileWalletController**: 7 methods annotated (Mobile Wallet tag)
- **CertificateApplicationController**: 6 methods annotated (TrustCert tag)
- **MobileTrustCertController**: 5 methods annotated (TrustCert tag)
- **MobileCommerceController**: 5 methods annotated (Commerce tag)
- **V2/FinancialInstitutionController**: 5 methods annotated (BaaS Onboarding tag)
- **LoanController**: 5 methods annotated (Lending tag)
- **LiquidityForecastController**: 4 methods annotated (Treasury tag)
- **LoanApplicationController**: 4 methods annotated (Lending tag)
- **WalletTransferController**: 3 methods annotated (Mobile Wallet tag)
- 11 new OpenAPI tags: Banking V2, Blockchain Wallets, Compliance V2, BaaS Onboarding, TrustCert, Commerce, Relayer, Mobile Wallet, Treasury, Lending

### Changed
- `OpenApiDoc.php` version updated to 3.4.0 with 18 new tags (31 total)
- OpenAPI spec regenerated with ~143 annotated endpoint methods across 21+ controllers

## [3.3.4] - 2026-02-12

### Added
- **Per-network relayer status**: `GET /v1/relayer/networks/{network}/status` — returns chain ID, gas price, block number, and relayer queue status for a single network (P1 mobile v2 gap)
- **Privacy pool statistics**: `GET /v1/privacy/pool-stats` — public endpoint returning aggregate privacy pool size, participant count, and anonymity strength rating (P2 mobile v2 gap)
- **User preferences API**: `GET /v1/user/preferences` + `PATCH /v1/user/preferences` — mobile app settings (active network, privacy mode, auto-lock, transaction auth, balance visibility, POI, biometric lock) with sensible defaults and merge-on-read (P2 mobile v2 gap)
- `mobile_preferences` JSON column on `users` table for persisting per-user mobile app settings

## [3.3.3] - 2026-02-12

### Fixed
- **PerformSystemHealthChecks**: Fixed `ini_set('memory_limit', '256M')` that crashed parallel test processes already using >256MB — now only increases the limit, never decreases it
- **MobilePayment unit tests**: Added missing `uses(TestCase::class)` to `PaymentIntentServiceTest`, `ReceiptServiceTest`, `ReceiveAddressServiceTest`, and `NetworkAvailabilityServiceTest` — fixes `BindingResolutionException` for Spatie EventSubscriber in parallel execution

## [3.3.2] - 2026-02-12

### Fixed
- **Compliance routes**: Fixed `POST /api/compliance/cases` and `POST /api/compliance/alerts` mapping to non-existent `create` method — domain route file now correctly references `store` method
- **TransactionMonitoring routes**: Fixed `GET /api/transaction-monitoring/patterns` and `GET /api/transaction-monitoring/thresholds` returning 404 — moved `/{id}` wildcard route after static routes to prevent route shadowing
- **EventReplayCommand**: Added projector class namespace validation (must be `App\` namespace) and Projector subclass check to prevent arbitrary class instantiation
- **EventReplayCommand**: Fixed `--domain` filter not being applied during replay — `resolveProjectors()` now filters projectors by domain namespace

### Changed
- `event:replay --projector` now validates class exists, is in `App\` namespace, and extends `Spatie\EventSourcing\EventHandlers\Projectors\Projector`
- `event:replay --domain` now filters projectors to only replay those in the matching `App\Domain\{domain}\` namespace

## [3.3.1] - 2026-02-12

### Fixed
- **EventStoreHealthCheck**: Fixed `checkProjectorLag()` that had hardcoded `$recentUnprocessed = 0`, making the health check a no-op — now properly queries `projector_statuses` table
- **EventStoreHealthCheck**: Made `checkEventGrowthRate()` threshold configurable via `config/event-store.php` instead of hardcoded `10000`
- **EventStatsCommand**: Fixed PHPStan error where `$format` option could be `null` but was passed as `string`
- **EventRebuildCommand**: Removed dead code (`$shortName`/`ReflectionClass`) in `rebuildAll()` and fixed PHPStan `class-string` error
- **EventArchivalService**: Added batch processing to `restoreFromArchive()` to prevent memory exhaustion on large archives
- **EventStoreService**: Optimized `cleanupSnapshots()` from N+1 per-UUID queries to a single bulk query with subquery
- **StructuredLoggingMiddleware**: Added `sanitizeTraceHeader()` to validate `X-Request-ID`/`X-Trace-ID` headers — rejects values longer than 128 chars or containing non-alphanumeric characters to prevent log injection

### Changed
- Added `health.growth_rate_threshold` to `config/event-store.php`
- Added `EVENT_STORE_GROWTH_RATE_THRESHOLD` to `.env.example`

## [3.3.0] - 2026-02-12

### Added

#### Event Store Commands (Phase 1, PR #493)
- `EventStoreService` — centralized service for event store operations with domain-to-table mapping for 21 domains
- `event:stats` command — display event store statistics per domain with table/json output
- `event:replay` command — safely replay events through projectors with `--domain`, `--from`, `--to`, `--dry-run` options
- `event:rebuild` command — rebuild aggregate state from events with `--uuid` and `--force` options
- `snapshot:cleanup` command — clean up old snapshots keeping latest per aggregate UUID

#### Real-time Observability Dashboards (Phase 2, PR #494)
- `EventStoreDashboard` Filament admin page at `/admin/event-store-dashboard`
- 4 dashboard widgets: EventStoreStats (30s poll), EventStoreThroughput (10s, line chart), AggregateHealth (60s), SystemMetrics (10s)
- `MonitoringMetricsUpdated` broadcast event on `monitoring` WebSocket channel
- Added `monitoring` channel to WebSocket configuration

#### Structured Logging (Phase 3, PR #495)
- `StructuredJsonFormatter` — Monolog formatter with timestamp, trace_id, span_id, domain, request_id, hostname
- `StructuredLoggingMiddleware` — HTTP middleware generating request_id, logging start/end with error-level for 5xx
- `LogsWithDomainContext` trait — auto-adds domain name and service class to log context
- `structured` logging channel in `config/logging.php`

#### Deep Health Checks (Phase 4, PR #496)
- `EventStoreHealthCheck` service — event table connectivity, projector lag, snapshot freshness, event growth rate checks
- `checkDeep()` and `checkDomain(string $domain)` methods on `HealthChecker`
- `--deep` flag on `system:health-check` command for event store health checks
- `DomainHealthWidget` — Filament widget showing domain health, snapshot age, events/hour

#### Event Store Partitioning (Phase 5, PR #497)
- `EventArchivalService` — archive, compact, restore, and stats methods for event lifecycle management
- `event:archive` command — archive old events with `--before`, `--domain`, `--batch-size`, `--dry-run` options
- `event:compact` command — compact events for aggregates with snapshots using `--keep-latest`, `--dry-run`
- `archived_events` migration table for long-term event storage
- `config/event-store.php` — archival, compaction, and partitioning configuration

### Changed
- `HealthChecker` now accepts optional `EventStoreHealthCheck` for deep checks
- `PerformSystemHealthChecks` command supports `--deep` flag
- `config/monitoring.php` extended with structured logging settings
- `config/logging.php` includes `structured` channel
- `bootstrap/app.php` registers `structured.logging` middleware alias
- `config/websocket.php` includes `monitoring` channel

---

## [3.2.1] - 2026-02-12

### Fixed
- Fixed GitLeaks false positives for developer documentation Blade views containing placeholder API keys in code examples

### Changed
- Updated 14 dependencies to latest minor/patch versions:
  - **Composer**: aws/aws-sdk-php 3.369.32, larastan/larastan 3.9.2, laravel/dusk 8.3.6, laravel/telescope 5.17.0, laravel/tinker 2.11.1, meilisearch/meilisearch-php 1.16.1, dmore/behat-chrome-extension 1.4.1
  - **npm**: postcss 8.5.6, @tailwindcss/typography 0.5.19
  - **GitHub Actions**: actions/cache v5, actions/download-artifact v7, github/codeql-action v4, azure/k8s-set-context v4, azure/setup-helm v4

---

## [3.2.0] - 2026-02-11

### Added

#### Module Manifests (Phase 1)
- Complete `module.json` manifests for all **41 domain modules** with schema, dependencies, interfaces, events, and commands
- `module:enable` and `module:disable` artisan commands with `config/modules.php` configuration

#### Modular Route Loading (Phase 2)
- **ModuleRouteLoader** extracts monolithic `routes/api.php` (1,646 lines) into **24 per-domain route files**, loaded automatically via `DomainServiceProvider`
- `routes/api.php` reduced from 1,646 to ~240 lines (thin orchestrator pattern)

#### Module Management API (Phase 3)
- REST endpoints at `/api/v2/modules` for listing, inspecting, enabling/disabling, and verifying modules
- Admin-only write operations with proper authorization

#### Filament Module Admin (Phase 4)
- Custom admin page at `/admin/modules` with search, status/type filters, enable/disable/verify actions
- **Module Health Widget** — stats overview widget showing total modules, manifest coverage, disabled count, and type breakdown

#### Performance & Load Testing (Phase 5)
- **k6 Load Test Suite** — smoke (1 VU), load (50 VUs), and stress (100 VUs) scenarios at `tests/k6/`
- **Query Performance Middleware** — detects slow queries and N+1 patterns with configurable thresholds via `config/performance.php`
- `performance:report` artisan command generates JSON/markdown baseline reports

#### DevOps & Governance (Phase 6)
- **Dependabot Configuration** — weekly updates for Composer, npm, and GitHub Actions
- **GitHub Issue Templates** — structured YAML forms for bug reports and feature requests
- **Pull Request Template** — checklist with type-of-change, test plan, and contributing guidelines
- **SPDX License Headers** — Apache-2.0 identifiers on key source files
- **Plugin Architecture Documentation** — README section and CONTRIBUTING module development guide
- **Integration Tests** — plugin system integration tests covering manifests, dependencies, enable/disable flow

### Changed
- `routes/api.php` reduced from 1,646 to ~240 lines (thin orchestrator pattern)
- `config/event-sourcing.php` narrowed auto-discovery to specific directories (fixes phantom route loading)
- `bootstrap/app.php` registered `query.performance` middleware alias
- README version badge updated to 3.2.0, domain count updated to 41

### Fixed
- Fixed Spatie Event Sourcing auto-discovery scanning entire `app/` directory, which caused route files to be loaded without API prefix

---

## [v3.1.0] - 2026-02-11

### Theme: Consolidation, Documentation & UI Completeness

After 18 releases of feature development (v1.1.0 → v3.0.0), v3.1.0 closes the documentation and UI gaps to match the platform's 41 domains, 266+ services, and 1,150+ routes.

### Added

#### Swagger/OpenAPI Documentation (Phase 2)
- Added @OA annotations to **CrossChainController** (7 routes), **DeFiController** (8 routes), **RegTechController** (12 routes)
- Added @OA annotations to **MobilePayment** controllers (6 files, ~25 routes), **Partner** controllers (5 files, 24 routes), **AiQueryController** (2 routes)
- Fixed L5-Swagger config to scan all v2.0+ controller subdirectories

#### Website Feature Pages (Phase 3)
- 7 new feature pages: `crosschain-defi`, `privacy-identity`, `mobile-payments`, `regtech-compliance`, `baas-platform`, `ai-framework`, `multi-tenancy`
- Updated landing page with v2.0+ feature sections and platform statistics
- Updated feature index with cards for all new feature areas

#### Developer Portal (Phase 4)
- Updated all 6 developer portal pages (index, api-docs, examples, sdks, webhooks, postman) with v2.0+ API documentation
- Added code examples for cross-chain bridge, DeFi swap, RegTech compliance, BaaS partner onboarding, AI queries
- Added BaaS SDK generation documentation (TypeScript, Python, Java, Go, PHP)

#### Admin UI — Filament Resources (Phases 5 & 6)
- **Phase 5 (7 high-priority resources)**: BridgeTransactionResource, DeFiPositionResource, AnomalyDetectionResource, FilingScheduleResource, MultiSigWalletResource, LoanResource, PortfolioSnapshotResource
- **Phase 6 (8 secondary resources)**: DelegatedProofJobResource, MerchantResource, CertificateResource, KeyShardRecordResource, SmartAccountResource, PaymentIntentResource, MobileDeviceResource, PartnerResource
- Admin UI coverage: **26 of 41 domains** (up from 11 pre-v3.1.0)

#### New Eloquent Models & Migrations
- `BridgeTransaction` model + migration (CrossChain domain)
- `DeFiPosition` model + migration (DeFi domain)
- `Certificate` model + migration (TrustCert domain)

#### User-Facing Views (Phase 7)
- **Cross-Chain Portfolio** (`/crosschain`) — bridge transactions, multi-chain portfolio, supported networks & providers
- **DeFi Portfolio** (`/defi`) — positions, protocol overview, yield tracking
- **Privacy & Identity** (`/privacy`) — ZK proof history, verification status, privacy features
- **Trust Certificates** (`/trustcert`) — certificate management, W3C Verifiable Credentials
- Dashboard "Web3 & Advanced Features" quick-action cards
- Navigation menu "Web3" dropdown (desktop + responsive mobile)

### Changed
- Updated `docs/VERSION_ROADMAP.md` with v3.1.0 completion status and v3.2.0 planning
- Updated `docs/ARCHITECTURAL_ROADMAP.md` with current metrics and domain inventory
- Updated Serena development memories with v3.1.0 state

---

## [v3.0.0] - 2026-02-10

### Added

#### CrossChain Domain
- **CrossChain bounded context** with bridge protocol abstractions and chain registry
- **BridgeOrchestratorService** - Multi-provider bridge orchestration (quote aggregation, route optimization)
- **Wormhole, LayerZero, Axelar bridge adapters** - Protocol-specific implementations with demo mode
- **BridgeFeeComparisonService** - Cross-provider fee/time comparison with weighted ranking
- **CrossChainAssetRegistryService** - Token address mapping across 9 chains
- **BridgeTransactionTracker** - Cache-based bridge transaction lifecycle tracking
- **CrossChainSwapService** - Atomic cross-chain swaps (bridge + swap in optimal order)
- **CrossChainSwapSaga** - Compensation-based saga for bridge+swap failure recovery
- **CrossChainYieldService** - Best yield discovery across chains with bridge cost analysis
- **MultiChainPortfolioService** - Aggregated portfolio across all chains with DeFi positions

#### DeFi Domain
- **DeFi bounded context** with protocol adapter interfaces and position tracking
- **UniswapV3Connector** - Multi-fee-tier swaps, L2 gas optimization, price impact estimation
- **AaveV3Connector** - Supply/borrow/repay/withdraw with market data and health factor
- **CurveConnector** - Stablecoin-optimized swaps with lower fees (0.04%)
- **LidoConnector** - ETH staking with stETH derivatives and withdrawal queue
- **SwapAggregatorService** - Multi-DEX quote aggregation with best-price routing
- **SwapRouterService** - Optimal route selection across DEXs with price impact validation
- **FlashLoanService** - Aave V3 flash loan orchestration with 0.05% fee
- **DeFiPortfolioService** - Aggregated portfolio with protocol/chain/type breakdowns
- **DeFiPositionTrackerService** - DeFi position tracking with health factor monitoring

#### API Endpoints
- 6 CrossChain API endpoints (`/api/v1/crosschain/`) - chains, bridge quotes, bridge initiate, bridge status, cross-chain swap quote/execute
- 8 DeFi API endpoints (`/api/v1/defi/`) - protocols, swap quote/execute, lending markets, portfolio, positions, staking, yield

---

## [v2.10.0] - 2026-02-10

### Added
- Mobile Commerce API: merchant listings, QR code parsing/generation, payment requests, payment processing
- Mobile Relayer API: relayer status, gas estimation, UserOp building/submission/tracking, paymaster data
- Mobile Wallet API: token list, balances, addresses, wallet state, transaction history, send flow
- Mobile TrustCert API: trust level status, requirements, limits, certificate application CRUD
- Auth compatibility: response envelope wrapping, /auth/me alias, account deletion, passkey registration
- CORS: X-Client-Platform and X-Client-Version headers allowed
- Mobile API compatibility handover document (docs/MOBILE_API_COMPATIBILITY.md)

### Changed
- Auth login/user responses now wrapped in `{ success, data }` envelope for mobile consistency

---

## [2.9.1] - 2026-02-10

### Production Hardening (Phase 3)

Completes the deferred Phase 3 of v2.9.0 with production-grade implementations for smart contracts, ZK circuits, HSM providers, and automated security auditing.

### Added

#### On-Chain SBT Deployment (#441)
- **OnChainSbtService** - ERC-5192 Soulbound Token minting/revoking on Polygon via JSON-RPC
- **DemoOnChainSbtService** - In-memory demo implementation for development
- Opt-in on-chain anchoring via `commerce.soulbound_tokens.on_chain_anchoring` config
- `SoulboundTokenMintedOnChain` and `SoulboundTokenRevokedOnChain` events

#### snarkjs Integration (#442)
- **SnarkjsProverService** - Wraps snarkjs CLI via Symfony Process for groth16 prove/verify
- **PoseidonHasher** - circomlibjs Poseidon hash via Node.js with SHA3-256 fallback
- **ProductionMerkleTreeService** - On-chain Merkle tree sync via JSON-RPC
- Configurable circuit mapping, hash algorithm, and proof provider selection

#### AWS KMS & Azure Key Vault (#443)
- **AwsKmsHsmProvider** - Full HsmProviderInterface via aws-sdk-php with DER-to-compact ECDSA conversion
- **AzureKeyVaultHsmProvider** - Full HsmProviderInterface via Azure Key Vault REST API v7.4 with OAuth2 auth
- **HsmProviderFactory** - Config-driven factory with credential validation
- LocalStack support for AWS KMS development testing

#### Security Audit Tooling (#444)
- **SecurityAuditService** - Orchestrator for OWASP Top 10 security checks
- `php artisan security:audit` command with `--format=json|text|table`, `--check`, `--min-score`, `--ci`
- 8 automated checks: Dependency Vulnerability, Security Headers, SQL Injection, Authentication, Encryption, Rate Limiting, Input Validation, Sensitive Data Exposure
- CI-compatible exit codes for pipeline integration

---

## [2.9.0] - 2026-02-10

### 🧠 ML Anomaly Detection & Banking-as-a-Service

Machine learning-powered anomaly detection for fraud prevention, plus a complete Banking-as-a-Service (BaaS) platform enabling partner institutions to integrate FinAegis capabilities via APIs, SDKs, and embeddable widgets.

### Highlights

| Feature | Description | PRs |
|---------|-------------|-----|
| ML Anomaly Detection | Statistical, behavioral, velocity, and geolocation anomaly detection | #416-#428 |
| BaaS Metering & Auth | Partner authentication middleware, API usage tracking | #429 |
| Partner Billing | Invoice generation with tiered pricing, overage, discounts | #430 |
| SDK Generation | Auto-generate TypeScript, Python, Java, Go, PHP client SDKs | #431 |
| Embeddable Widgets | Payment, Checkout, Balance, Transfer, Account widgets with branding | #432 |
| Integration Marketplace | Third-party integration connectors with health monitoring | #433 |
| Partner API | 26 REST endpoints for partner self-service under `/api/partner/v1` | #434 |
| BaaS Integration Tests | End-to-end workflow testing | #435 |
| Test Suite Cleanup | Fixed 85+ failing tests, flaky test stabilization | #436-#439 |

### Added

#### ML Anomaly Detection (Phase 1)
- **StatisticalAnomalyActivity** - Z-score and IQR-based anomaly detection with configurable thresholds
- **BehavioralProfileActivity** - User behavioral baseline comparison with adaptive profiles
- **VelocityAnomalyActivity** - Transaction frequency and volume spike detection
- **GeolocationAnomalyActivity** - Location-based anomaly detection with IP reputation and DBSCAN clustering
- **AnomalyDetectionOrchestrator** - Coordinates all detection methods with weighted scoring
- **ProcessAnomalyBatchJob** - Scheduled batch scanning of historical transactions
- **GeoMathService** - Haversine distance and DBSCAN clustering for geospatial analysis
- Database tables: `user_behavioral_profiles`, `anomaly_detections` with proper indexes

#### Banking-as-a-Service (Phase 2)
- **PartnerUsageMeteringService** - Daily API call tracking, widget load metering, SDK download tracking
- **PartnerAuthMiddleware** - Client ID/Secret authentication with IP allowlist and rate limiting
- **PartnerBillingService** - Automated invoice generation with base fees, overage calculation, billing cycle discounts (quarterly 5%, annual 15%)
- **SdkGeneratorService** - Template-based SDK generation for 5 languages with OpenAPI spec support
- **EmbeddableWidgetService** - HTML/JS embed code generation with partner branding (CSS variables, widget config)
- **PartnerMarketplaceService** - Integration connector management with health monitoring
- **PartnerIntegration** model - Tracks partner third-party integrations with encrypted config
- **5 Partner Controllers** - Dashboard, SDK, Widget, Billing, Marketplace
- **PartnerTier** enum - Business logic for Starter, Growth, Enterprise tiers

### API Endpoints

| Category | Endpoints |
|----------|-----------|
| Partner Profile | `GET /api/partner/v1/profile`, `GET /api/partner/v1/usage`, `GET /api/partner/v1/tier` |
| Branding | `GET /api/partner/v1/branding`, `PUT /api/partner/v1/branding` |
| SDK | `GET /api/partner/v1/sdk/languages`, `POST /api/partner/v1/sdk/generate`, `GET /api/partner/v1/sdk/{language}` |
| Widgets | `GET /api/partner/v1/widgets`, `POST /api/partner/v1/widgets/{type}/embed`, `GET /api/partner/v1/widgets/{type}/preview` |
| Billing | `GET /api/partner/v1/billing/invoices`, `GET /api/partner/v1/billing/outstanding`, `GET /api/partner/v1/billing/breakdown` |
| Marketplace | `GET /api/partner/v1/marketplace`, `POST /api/partner/v1/marketplace/integrations`, `DELETE /api/partner/v1/marketplace/integrations/{id}` |

### Security
- DBSCAN DoS prevention with configurable limits
- PII protection via IP address hashing in anomaly records
- Input sanitization for all anomaly detection parameters
- Partner auth with encrypted client secrets and webhook secrets
- IP allowlist enforcement in partner middleware

### Fixed
- Fixed 85+ failing tests across the entire test suite (#436-#439)
- Fixed AssetAllocation VO serialization in Event Sourcing (json_encode on private properties)
- Fixed RebalancingService priority threshold off-by-one error
- Fixed MySQL count()/sum() string return type casting in FraudDetectionService
- Fixed flaky BasketValueCalculationServiceTest with time freezing
- Fixed PartnerIntegration migration (UUID FK type, encrypted column type)
- Fixed rate limiting test failures (#437)
- Fixed stale test assertions in 6 test files (#438)
- Added infrastructure-dependent test skip logic (#436)

### Testing
- 136+ new tests (115 fraud/anomaly + 21 edge cases + BaaS unit/feature/integration)
- PHPStan Level 8 clean (baselines for Fraud and BaaS domains)
- All CI checks green: Unit, Feature, Integration, Behat, Security, Performance

---

## [2.8.0] - 2026-02-08

### 🤖 AI Query & Regulatory Technology

AI-powered natural language transaction queries and comprehensive multi-jurisdiction regulatory technology infrastructure. This release completes the AI Framework query layer and delivers RegTech adapters for FinCEN, ESMA, FCA, and MAS with MiFID II, MiCA, and Travel Rule compliance services.

### Highlights

| Feature | Description | PRs |
|---------|-------------|-----|
| AI Transaction Query Tools | Natural language transaction search, balance queries, pattern analysis | #397 |
| AI Query API Endpoints | REST API + MCP tools for AI-powered queries | #398 |
| RegTech Jurisdiction Adapters | FinCEN, ESMA, FCA, MAS regulatory filing adapters | #399 |
| MiFID/MiCA/Travel Rule Services | Full regulatory compliance services with 11 API endpoints | #400 |

### Added

#### AI Framework Enhancements
- **TransactionQueryTool** - Natural language transaction queries with date/amount/type filters
- **BalanceQueryTool** - Multi-currency balance aggregation and reporting
- **PatternAnalysisTool** - Spending pattern detection and anomaly flagging
- **QueryExplanationService** - Transparent AI query interpretation
- **AIQueryController** - REST endpoints for transaction queries, balance queries, and pattern analysis
- **MCP Tool Registration** - AI tools available via Model Context Protocol

#### RegTech Domain (NEW Services)
- **FinCENAdapter** - US BSA E-Filing (CTR, SAR, CMIR, FBAR) with threshold validation
- **ESMAAdapter** - EU FIRDS/TREM (MiFID Transaction, EMIR, SFTR) with ISIN/LEI/MIC validation
- **FCAAdapter** - UK Gabriel (MiFID Transaction, REP-CRIM, SUP16) with FCA FRN requirement
- **MASAdapter** - SG eServices Gateway (MAS Returns, STR) with grounds-for-suspicion validation
- **AbstractRegulatoryAdapter** - Shared demo/sandbox behavior for all adapters
- **MifidReportingService** - MiFID II transaction reporting (RTS 25), best execution analysis (RTS 27/28), instrument reference data (FIRDS/ANNA DSB)
- **MicaComplianceService** - CASP authorization, crypto-asset whitepaper validation, reserve management, travel rule checking
- **TravelRuleService** - FATF Recommendation 16 compliance with jurisdiction-specific thresholds (US $3,000 / EU EUR 1,000 / UK GBP 1,000 / SG SGD 1,500)
- **RegTechServiceProvider** - Auto-registers all 4 jurisdiction adapters with orchestration service

### API Endpoints

| Category | Endpoints |
|----------|-----------|
| AI Queries | `POST /api/ai/query/transactions`, `POST /api/ai/query/balances`, `POST /api/ai/query/patterns` |
| Compliance | `GET /api/regtech/compliance/summary`, `GET /api/regtech/adapters` |
| Regulations | `GET /api/regtech/regulations/applicable` |
| Reports | `POST /api/regtech/reports`, `GET /api/regtech/reports/{ref}/status` |
| MiFID II | `GET /api/regtech/mifid/status` |
| MiCA | `GET /api/regtech/mica/status`, `POST /api/regtech/mica/whitepaper/validate`, `GET /api/regtech/mica/reserves` |
| Travel Rule | `POST /api/regtech/travel-rule/check`, `GET /api/regtech/travel-rule/thresholds` |

### Testing
- 84 new unit tests (47 adapter tests + 37 service tests)
- All tests pass with Mockery isolation (no Redis/database dependency)

---

## [2.7.0] - 2026-02-08

### 📱 Mobile Payment API & Enhanced Authentication

Complete mobile payment infrastructure with stablecoin payments, real-time activity feeds, WebAuthn/Passkey authentication, and P2P transfer helpers. This release provides all backend APIs required for the mobile wallet app's payment and send flows.

### Highlights

| Feature | Description | PRs |
|---------|-------------|-----|
| Mobile Payment Domain | Full domain with models, enums, migrations, state machine | #387 |
| Payment Intent API | Create, submit, cancel, poll payment lifecycle | #388 |
| Real-time Activity | WebSocket events, cursor-paginated activity feed | #389 |
| Wallet Receive | Deposit address generation for Solana/Tron | #390 |
| Receipt Generation | Shareable receipts with PDF export support | #391 |
| TrustCert Export | Certificate details and PDF export for mobile | #392 |
| Security Hardening | Race condition fixes, API spec compliance | #393 |
| Response Alignment | Mobile-spec response shapes, idempotency support | #394 |
| Passkey Authentication | WebAuthn/FIDO2 challenge-response auth | #395 |
| P2P Transfer Helpers | Address validation, name resolution, fee quotes | #396 |

### Added

#### MobilePayment Domain (NEW)
- **PaymentIntent** model - Full payment lifecycle with state machine (CREATED → AWAITING_AUTH → SUBMITTING → PENDING → CONFIRMED/FAILED/CANCELLED/EXPIRED)
- **PaymentReceipt** model - Shareable receipts with public IDs and share tokens
- **ActivityFeedItem** model - Unified activity feed with cursor-based pagination
- **PaymentIntentService** - Merchant validation, fee estimation, state transitions
- **ReceiptService** - Receipt generation with Redis caching and share URLs
- **ActivityFeedService** - Cursor-paginated feed with type filters (All/Income/Expenses)
- **ReceiveAddressService** - Deposit address generation per network/asset
- **NetworkAvailabilityService** - Real-time network status for Solana and Tron
- **FeeEstimationService** - Gas cost estimation with shield-enabled surcharges
- **ExpireStalePaymentIntents** job - Background expiration with chunk processing
- **PaymentStatusChanged** broadcast event - WebSocket real-time updates
- **PaymentNetwork** enum - Solana + Tron with address patterns, explorer URLs
- **PaymentAsset** enum - USDC with decimals configuration
- **PaymentIntentStatus** enum - Full state machine with transition validation

#### Authentication
- **PasskeyAuthenticationService** - WebAuthn/FIDO2 authentication with ECDSA P-256 signature verification
- **PasskeyController** - Challenge generation and assertion verification endpoints
- Passkey registration and credential management on MobileDevice model
- Rate limiting and device blocking for failed passkey attempts

#### Wallet Transfer (P2P Send Flow)
- **WalletTransferService** - Address validation, ENS/SNS name resolution, fee quoting
- **WalletTransferController** - Three endpoints for mobile send flow
- Base58 address validation for Solana (32-44 chars) and Tron (T-prefixed, 34 chars)

#### TrustCert Enhancements
- **CertificateExportService** - Mobile-spec certificate details and PDF export
- Certificate details endpoint with verification status, scope, QR payload

#### Security & Quality
- HSM ECDSA signing support for hardware security modules
- Biometric JWT verification for UserOperation signing
- Production-ready balance checking for gas station
- Comprehensive security audit hardening (5 findings resolved)
- 319+ new domain unit tests (KeyManagement, Privacy, AI, Batch, Wallet)

### API Endpoints

| Category | Endpoints |
|----------|-----------|
| Payment Intents | `POST /v1/payments/intents`, `GET /{intentId}`, `POST /{intentId}/submit`, `POST /{intentId}/cancel` |
| Activity Feed | `GET /v1/activity?cursor=...&type=all` |
| Transactions | `GET /v1/transactions/{txId}`, `POST /{txId}/receipt` |
| Wallet Receive | `GET /v1/wallet/receive?asset=USDC&network=SOLANA` |
| Network Status | `GET /v1/networks/status` |
| Passkey Auth | `POST /v1/auth/passkey/challenge`, `POST /v1/auth/passkey/authenticate` |
| P2P Helpers | `GET /v1/wallet/validate-address`, `POST /v1/wallet/resolve-name`, `POST /v1/wallet/quote` |
| TrustCert | `GET /v1/trustcert/{certId}/certificate`, `POST /{certId}/export-pdf` |

### Security
- WebAuthn signature verification with OpenSSL ECDSA P-256
- Idempotency key support (`X-Idempotency-Key` header) for offline queue resilience
- Route-level rate limiting (throttle:10,1) on authentication endpoints
- Device blocking after repeated failed passkey attempts
- Race condition fixes in payment intent state transitions
- Input validation bounds checking on all new endpoints

### Fixed
- Payment intent response shapes aligned with mobile specification
- Certificate export response aligned with mobile-spec fields
- Stale payment intent expiration with per-intent error isolation

---

## [2.6.0] - 2026-02-02

### 🔐 Privacy Layer & Enhanced ERC-4337 Relayer for Mobile

This release implements the backend APIs required for mobile app privacy features, completing the server-side infrastructure for ERC-4337 account abstraction and ZK-proof based privacy pools.

### Highlights

| Feature | Description | PRs |
|---------|-------------|-----|
| Merkle Tree Infrastructure | Privacy pool state sync for mobile | #368 |
| Smart Account Management | ERC-4337 account deployment | #369 |
| Delegated Proof Generation | Server-side ZK proofs for low-end devices | #370 |
| SRS Manifest | ZK circuit parameters for mobile | #371 |
| WebSocket Merkle Updates | Real-time tree sync | #372 |
| Enhanced Relayer | initCode support, network details | #373 |
| UserOperation Signing | Auth shard signing with biometrics | #374 |
| Security Hardening | Rate limiting, input validation | #375 |

### Added

#### Privacy Domain
- **MerkleTreeService** - Real-time privacy pool state synchronization
- **DelegatedProofService** - Server-side ZK proof generation for mobile
- **SrsManifestService** - ZK circuit SRS file management
- **MerkleRootUpdated** event - WebSocket broadcasting for tree updates

#### Relayer Domain
- **SmartAccountService** - ERC-4337 smart account deployment
- **GasStationService** - Enhanced with initCode support for first transactions
- **UserOperationSigningService** - Auth shard signing with biometric verification

### API Endpoints

| Category | Endpoints |
|----------|-----------|
| Privacy | `GET /api/v1/privacy/merkle-root`, `POST /merkle-path`, `GET /srs-manifest` |
| Delegated Proofs | `POST /api/v1/privacy/delegated-proof`, `GET /{jobId}` |
| Smart Accounts | `POST /api/v1/relayer/account`, `GET /nonce/{address}` |
| UserOp Signing | `POST /api/auth/sign-userop` |

### Security
- Route-level rate limiting on sensitive endpoints (throttle:10,1)
- Input validation with bounds checking for hex strings
- Atomic rate limiting with Cache::increment()
- Production TODO annotations for demo implementations

---

## [2.5.0] - 2026-02-01

### 📱 Mobile App Launch

Mobile app infrastructure for Expo/React Native application (separate repository).

### Added
- Mobile app specification and architecture
- Backend API refinements for mobile consumption
- Passkey/WebAuthn specification (v2.5.1)
- Privacy protocol decision framework

---

## [2.4.0] - 2026-02-01

### 🔐 Privacy & Identity Release

Enterprise privacy infrastructure with zero-knowledge proofs and decentralized identity.

### Highlights

| Feature | Description |
|---------|-------------|
| Key Management | Shamir's Secret Sharing for distributed key custody |
| Privacy Layer | ZK-KYC, Proof of Innocence, Selective Disclosure |
| Commerce | Soulbound Tokens, Merchant Onboarding, Payment Attestations |
| TrustCert | W3C Verifiable Credentials, Certificate Authority |

### Added

#### KeyManagement Domain
- **ShamirService** - Secret sharing with configurable thresholds
- **KeyRecoveryService** - Multi-party key reconstruction
- HSM integration interfaces

#### Privacy Domain
- **ZkKycService** - Zero-knowledge KYC verification
- **ProofOfInnocenceService** - Compliance-friendly privacy proofs
- **SelectiveDisclosureService** - Attribute-level credential sharing

#### Commerce Domain
- **SoulboundTokenService** - Non-transferable identity tokens
- **MerchantOnboardingService** - Merchant verification workflow
- **PaymentAttestationService** - Transaction attestation proofs

#### TrustCert Domain
- **VerifiableCredentialService** - W3C VC issuance/verification
- **CertificateAuthorityService** - PKI certificate management
- **TrustFrameworkService** - Multi-issuer trust policies

---

## [2.3.0] - 2026-01-31

### 🤖 AI Framework & RegTech Foundation

AI-powered financial services with regulatory technology foundation.

### Added
- AI Framework with multi-provider support (OpenAI, Anthropic, Mistral)
- RegTech adapters for compliance automation
- BaaS (Banking-as-a-Service) configuration system
- Enhanced AI agent protocols

---

## [2.2.0] - 2026-01-31

### 📱 Mobile Backend & Biometric Authentication Release

This release delivers complete mobile backend infrastructure with enterprise-grade security, event sourcing integration, real-time push notifications, and WebSocket broadcasting for mobile wallet applications.

### Highlights

| Feature | Description | PRs |
|---------|-------------|-----|
| Mobile Device Management | Device registration, blocking, trust levels | #347-352 |
| Biometric Authentication | ECDSA P-256 challenge-response, device binding | #348-349 |
| Push Notifications | Firebase Cloud Messaging, preference management | #351 |
| Event Sourcing | Mobile domain events with tenant awareness | #349 |
| Cross-Domain Integration | Transaction and security event listeners | #352 |
| WebSocket Broadcasting | Soketi configuration for real-time mobile updates | #360 |
| CI/CD Optimization | Test parallelization, LazilyRefreshDatabase | #357-359 |

### Added

#### Mobile Device Management
- **MobileDeviceService** - Device registration, blocking, trust management
- **MobileDevice model** - Multi-device support per user (max 5)
- Device takeover prevention with automatic session invalidation
- Platform-specific tracking (iOS/Android)
- Push token management with duplicate detection

#### Biometric Authentication
- **BiometricAuthenticationService** - ECDSA P-256 signature verification
- **Challenge-response flow** - 5-minute TTL challenges
- **Device binding** - Public key stored per device
- **Rate limiting** - Auto-lockout after 5 failed attempts
- IP network validation for challenge responses

#### Push Notifications
- **PushNotificationService** - Firebase Cloud Messaging integration
- **NotificationPreferenceService** - User/device preferences
- Notification types: transaction, security, marketing, system
- Scheduled and retry mechanisms
- Read/unread tracking

#### Session Management
- **MobileSessionService** - Device-bound session management
- Token refresh endpoints
- Revoke single/all sessions
- Trusted device extended sessions (8 hours vs 1 hour)

#### Event Sourcing Integration
- **MobileDeviceAggregate** - Event-sourced device state
- 10 domain events for complete audit trail
- WebSocket broadcasting on tenant channels
- Tenant-aware background jobs

#### Cross-Domain Event Listeners
- **SendTransactionPushNotificationListener** - Transaction alerts
- **SendSecurityAlertListener** - Security event notifications
- **LogMobileAuditEventListener** - Compliance audit logging

### API Endpoints

| Category | Endpoints |
|----------|-----------|
| Device Management | `POST/GET/DELETE /api/mobile/devices`, `POST /devices/{id}/block` |
| Biometric Auth | `POST /api/mobile/auth/biometric/challenge`, `/verify` |
| Sessions | `GET/DELETE /api/mobile/sessions` |
| Notifications | `GET /api/mobile/notifications`, `PUT /notifications/preferences` |

### Configuration

New `config/mobile.php`:
- App version management with force update flag
- Device limits and session durations
- Biometric challenge TTL and failure thresholds
- Push notification batch size and retry settings

### Security

- ECDSA P-256 public key verification
- Challenge expiration (5 minutes)
- Biometric lockout after 5 failures (30 minutes)
- Device takeover detection with session invalidation
- IP network validation for challenge responses
- Sensitive fields hidden in API responses

#### WebSocket Broadcasting (#360)
- Soketi (Pusher-compatible) configuration for real-time updates
- Tenant-scoped mobile channel (`tenant.{id}.mobile`)
- TenantBroadcastEvent integration
- Broadcasting configuration in `config/broadcasting.php`

### Changed

#### CI/CD Optimization (#357-359)
- **LazilyRefreshDatabase** - ~40% faster test execution with lazy database refresh
- **Parallel test execution** - 2 workers for unit tests in CI
- **Memory optimization** - Increased from 768M to 1G for test stability
- **Behat optimization** - CI-aware wait times (500ms vs 2-3s)
- **Security test consolidation** - Removed duplicate test execution
- **Pipeline parallelization** - Removed sequential job dependencies

#### API Response Standardization (#356)
- Consistent `error.code` and `error.message` format
- User-friendly validation messages
- Standardized HTTP status codes

### Documentation

- Created Mobile domain README
- Added API endpoint documentation
- Updated CLAUDE.md with Mobile services
- Updated version badges

---

## [2.1.0] - 2026-01-30

### 🔐 Security & Enterprise Features Release

This release delivers enterprise-grade security hardening and infrastructure features, including hardware wallet integration, multi-signature support, real-time WebSocket streaming, and Kubernetes-native deployment.

### Highlights

| Feature | Description | PRs |
|---------|-------------|-----|
| Hardware Wallet Integration | Ledger Nano S/X, Trezor One/Model T support | #341 |
| Multi-Signature Wallets | M-of-N threshold signatures for corporate accounts | #342 |
| WebSocket Streaming | Real-time order book, NAV, transaction updates | #343 |
| Kubernetes Native | Helm charts, HPA, Istio service mesh | #344 |
| Security Hardening | ECDSA, PBKDF2, EIP-2 compliance | #345 |

### Added

#### Hardware Wallet Integration
- **LedgerSignerService** - Ledger Nano S/X device support
- **TrezorSignerService** - Trezor One/Model T device support
- **HardwareWalletManager** - Unified wallet coordination
- **HardwareWalletController** - REST API for device management
- Supported chains: Ethereum, Bitcoin, Polygon, BSC
- BIP44 derivation path support
- Transaction signing workflows with 5-minute TTL

#### Multi-Signature Wallet Support
- M-of-N threshold signature schemes (e.g., 2-of-3, 3-of-5)
- Transaction approval workflows
- Multi-signer coordination
- Signature aggregation and verification

#### WebSocket Real-time Streaming
- Tenant-scoped broadcast channels
- Real-time order book updates
- Live NAV calculations
- Transaction status notifications
- Portfolio value streaming

#### Kubernetes Native Deployment
- **Helm Charts** - Complete deployment package
- **Horizontal Pod Autoscaler** - CPU/memory-based scaling
- **Istio Service Mesh** - Traffic management, mTLS
- **Network Policies** - Pod-to-pod security
- Production and staging value files

### Security

#### Cryptographic Hardening
- **ECDSA ecrecover** - Proper signature validation with public key recovery
- **PBKDF2** - 100,000 iteration key derivation
- **EIP-2** - Signature malleability protection (s-value validation)
- **Timing-safe comparison** - Prevent timing attacks on key comparison
- **Curve order validation** - Secp256k1 compliance

### Infrastructure

#### Docker Build Improvements
- Multi-stage build optimization
- Alpine PHP 8.4-fpm base image
- PECL Redis extension compilation
- Autoconf build dependencies management

### Documentation
- Updated all documentation to v2.1.0
- Added Hardware Wallet API documentation
- Added WebSocket streaming guide
- Cleaned up archived documentation
- Updated version badges across all files

---

## [2.0.0] - 2026-01-28

### 🏢 Multi-Tenancy Release

Transform FinAegis into a **multi-tenant SaaS platform** with team-based data isolation, powered by stancl/tenancy v3.9. This release introduces complete tenant isolation for all domains while maintaining backward compatibility for single-tenant deployments.

### Highlights

| Phase | Deliverable | PRs |
|-------|-------------|-----|
| Phase 1 | Foundation POC - stancl/tenancy setup, tenant model, middleware | #328 |
| Phase 2 | Migration Infrastructure - 14 tenant migration files | #329, #337 |
| Phase 3 | Event Sourcing Integration - Tenant-aware aggregates & projectors | #330 |
| Phase 4 | Model Scoping - 83 models with tenant connection trait | #331 |
| Phase 5 | Queue Job Tenant Context - TenantAwareJob trait | #332 |
| Phase 6 | WebSocket Channel Authorization - Tenant-scoped broadcasting | #333 |
| Phase 7 | Filament Admin Tenant Filtering - Admin panel tenant support | #334 |
| Phase 8 | Data Migration Tooling - Import/export commands | #335 |
| Phase 9 | Security Audit - Isolation validation tests | #336 |

### Added

#### Multi-Tenancy Foundation
- **stancl/tenancy v3.9** integration with custom team-based tenancy
- **Tenant Model** - Links to Teams, supports multiple database strategies
- **InitializeTenancyByTeam Middleware** - Team membership verification, rate limiting, audit logging
- **TeamTenantResolver** - Cached tenant resolution with security checks

#### Tenant Database Migrations (14 files)
- `0001_01_01_000001_create_tenant_accounts_table.php` - Core account tables
- `0001_01_01_000002_create_tenant_transactions_table.php` - Transaction records
- `0001_01_01_000003_create_tenant_transfers_table.php` - Transfer tracking
- `0001_01_01_000004_create_tenant_account_balances_table.php` - Balance projections
- `0001_01_01_000005_create_tenant_compliance_tables.php` - KYC/AML tables
- `0001_01_01_000006_create_tenant_banking_tables.php` - Bank connections
- `0001_01_01_000007_create_tenant_lending_tables.php` - Loan lifecycle
- `0001_01_01_000008_create_tenant_event_sourcing_tables.php` - Event stores
- `0001_01_01_000009_create_tenant_exchange_tables.php` - Trading engine
- `0001_01_01_000010_create_tenant_stablecoin_tables.php` - Stablecoin ops
- `0001_01_01_000011_create_tenant_wallet_tables.php` - Blockchain wallets
- `0001_01_01_000012_create_tenant_treasury_tables.php` - Portfolio management
- `0001_01_01_000013_create_tenant_cgo_tables.php` - Investment platform
- `0001_01_01_000014_create_tenant_agent_protocol_tables.php` - AI agent protocol

#### Event Sourcing Integration
- **TenantAwareStoredEvent** - Base class for tenant-scoped events
- **TenantAwareSnapshot** - Base class for tenant-scoped snapshots
- **TenantAwareAggregateRoot** - Aggregate root with tenant context
- **TenantAwareStoredEventRepository** - Tenant-filtered event storage
- **TenantAwareSnapshotRepository** - Tenant-filtered snapshots

#### Model Scoping
- **UsesTenantConnection Trait** - Applied to 83 Eloquent models
- All domain models updated (Account, Banking, Compliance, Exchange, etc.)
- 16 event sourcing models extend TenantAwareStoredEvent
- 5 snapshot models extend TenantAwareSnapshot

#### Queue & Background Jobs
- **TenantAwareJob Trait** - Explicit tenant context tracking
- Updated AsyncCommandJob, AsyncDomainEventJob, ProcessCustodianWebhook
- Tenant tags for Laravel Horizon monitoring
- QueueTenancyBootstrapper enabled in config

#### WebSocket & Broadcasting
- **TenantChannelAuthorizer** - Tenant-scoped channel authorization
- **TenantBroadcastEvent Trait** - Tenant-aware event broadcasting
- Tenant-scoped channel definitions in routes/channels.php

#### Filament Admin Panel
- **TenantAwareResource Trait** - Automatic tenant scoping for resources
- **FilamentTenantMiddleware** - Tenant context initialization
- **TenantSelectorWidget** - UI widget for switching tenants
- Admin panel tenant filtering across all resources

#### Data Migration Tooling
- **TenantDataMigrationService** - Core data migration service
- **MigrateTenantDataCommand** - `php artisan tenant:migrate-data`
- **ExportTenantDataCommand** - `php artisan tenant:export` (JSON/CSV/SQL)
- **ImportTenantDataCommand** - `php artisan tenant:import`
- Migration tracking tables (tenant_data_migrations, imports, exports)

#### Security Features
- Team membership verification before tenant access
- Rate limiting (60 attempts/minute) on tenant lookups
- Audit logging for all tenancy events
- Explicit 403 responses when tenant required but not found
- Config-based auto-creation (dev/test only)

### Security

- **TenantIsolationSecurityTest** - 9 structural security tests
- **CrossTenantAccessPreventionTest** - 17 isolation validation tests
- Security audit documentation at `docs/security/MULTI_TENANCY_SECURITY_AUDIT.md`
- Pure unit tests using reflection (no Laravel container dependencies)

### Changed

- Database connections now support: central, tenant, tenant_template
- Event sourcing repositories are now tenant-aware
- All financial models use tenant-scoped database connections
- Queue jobs preserve tenant context across async boundaries

### Migration Notes

1. **New Configuration Files**:
   ```bash
   config/tenancy.php      # stancl/tenancy configuration
   config/multitenancy.php # Custom multi-tenancy settings
   ```

2. **Run Central Migrations**:
   ```bash
   php artisan migrate  # Creates tenants table and domains
   ```

3. **Run Tenant Migrations** (after creating tenants):
   ```bash
   php artisan tenants:migrate
   ```

4. **Data Migration** (for existing single-tenant data):
   ```bash
   php artisan tenant:migrate-data {tenant_id} --tables=accounts,transactions
   ```

### Upgrade Notes

For existing single-tenant deployments:
- The default behavior remains unchanged when no tenant is active
- Multi-tenancy features are opt-in via middleware
- Existing data can be migrated using the data migration commands
- See `docs/V2.0.0_MULTI_TENANCY_ARCHITECTURE.md` for detailed upgrade guide

### Breaking Changes

- None for single-tenant deployments
- Multi-tenant deployments require:
  - Tenant creation before accessing tenant-scoped resources
  - Team membership for tenant access
  - Updated route middleware configuration

---

## [1.4.1] - 2026-01-27

### 🐛 Database Cache Connection Fix

Fixes a critical issue where `php artisan optimize` fails with "Access denied for user 'root'@'localhost'" in production environments.

### Fixed

- **Cache Configuration** - Fixed database cache driver using incorrect credentials during optimization
  - `config/cache.php` now properly defaults `DB_CACHE_CONNECTION` to the configured `DB_CONNECTION`
  - Also fixed `lock_connection` to inherit from `DB_CONNECTION` when not explicitly set
  - Resolves issue where Laravel would fall back to hardcoded 'root' credentials during `php artisan optimize`

### Changed

- **Environment Configuration** - Added documentation for `DB_CACHE_CONNECTION` in `.env.example`
  - Commented example showing how to explicitly set cache database connection
  - Helpful for environments requiring separate cache database credentials

### Root Cause Analysis

The `laravel-data` caching step during `php artisan optimize` uses the database cache driver. When `DB_CACHE_CONNECTION` was null (not set in .env), Laravel's cache driver would not properly inherit the application's configured database credentials, instead falling back to the hardcoded MySQL defaults (`root` with empty password) defined in `config/database.php`.

---

## [1.4.0] - 2026-01-27

### 🧪 Test Coverage Expansion Release

Comprehensive test coverage for previously untested domain services and value objects, plus code quality improvements through shared test utilities.

### Highlights

| Category | Deliverables |
|----------|--------------|
| AI Domain | 55 unit tests (ConsensusBuilder, AIAgentService, ToolRegistry) |
| Batch Domain | 37 unit tests (ProcessBatchItemActivity, BatchJobData) |
| CGO Domain | 70 unit tests (CgoKycService, InvestmentAgreementService, etc.) |
| FinancialInstitution Domain | 65 unit tests (ComplianceCheckService, PaymentVerificationService, etc.) |
| Fraud Domain | 18 unit tests for FraudDetectionService |
| Wallet Domain | 37 unit tests (KeyManagementService + Value Objects) |
| Regulatory Domain | 13 unit tests for ReportGeneratorService |
| Stablecoin Domain | 24 unit tests for Value Objects |
| Test Utilities | InvokesPrivateMethods helper trait |
| Code Quality | PHPStan Level 8 fixes, API scope test updates |
| **Total** | **319 new domain tests** |

### Added

#### Domain Test Suites

- **AI Domain Tests** (55 tests)
  - `ConsensusBuilderTest` (7 tests) - Consensus building algorithm
  - `AIAgentServiceTest` (24 tests) - Chat responses, keyword matching, feedback
  - `ToolRegistryTest` (24 tests) - Tool registration, search, schema export

- **Batch Domain Tests** (37 tests)
  - `ProcessBatchItemActivityTest` (24 tests)
    - Currency conversion rates (USD, EUR, GBP, PHP)
    - Conversion calculations with various amounts
    - Edge cases (zero, small, large amounts)
  - `BatchJobDataTest` (13 tests)
    - Data object creation and validation
    - UUID generation, type handling, metadata

- **Fraud Domain Tests** (18 tests)
  - `FraudDetectionServiceTest` - Pattern detection, risk scoring
  - Tests high-value, velocity, geographic, time-based, and round amount patterns
  - Aggregation logic and risk multiplier calculations
  - Uses anonymous class test doubles for Eloquent models

- **Wallet Domain Tests** (37 tests)
  - `KeyManagementServiceTest` (23 tests)
    - BIP39 mnemonic generation and validation
    - Key derivation (BIP32/BIP44)
    - Multi-blockchain address generation (Ethereum, Bitcoin, Solana, etc.)
    - Signature operations and key storage
  - `WalletValueObjectsTest` (14 tests)
    - `WalletAddress` value object (address, blockchain, label)
    - `TransactionResult` value object (hash, status, gas, logs)
    - Status helpers (isSuccess, isPending, isFailed)

- **Regulatory Domain Tests** (13 tests)
  - `ReportGeneratorServiceTest` - Report generation utilities
  - CSV header extraction (CTR, SAR, KYC report types)
  - Certification statements for regulatory compliance
  - Filename generation with proper formatting
  - XML conversion for nested data structures

- **Stablecoin Domain Tests** (24 tests)
  - `StablecoinValueObjectsTest` - Financial value objects
  - `LiquidationThreshold` - Collateral health levels (safe/margin call/liquidation)
  - `CollateralRatio` - Ratio calculations with BigDecimal precision
  - `PriceData` - Price feeds with staleness detection

- **CGO Domain Tests** (70 tests)
  - `CgoKycServiceTest` (17 tests) - KYC verification and compliance checks
  - `InvestmentAgreementServiceTest` (18 tests) - Agreement generation and management
  - `RiskAssessmentServiceTest` (18 tests) - Risk scoring and investment suitability
  - `OfferingValidatorServiceTest` (17 tests) - Offering validation rules

- **FinancialInstitution Domain Tests** (65 tests)
  - `ComplianceCheckServiceTest` (18 tests) - Regulatory compliance verification
  - `PaymentVerificationServiceTest` (18 tests) - Payment validation and fraud checks
  - `BankingConnectorServiceTest` (14 tests) - Banking API integration tests
  - `TransactionMonitoringServiceTest` (15 tests) - Real-time transaction monitoring

#### Test Utilities

- **InvokesPrivateMethods Trait** (`tests/Traits/`)
  - `invokeMethod()` - Invoke private/protected methods via reflection
  - `getPrivateProperty()` - Read private property values
  - `setPrivateProperty()` - Set private property values
  - Reduces code duplication across test files (DRY improvement)

#### Domain Commands

- **DomainCreateCommand** (`php artisan domain:create`)
  - Scaffold new domain structure with all required files
  - Creates Models, Services, Events, Repositories directories
  - Generates ServiceProvider template
  - Creates module.json manifest

### Fixed

- PHPStan Level 8 errors in `AccountQueryService`
- Test isolation issues with Eloquent model mocking
- Type safety for financial calculations in value objects
- API scope authentication in 20+ feature tests after security hardening
- Test expectations for empty scopes (now correctly deny access)
- Flaky `DemoLendingServiceTest` credit score simulation
- `AgentMessageBusServiceTest` mock return types (Agent model vs array)
- `MetricsMiddlewareTest` timing-sensitive assertion (now uses assertNotNull)

### CI/CD

- **Deploy Workflow Improvements**
  - Added Redis service container for pre-deployment tests
  - Added APP_KEY environment variable to build-artifacts job
  - Fixed tar "file changed as we read it" error with `--warning=no-file-changed`
  - Excluded `bootstrap/cache/*` from deployment package
  - Properly skip deployment steps when server credentials not configured
  - Added step outputs for conditional deployment execution
  - Improved notification messages for skipped vs failed deployments

### Security

- **Rate limiting threshold** - Reduced auth attempts from 5 to 3 (brute force protection)
- **Session limit** - Reduced max concurrent sessions from 5 to 3 (session hijacking protection)
- **Token expiration enforcement** - All auth controllers now use `createTokenWithScopes()` for proper token expiration
  - Fixed: LoginController, PasswordController, SocialAuthController, TwoFactorAuthController
- **API scope bypass fix** - Removed backward compatibility bypass in `CheckApiScope` middleware
- **Agent scope bypass fix** - `AgentScope::hasScope()` now returns false for empty scopes (was returning true)

### Developer Experience

- Anonymous class test doubles pattern documented
- Test utilities centralized for reuse
- Pure unit tests (no database dependencies)

---

## [1.3.0] - 2026-01-25

### 🔧 Platform Modularity Release

Transform FinAegis from a monolithic domain structure to a **modular architecture** where domains can be installed independently. This enables faster onboarding, customized deployments, and better maintainability.

### Highlights

| Category | Deliverables |
|----------|--------------|
| Shared Interfaces | 4 new domain contracts for loose coupling |
| Security | Input validation and audit logging across shared services |
| Module Manifests | 29 domain manifests with dependency declarations |
| Domain Commands | 5 Artisan commands for domain management |
| Infrastructure | DependencyResolver, DomainManager services |

### Added

#### Phase 1: Shared Domain Interfaces
- **WalletOperationsInterface** - Wallet funds management contract
  - `depositFunds()`, `withdrawFunds()`, `getBalance()`
  - `lockFunds()`, `unlockFunds()`, `transferBetweenWallets()`
- **AssetTransferInterface** - Cross-domain asset operations
  - `transfer()`, `getAssetDetails()`, `validateTransfer()`
  - `convertAsset()`, `getTransferStatus()`
- **PaymentProcessingInterface** - Payment gateway abstraction
  - `processDeposit()`, `processWithdrawal()`, `getPaymentStatus()`
  - `refundPayment()`, `validatePaymentRequest()`
- **AccountQueryInterface** - Read-only account operations
  - `getAccountDetails()`, `getBalance()`, `getTransactionHistory()`
  - `accountExists()`, `getAccountsByOwner()`

#### Phase 2: Security Implementation
- **FinancialInputValidator** trait - Consistent input validation
  - UUID validation for all identifiers
  - Amount validation (positive, precision limits)
  - Currency/asset code validation (ISO 4217)
  - Reference and metadata sanitization
- **AuditLogger** trait - Financial operation audit trail
  - Automatic sensitive data redaction
  - Request ID tracking for correlation
  - Operation timing and outcome logging
- **Encrypted Cache Storage** - Secure wallet locks and payment records
- **Reduced Lock TTL** - From 24h to 1h for security

#### Phase 3: Module Manifest System
- **ModuleManifest** value object - Parses `module.json` files
- **DependencyResolver** service - Builds dependency trees, detects cycles
- **DomainManager** service - Central domain operations management
- **29 module.json files** - One per domain with:
  - Version and description metadata
  - Required and optional dependencies
  - Provided interfaces, events, and commands
  - Path configuration (routes, migrations, config)

#### Phase 4: Domain Installation Commands
- `php artisan domain:list` - List all domains with status and dependencies
  - Filter by type (`--type=core`) or status (`--status=installed`)
  - JSON output support (`--json`)
- `php artisan domain:install {domain}` - Install a domain
  - Automatic dependency resolution
  - Migration execution
  - Config publishing
- `php artisan domain:remove {domain}` - Safe domain removal
  - Dependent checking (prevents breaking changes)
  - Migration rollback
  - Force option for overrides
- `php artisan domain:dependencies {domain}` - Show dependency tree
  - Visual tree rendering
  - Flat list option (`--flat`)
  - Unsatisfied dependency warnings
- `php artisan domain:verify {domain?}` - Verify domain health
  - Manifest validation
  - Dependency satisfaction
  - Interface implementation checks

#### Domain Classification
- **Core Domains** (always required): `shared`, `account`, `user`, `compliance`
- **Optional Financial**: `exchange`, `lending`, `treasury`, `wallet`, `payment`, `banking`, `asset`, `stablecoin`
- **Optional AI/Agent**: `ai`, `agent-protocol`, `governance`
- **Optional Infrastructure**: `monitoring`, `performance`, `fraud`, `batch`, `webhook`

### Changed
- Service locator anti-pattern removed from WalletOperationsService
- Value objects (AccountUuid, Money) used consistently in AssetTransferService

### Security
- Input validation added to all shared service implementations
- Audit logging for compliance and security monitoring
- Encrypted cache storage for sensitive operation data
- 365-day retention for audit and security logs

### Developer Experience
- Domain discovery via `domain:list` command
- Dependency visualization via `domain:dependencies`
- Health verification via `domain:verify`
- JSON output for CI/CD integration

---

## [1.2.0] - 2026-01-13

### 🚀 Feature Completion Release

This release completes the **Phase 6 integration bridges**, adds **production observability**, and resolves all actionable TODO items - making the platform feature-complete for production deployment.

### Highlights

| Category | Deliverables |
|----------|--------------|
| Integration Bridges | Agent-Payment, Agent-KYC, Agent-MCP bridges |
| Enhanced Features | Yield Optimization, EDD Workflows, Batch Processing |
| Observability | 10 Grafana dashboards, Prometheus alerting rules |
| Domain Completions | StablecoinReserve model, Paysera integration |
| TODO Cleanup | 10 TODOs resolved, 2 deferred (external blockers) |

### Added

#### Integration Bridges (Phase 6 Completion)
- **AgentPaymentIntegrationService** - Connects Agent Protocol to Payment System
  - Wallet-to-account linking for AI agents
  - Real financial transaction execution
  - Balance synchronization across systems
- **AgentKycIntegrationService** - Unified KYC across human and AI agents
  - KYC inheritance from linked users
  - Compliance tier mapping
  - Regulatory compliance for AI-driven transactions
- **AgentMCPBridgeService** - AI Framework integration with Agent Protocol
  - Tool execution with proper agent authorization
  - Comprehensive audit logging
  - MCP tool registration for agents

#### Enhanced Features
- **YieldOptimizationController** - Wired to existing YieldOptimizationService
  - Portfolio optimization endpoints
  - Yield projection API
  - Rebalancing recommendations
- **EnhancedDueDiligenceService** - Advanced compliance workflows
  - EDD workflow initiation and management
  - Document collection and verification
  - Risk assessment scoring
  - Periodic review scheduling
- **BatchProcessingController** - Complete scheduled processing
  - Batch scheduling with dispatch delay
  - Cancellation with compensation patterns
  - Progress tracking and retry logic

#### Production Observability
- **Grafana Dashboards** (10 domain dashboards in `infrastructure/observability/grafana/`)
  - Account/Banking metrics
  - Exchange trading metrics
  - Lending portfolio health
  - Compliance monitoring
  - Agent Protocol metrics
  - Stablecoin reserves
  - Treasury portfolio
  - Wallet operations
  - System health overview
  - AI Framework metrics
- **Prometheus Alerting Rules** (`infrastructure/observability/prometheus/`)
  - Critical alerts (immediate response)
  - Warning alerts (investigation needed)
  - Domain-specific alert thresholds

#### Stablecoin Domain Completion
- **StablecoinReserve Model** - Read model for reserve data projection
  - Reserve tracking with custodian information
  - Allocation percentage calculations
  - Verification status and audit trail
- **StablecoinReserveAuditLog Model** - Comprehensive audit logging
  - Deposit/withdrawal tracking
  - Rebalance history
  - Price update records
- **StablecoinReserveProjector** - Event sourcing projection
  - Projects ReservePool aggregate events
  - Real-time reserve statistics

#### Payment Integration
- **PayseraDepositServiceInterface** - Contract for Paysera operations
- **PayseraDepositService** - Production Paysera integration
  - OAuth2 authentication flow
  - Deposit initiation with redirect
  - Callback handling with verification
- **DemoPayseraDepositService** - Demo mode simulation
  - Predictable test behaviors
  - No external API calls
  - Instant callback simulation
- **PayseraDepositController** - Full controller implementation
  - Input validation
  - Error handling
  - Demo/production mode switching

#### Workflow & Saga Additions
- **LoanDisbursementSaga** - Multi-step loan orchestration
  - Loan approval workflow
  - Fund disbursement with compensation
  - Notification integration
- **NotifyReputationChangeActivity** - Real Laravel notifications
  - Email notifications
  - Database notifications
  - Customizable templates

### Changed

- **DemoServiceProvider** - Added Paysera service bindings
- **StablecoinAggregateRepository** - Now uses real StablecoinReserve model
- **ProcessCustodianWebhook** - Wired to WebhookProcessorService

### Fixed

- Removed TODO stubs from PayseraDepositController
- Resolved StablecoinReserve model dependency in repository
- Fixed MySQL index name length (64 char limit)
- PHPStan Level 8 compliance for all new files

### Technical Debt Status

| Category | Count | Status |
|----------|-------|--------|
| Resolved | 10 | ✅ Complete |
| Blocked | 1 | 🚫 External (laravel-workflow RetryOptions) |
| Deferred | 1 | 📉 v1.3.0 (BasketService refactor) |

### Migration Notes

1. Run migrations for new tables:
   ```bash
   php artisan migrate
   ```
   New tables: `stablecoin_reserves`, `stablecoin_reserve_audit_logs`, `edd_*`, `agent_mcp_audit_logs`

2. Configure Paysera (optional):
   ```env
   PAYSERA_PROJECT_ID=your_project_id
   PAYSERA_SIGN_PASSWORD=your_sign_password
   ```

3. Set up observability (optional):
   - Import Grafana dashboards from `infrastructure/observability/grafana/`
   - Configure Prometheus with rules from `infrastructure/observability/prometheus/`

### Upgrade Notes

This release has no breaking changes. All new features are additive.

```bash
git pull origin main
composer install
php artisan migrate
php artisan config:cache
```

---

## [1.1.0] - 2026-01-11

### 🔧 Foundation Hardening Release

This release focuses on **code quality**, **test coverage expansion**, and **CI/CD hardening** - laying a solid foundation for future feature development.

### Highlights

| Metric | v1.0.0 | v1.1.0 | Improvement |
|--------|--------|--------|-------------|
| PHPStan Level | 5 | **8** | +3 levels |
| PHPStan Baseline | 54,632 lines | **9,007 lines** | **83% reduction** |
| Test Files | 458 | **499** | +41 files |
| Behat Features | 1 | **22** | +21 features |

### Added

#### Comprehensive Domain Test Suites
- **Banking Domain** (40 tests)
  - BankingConnectorTest - Multi-bank routing
  - BankRoutingServiceTest - Intelligent bank selection
  - BankHealthMonitorTest - Health monitoring
- **Governance Domain** (55 tests)
  - VotingPowerCalculatorTest - Voting weight calculations
  - ProposalStatusTest - Proposal lifecycle
  - VoteTypeTest - Vote type behaviors
  - GovernanceExceptionTest - Exception handling
- **User Domain** (64 tests)
  - NotificationPreferencesTest - Email/SMS/push settings
  - PrivacySettingsTest - Privacy controls
  - UserPreferencesTest - Language/timezone/currency
  - UserRolesTest - Role-based access
  - UserProfileExceptionTest - Exception factory
- **Compliance Domain** (34 tests)
  - AlertStatusTest - Alert lifecycle management
  - AlertSeverityTest - Severity levels and priorities
- **Treasury Domain** (53 tests)
  - RiskProfileTest - Risk levels and exposure limits
  - AllocationStrategyTest - Portfolio allocation
  - LiquidityMetricsTest - Basel III regulatory metrics
- **Lending Domain** (59 tests)
  - LoanPurposeTest - Loan purposes and interest rates
  - CollateralTypeTest - Collateral and LTV ratios
  - CreditScoreTest - Credit score validation
  - RiskRatingTest - Risk ratings and multipliers

#### PHPStan Level 8 Achievement
- Upgraded from level 5 → 6 → 7 → **8**
- Fixed event sourcing aggregate return types
- Added null-safe operators in AI/MCP services
- Corrected reflection method null-safety in tests
- Added User type annotations to ComplianceController

### Changed

#### CI/CD Hardening
- **Security Audit Enforcement**: CI now fails on critical/high vulnerabilities
- Removed obsolete backup files from `bin/` directory
- Enhanced pre-commit checks for better local validation

### Fixed

- PHPStan baseline errors across all domains
- Null-safety issues in AI service implementations
- Reflection method null-pointer exceptions in tests
- Type annotations for Eloquent factory return types

### Developer Experience

#### Pre-Commit Quality Checks
```bash
./bin/pre-commit-check.sh --fix  # Auto-fix issues
```

#### Test Commands
```bash
./vendor/bin/pest --parallel                    # Run all tests
./vendor/bin/pest tests/Domain/Banking/         # Run domain tests
```

### Upgrade Notes

This is a quality-focused release with no breaking changes.

1. Pull the latest changes:
   ```bash
   git pull origin main
   composer install
   ```

2. Verify PHPStan compliance:
   ```bash
   XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G
   ```

3. Run the test suite:
   ```bash
   ./vendor/bin/pest --parallel
   ```

---

## [1.0.0] - 2024-12-21

### 🎉 Open Source Release

This release marks the transformation of FinAegis from a proprietary platform to an **open-source core banking framework** with GCU (Global Currency Unit) as its reference implementation.

### Added

#### Open Source Foundation (Phase 1)
- **CONTRIBUTING.md** - Comprehensive contribution guidelines with development workflow
- **SECURITY.md** - Vulnerability reporting and security policy
- **CODE_OF_CONDUCT.md** - Contributor Covenant 2.1 community guidelines
- **Architecture Decision Records (ADRs)**
  - ADR-001: Event Sourcing Architecture
  - ADR-002: CQRS Pattern Implementation
  - ADR-003: Saga Pattern for Distributed Transactions
  - ADR-004: GCU Basket Currency Design
  - ADR-005: Demo Mode Architecture
- **ARCHITECTURAL_ROADMAP.md** - Strategic 4-phase transformation plan
- **IMPLEMENTATION_PLAN.md** - Sprint-level implementation details

#### Platform Modularity (Phase 2)
- **Domain Dependency Analysis** - Three-tier domain classification (Core, Supporting, Optional)
- **Shared Contracts for Domain Decoupling**
  - `AccountOperationsInterface` - Cross-domain account operations
  - `ComplianceCheckInterface` - KYC/AML verification abstraction
  - `ExchangeRateInterface` - Currency conversion abstraction
  - `GovernanceVotingInterface` - Voting system abstraction
- **AccountOperationsAdapter** - Reference implementation bridging interface to Account domain

#### GCU Reference Implementation (Phase 3)
- **Basket Domain README** - Complete domain documentation
- **BUILDING_BASKET_CURRENCIES.md** - Step-by-step tutorial (776 lines)
  - Custom basket creation from scratch
  - NAV calculation implementation
  - Rebalancing strategies
  - Governance integration
  - Testing patterns

#### Production Hardening (Phase 4)
- **SECURITY_AUDIT_CHECKLIST.md** - 74+ item security review framework
  - Authentication & session management
  - Authorization & access control
  - Data protection & encryption
  - Financial security & fraud prevention
  - API security & rate limiting
  - Infrastructure & container security
- **DEPLOYMENT_GUIDE.md** - Production deployment documentation
  - Docker Compose configuration
  - Kubernetes manifests (Deployment, Service, Ingress, HPA)
  - Database setup and backup strategies
  - Queue worker configuration
  - Scaling considerations
- **OPERATIONAL_RUNBOOK.md** - Day-to-day operations manual
  - Incident response procedures (SEV-1 to SEV-4)
  - Common scenarios with resolutions
  - Maintenance procedures
  - Disaster recovery (RTO/RPO objectives)

### Changed
- Website content updated for open-source accuracy
- Investment components converted to demo-only mode
- Enhanced documentation structure with clear separation of concerns
- Improved domain boundaries with interface-based decoupling

### Architecture Highlights
- **29 Bounded Contexts** organized in three tiers
- **Event Sourcing** with domain-specific event stores
- **CQRS** with Command/Query Bus infrastructure
- **Saga Pattern** for distributed transaction compensation
- **Demo Mode** for development without external dependencies

## [0.9.0] - 2024-12-18

### Added
- **Agent Protocol (AP2/A2A)** - Full implementation of Google's Agent Payments Protocol
  - Agent registration with DID support
  - Escrow service for secure transactions
  - Reputation and trust scoring system
  - A2A messaging infrastructure
  - MCP tools for AI agent integration
  - Protocol negotiation API
  - OAuth2-style agent scopes

### Changed
- AI Framework enhanced with Agent Protocol bridge service
- Multi-agent coordination capabilities

## [0.8.0] - 2024-12-01

### Added
- **Treasury Management Domain**
  - Portfolio management with event sourcing
  - Cash allocation and yield optimization
  - Investment strategy workflows
  - Treasury aggregates with full audit trail

- **Enhanced Compliance Domain**
  - Three-tier KYC verification (Basic, Enhanced, Full)
  - AML screening integration
  - Transaction monitoring with SAR/CTR generation
  - Biometric verification support

### Changed
- Improved event sourcing patterns across domains
- Enhanced saga compensation logic

## [0.7.0] - 2024-11-15

### Added
- **AI Framework**
  - Production-ready MCP server with 20+ banking tools
  - Event-sourced AI interactions
  - Tool execution with audit trail
  - Claude and OpenAI provider support

- **Distributed Tracing**
  - OpenTelemetry integration
  - Cross-domain trace correlation
  - Performance monitoring

### Fixed
- PHPStan level 5 compliance issues
- Test isolation for security tests

## [0.6.0] - 2024-11-01

### Added
- **Governance Domain**
  - Democratic voting system
  - Asset-weighted voting strategy
  - Proposal lifecycle management
  - GCU basket composition voting

- **Stablecoin Domain Enhancements**
  - Multi-collateral support
  - Health monitoring with margin calls
  - Liquidation workflows
  - Position management

## [0.5.0] - 2024-10-15

### Added
- **GCU (Global Currency Unit) Basket**
  - 6-currency basket implementation (USD, EUR, GBP, CHF, JPY, XAU)
  - NAV calculation service
  - Automatic rebalancing with governance
  - Performance tracking

- **Liquidity Pool Enhancements**
  - Spread management saga
  - Market maker workflow
  - Impermanent loss protection
  - AMM (Automated Market Maker) implementation

## [0.4.0] - 2024-10-01

### Added
- **Exchange Domain**
  - Order matching engine with saga pattern
  - Liquidity pool management
  - External exchange connectors (Binance, Kraken)
  - 6-tier fee system
  - 44 domain events

- **Lending Domain**
  - P2P lending platform
  - Credit scoring system
  - Loan lifecycle management
  - Risk assessment workflows

## [0.3.0] - 2024-09-15

### Added
- **Wallet Domain**
  - Multi-chain blockchain support (BTC, ETH, Polygon, BSC)
  - Transaction signing
  - Balance tracking
  - Withdrawal workflows with saga compensation

- **Demo Mode Architecture**
  - Service switching pattern
  - Mock implementations for all external services
  - Demo data seeding
  - Visual demo indicators

## [0.2.0] - 2024-09-01

### Added
- **Account/Banking Domain**
  - Event-sourced account management
  - Multi-asset balance tracking
  - SEPA/SWIFT transfer support
  - Multi-bank connector pattern (Paysera, Deutsche Bank, Santander)
  - Intelligent bank routing

- **CQRS Infrastructure**
  - Command Bus with middleware support
  - Query Bus with caching
  - Domain Event Bus bridging Laravel events

## [0.1.0] - 2024-08-15

### Added
- Initial project structure with Domain-Driven Design
- Event sourcing foundation using Spatie Event Sourcing
- Laravel 12 with PHP 8.4 support
- Filament 3.0 admin panel
- Pest PHP testing framework
- PHPStan level 5 static analysis
- CI/CD pipeline with GitHub Actions

---

## Version History Summary

| Version | Date | Highlights |
|---------|------|------------|
| **2.0.0** | **2026-01-28** | **🏢 Multi-Tenancy** |
| 1.4.1 | 2026-01-27 | 🐛 Database Cache Connection Fix |
| 1.4.0 | 2026-01-27 | 🧪 Test Coverage Expansion |
| 1.3.0 | 2026-01-25 | 🔧 Platform Modularity |
| 1.2.0 | 2026-01-13 | 🚀 Feature Completion |
| 1.1.0 | 2026-01-11 | 🔧 Foundation Hardening |
| 1.0.0 | 2024-12-21 | 🎉 Open Source Release |
| 0.9.0 | 2024-12-18 | Agent Protocol (AP2/A2A) |
| 0.8.0 | 2024-12-01 | Treasury Management, Enhanced Compliance |
| 0.7.0 | 2024-11-15 | AI Framework, Distributed Tracing |
| 0.6.0 | 2024-11-01 | Governance, Stablecoin Enhancements |
| 0.5.0 | 2024-10-15 | GCU Basket, Liquidity Pools |
| 0.4.0 | 2024-10-01 | Exchange, Lending |
| 0.3.0 | 2024-09-15 | Wallet, Demo Mode |
| 0.2.0 | 2024-09-01 | Account/Banking, CQRS |
| 0.1.0 | 2024-08-15 | Initial Release |

## Upgrade Notes

### From 0.9.x to 1.0.0
This is a documentation-focused release with no breaking changes.
- Review new contribution guidelines in `CONTRIBUTING.md`
- Consider using shared contracts for domain decoupling
- Review security checklist before production deployment

### From 0.8.x to 0.9.x
- Run `php artisan migrate` for Agent Protocol tables
- Update `.env` with `AGENT_PROTOCOL_*` configuration
- Register AgentProtocolServiceProvider if not auto-discovered

### From 0.7.x to 0.8.x
- Run `php artisan migrate` for Treasury tables
- New compliance configuration in `config/compliance.php`

### From 0.6.x to 0.7.x
- Run `php artisan migrate` for AI Framework tables
- Configure AI providers in `config/ai.php`

[Unreleased]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v1.4.1...v2.0.0
[1.4.1]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.9.0...v1.0.0
[0.9.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.8.0...v0.9.0
[0.8.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.7.0...v0.8.0
[0.7.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.6.0...v0.7.0
[0.6.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/FinAegis/core-banking-prototype-laravel/releases/tag/v0.1.0
