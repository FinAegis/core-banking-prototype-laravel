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
```

## Architecture

- **Web3 Integration**: `app/Infrastructure/Web3/` (EthRpcClient, AbiEncoder) — also legacy `app/Domain/Relayer/Services/EthRpcClient.php`
- **ZK Circuits**: `storage/app/circuits/` (Circom sources + Solidity verifiers)
- **58 domains** in `app/Domain/` (DDD bounded contexts)
- **Payment Protocols**: x402 (Coinbase), MPP (Stripe/Tempo), AP2 (Google)
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
- Wire contract is camelCase: `quoteId`, `intentId`, `evm.ownerPasskeyCredentialId`. `Idempotency-Key` is an HTTP header, not a body field.
- Confirmation: `HeliusTransactionProcessor` for Solana (existing webhook), `PollEvmWalletSendConfirmations` for EVM (cron, every minute)
- Operator commands: `php artisan privy:verify-jwt <token>`, `php artisan wallet:inspect-user <email>`
- Spec: `docs/superpowers/specs/2026-05-05-wallet-privy-noncustodial-design.md` (if present); see PR #1017

| Pitfall | Fix |
|---|---|
| `GuzzleHttp\ClientInterface` unbound | Bind to `Client::class` in `AppServiceProvider::register` — without it, `PrivyJwtVerifier` fails to resolve and `/privy-login` 500s |
| EVM address case | Always `strtolower()` for storage (matches our existing EVM convention) — Solana stays case-sensitive |
| Float-money amounts | Preparers validate `amount` against numeric-string + bcmath; never `(float)` for money |
| Idempotency key on the wrong field | It's an HTTP header (`Idempotency-Key`), not a body field — matches `/pay`/`/pay/card` |
| `quote_id` vs `quoteId` | Wire contract is camelCase across the board (matches mobile RN/TS request types) |

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
- Webhook controllers: Helius handles Solana, Alchemy handles EVM — both send FCM push via `PushNotificationService`
- Alchemy webhook signing keys: stored in `webhook_endpoints` table (managed by `AlchemyWebhookManager`), not env vars
- Test tables: use `Tests\Traits\CreatesSolanaTestTables` trait for in-memory SQLite schema in webhook/wallet tests
- Multi-connection tests: `tests/MultiConnection/` — runs against real MySQL with `database.force_real_tenant_connection=true` so models with `UsesTenantConnection` use a separate MySQL session. Required check on PRs. See `docs/superpowers/specs/2026-04-26-multi-connection-test-infrastructure-design.md`.
- Parallel agent merges: always check for duplicate `use` imports after merging agent branches
- Reviewer accounts: operator-only tool for app-store review submissions — see `docs/operations/reviewer-accounts.md`. Bypasses are scoped via `account_flags` table; the daily sweep runs at 00:10 UTC via `routes/console.php`.
