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
```

## Architecture

- **Web3 Integration**: `app/Infrastructure/Web3/` (EthRpcClient, AbiEncoder) — also legacy `app/Domain/Relayer/Services/EthRpcClient.php`
- **ZK Circuits**: `storage/app/circuits/` (Circom sources + Solidity verifiers)
- **56 domains** in `app/Domain/` (DDD bounded contexts)
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

```bash
gh pr checks <PR_NUMBER>              # Check PR status
gh run view <RUN_ID> --log-failed     # View failed logs
```

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
- Parallel agent merges: always check for duplicate `use` imports after merging agent branches
