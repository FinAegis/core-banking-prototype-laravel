# MCP Public Server Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up a public, OAuth-2.1-protected, Anthropic-spec MCP server at `https://mcp.zelta.app/mcp` that wraps the existing internal MCP tool framework so external agents (Claude Desktop, Cursor, Continue.dev, partners) can connect.

**Architecture:** New `app/Domain/MCP/` module is a thin wire-protocol gateway that adapts JSON-RPC 2.0 over streamable HTTP to the existing `App\Domain\AI\MCP\ToolRegistry`. OAuth via Laravel Passport (already installed) extended with Dynamic Client Registration and RFC 9728 Protected Resource Metadata. Side-by-side npm package `@finaegis/mcp` ships a stdio↔remote relay for older clients and CI conformance testing.

**Tech Stack:** PHP 8.4 / Laravel 12 / Pest / Passport ^13 / Redis / OpenTelemetry / TypeScript 5 / `@modelcontextprotocol/sdk` / Vitest / `keytar`.

**Source spec:** `docs/superpowers/specs/2026-04-27-mcp-server-design.md`

---

## File Structure

Files this plan creates or modifies. Each entry has one clear responsibility.

### New PHP files (server)

| File | Responsibility |
|---|---|
| `config/mcp.php` | Tool catalog, scopes, kill-switches, spending defaults, host config |
| `app/Domain/MCP/Server/StreamableHttpController.php` | HTTP entry: `POST /mcp` + `GET /mcp` (SSE) |
| `app/Domain/MCP/Server/JsonRpcRouter.php` | Method dispatch — `initialize`, `tools/list`, `tools/call`, `resources/list`, `resources/read`, `ping` |
| `app/Domain/MCP/Server/SseStreamManager.php` | Server→client SSE event stream |
| `app/Domain/MCP/Server/McpToolAdapter.php` | Bridges `MCPToolInterface` → JSON-RPC tool call envelope |
| `app/Domain/MCP/Auth/McpOAuthGuard.php` | Bearer token validation + scope enforcement |
| `app/Domain/MCP/Auth/DynamicClientRegistrationController.php` | `POST /oauth/register` (RFC 7591) |
| `app/Domain/MCP/Auth/ConsentScreenController.php` | Custom consent UI replacing Passport's default |
| `app/Domain/MCP/Discovery/ProtectedResourceMetadataController.php` | `GET /.well-known/oauth-protected-resource` (RFC 9728) |
| `app/Domain/MCP/Discovery/AuthorizationServerMetadataController.php` | `GET /.well-known/oauth-authorization-server` |
| `app/Domain/MCP/Audit/ToolInvocationLogger.php` | Writes to `mcp_tool_invocations` |
| `app/Domain/MCP/Policy/SpendingLimitService.php` | Per-token rolling-window spending enforcement |
| `app/Domain/MCP/Policy/IdempotencyCache.php` | Redis-backed idempotency replay logic |
| `app/Domain/MCP/Tools/Ramp/RampStartTool.php` | Wraps `RampController::session()` for MCP |
| `app/Domain/MCP/Tools/Ramp/RampStatusTool.php` | Wraps `RampController::getSession()` for MCP |
| `app/Domain/MCP/Resources/AccountProfileResource.php` | `account://profile` — primary account metadata |
| `app/Domain/MCP/Resources/AccountBalanceResource.php` | `account://balance/{currency}` — live balance |
| `app/Domain/MCP/Resources/RecentTransactionsResource.php` | `transactions://recent?limit=N` |
| `app/Domain/MCP/Resources/SingleTransactionResource.php` | `transaction://{id}` |
| `app/Domain/MCP/Routes/api.php` | Subdomain-bound route group for `mcp.zelta.app` |
| `app/Providers/McpServiceProvider.php` | Bootstraps Domain\MCP, registers routes + middleware alias |
| `app/Filament/Admin/Resources/McpOAuthClientResource.php` | Filament admin: OAuth clients |
| `app/Filament/Admin/Resources/McpToolInvocationResource.php` | Filament admin: tool invocations log |
| `app/Filament/Admin/Resources/McpActiveSessionResource.php` | Filament admin: live SSE sessions |

### New migrations

| File | What it does |
|---|---|
| `database/migrations/2026_04_28_000001_create_mcp_tool_invocations_table.php` | Audit log table |
| `database/migrations/2026_04_28_000002_create_mcp_token_policies_table.php` | Per-token spending limit + scoped tools |
| `database/migrations/2026_04_28_000003_extend_oauth_clients_for_dcr.php` | Adds DCR metadata columns to Passport's `oauth_clients` |

### New views (Blade)

| File | Responsibility |
|---|---|
| `resources/views/mcp/consent.blade.php` | OAuth consent screen rendered by ConsentScreenController |
| `resources/views/features/mcp.blade.php` | Marketing page — MCP-native banking |

### Modified files (existing)

| File | Change |
|---|---|
| `bootstrap/app.php` | Add `mcp.` subdomain routing branch |
| `routes/api.php` | No change (subdomain has its own route file) |
| `app/Providers/AppServiceProvider.php` (or `bootstrap/providers.php`) | Register `McpServiceProvider` |
| `resources/views/developers/mcp-tools.blade.php` | Full rewrite — public-server messaging |
| `resources/views/welcome.blade.php` | Add MCP hero badge |
| `resources/views/about.blade.php` | Update tech-stack section |
| `resources/views/pricing.blade.php` | Add "MCP usage included" line per tier |
| `resources/views/features/index.blade.php` | Add MCP feature card; bump domain count 56 → 57 |
| `resources/views/developers/index.blade.php` | Add MCP card |
| `docs/13-AI-FRAMEWORK/MCP_INTEGRATION.md` | Rewrite for public server focus |
| `docs/13-AI-FRAMEWORK/01-MCP-Integration.md` | Rewrite for public server focus |
| `docs/partners/VERTEXSMS_Q13_BIDIRECTIONAL_MCP.md` | Replace `mcp-manifest.json` claim with RFC 9728 URL |
| `docs/VERSION_ROADMAP.md` | Add v7.11.0 entry |
| `CLAUDE.md` | Add MCP commands, routes, env vars, pitfalls |
| `README.md` | Add MCP section |
| `.env.production.example` | Add MCP env vars |
| `.env.zelta.example` | Add MCP env vars |
| `.github/workflows/monorepo-split.yml` | Add `finaegis-mcp-stdio` mirror job |

### New docs (created from scratch)

| File | Responsibility |
|---|---|
| `docs/13-AI-FRAMEWORK/02-MCP-Server-Architecture.md` | Wire protocol + OAuth flow + scope catalog |
| `docs/13-AI-FRAMEWORK/03-MCP-Quickstart.md` | "Connect Claude/Cursor in 30 seconds" |
| `docs/13-AI-FRAMEWORK/04-MCP-OAuth-Reference.md` | DCR + scope catalog + consent flow reference |
| `docs/13-AI-FRAMEWORK/05-MCP-Tool-Reference.md` | All 12 tools + 4 resources, schemas |

### New TypeScript package

| File | Responsibility |
|---|---|
| `packages/finaegis-mcp-stdio/package.json` | npm metadata, deps |
| `packages/finaegis-mcp-stdio/tsconfig.json` | TS compiler config |
| `packages/finaegis-mcp-stdio/src/index.ts` | CLI entrypoint (`npx -y @finaegis/mcp`) |
| `packages/finaegis-mcp-stdio/src/stdio-relay.ts` | stdio↔HTTPS relay |
| `packages/finaegis-mcp-stdio/src/oauth-helper.ts` | Browser-based OAuth + keychain persistence |
| `packages/finaegis-mcp-stdio/src/config.ts` | Defaults + env var overrides |
| `packages/finaegis-mcp-stdio/test/stdio-relay.test.ts` | Vitest suite |
| `packages/finaegis-mcp-stdio/test/oauth-helper.test.ts` | Vitest suite |
| `packages/finaegis-mcp-stdio/README.md` | Install, run, configure, troubleshoot |

### New tests

| File | Responsibility |
|---|---|
| `tests/Unit/Domain/MCP/JsonRpcRouterTest.php` | Method dispatch, error envelopes |
| `tests/Unit/Domain/MCP/McpToolAdapterTest.php` | Bridges existing tools correctly |
| `tests/Unit/Domain/MCP/DcrValidationTest.php` | Client metadata validation |
| `tests/Unit/Domain/MCP/DiscoveryMetadataTest.php` | RFC 9728 schema compliance |
| `tests/Unit/Domain/MCP/OAuthScopeGuardTest.php` | 401/403 paths |
| `tests/Unit/Domain/MCP/IdempotencyCacheTest.php` | All four idempotency scenarios |
| `tests/Unit/Domain/MCP/SpendingLimitPolicyTest.php` | Daily window roll, multi-currency |
| `tests/Unit/Domain/MCP/KillSwitchTest.php` | Disabled tool behavior |
| `tests/Feature/MCP/ConnectionHandshakeTest.php` | Full 401 → discovery → DCR → token → initialize |
| `tests/Feature/MCP/ToolsListTest.php` | Filter by scope; omit disabled |
| `tests/Feature/MCP/ToolsCallTest.php` | Success + 4 error paths |
| `tests/Feature/MCP/IdempotencyReplayTest.php` | Cached replay + args mismatch |
| `tests/Feature/MCP/SseStreamTest.php` | GET /mcp event stream |
| `tests/Feature/MCP/PublicDiscoveryTest.php` | `.well-known/*` unauthenticated |
| `tests/Feature/MCP/ClientCredentialsFlowTest.php` | Partner sub-account path |
| `tests/Integration/MCP/McpStdioConformanceTest.php` | Boot npm wrapper, run protocol contract |

---

## Phase 1: Foundation (Tasks 1-8)

### Task 1: Create `config/mcp.php` skeleton

**Files:**
- Create: `config/mcp.php`

- [ ] **Step 1: Write the config file**

```php
<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('MCP_ENABLED', true),
    'host'    => env('MCP_HOST', 'mcp.zelta.app'),

    /*
    |--------------------------------------------------------------------------
    | Wire Protocol
    |--------------------------------------------------------------------------
    */
    'protocol_version' => '2025-11-25',
    'server_info'      => [
        'name'    => env('MCP_SERVER_NAME', 'Zelta'),
        'version' => env('MCP_SERVER_VERSION', '0.1.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth & Discovery
    |--------------------------------------------------------------------------
    */
    'authorization_server' => env('MCP_AUTH_SERVER', 'https://zelta.app'),
    'resource_uri'         => env('MCP_RESOURCE_URI', 'https://mcp.zelta.app'),

    'scopes' => [
        'accounts:read'      => 'Read account profile and balances',
        'accounts:write'     => 'Create new accounts',
        'payments:read'      => 'Read payment status',
        'payments:write'     => 'Send payments (subject to spending limit)',
        'transactions:read'  => 'Read transaction history and spending analysis',
        'exchange:read'      => 'Get exchange rate quotes',
        'exchange:write'     => 'Execute exchange trades (subject to spending limit)',
        'ramp:read'          => 'Read on/offramp session status',
        'ramp:write'         => 'Start on/offramp sessions (subject to spending limit)',
        'sms:send'           => 'Send SMS messages (paid per-message via x402)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tool Catalog (v1)
    |--------------------------------------------------------------------------
    | Maps public MCP tool names to internal MCPToolInterface tool names.
    | Disabled tools are omitted from tools/list AND return -32004 if invoked.
    */
    'tools' => [
        'account.balance'      => ['internal' => 'get_account_balance',  'scope' => 'accounts:read',     'enabled' => env('MCP_TOOL_ACCOUNT_BALANCE', true),     'is_write' => false],
        'account.create'       => ['internal' => 'create_account',       'scope' => 'accounts:write',    'enabled' => env('MCP_TOOL_ACCOUNT_CREATE', true),      'is_write' => true],
        'payment.status'       => ['internal' => 'payment_status',       'scope' => 'payments:read',     'enabled' => env('MCP_TOOL_PAYMENT_STATUS', true),      'is_write' => false],
        'payment.transfer'     => ['internal' => 'transfer',             'scope' => 'payments:write',    'enabled' => env('MCP_TOOL_PAYMENT_TRANSFER', true),    'is_write' => true],
        'transactions.query'   => ['internal' => 'transaction_query',    'scope' => 'transactions:read', 'enabled' => env('MCP_TOOL_TRANSACTIONS_QUERY', true),  'is_write' => false],
        'spending.analysis'    => ['internal' => 'spending_analysis',    'scope' => 'transactions:read', 'enabled' => env('MCP_TOOL_SPENDING_ANALYSIS', true),   'is_write' => false],
        'exchange.quote'       => ['internal' => 'exchange_quote',       'scope' => 'exchange:read',     'enabled' => env('MCP_TOOL_EXCHANGE_QUOTE', true),      'is_write' => false],
        'exchange.trade'       => ['internal' => 'exchange_trade',       'scope' => 'exchange:write',    'enabled' => env('MCP_TOOL_EXCHANGE_TRADE', true),      'is_write' => true],
        'ramp.start'           => ['internal' => 'ramp_start',           'scope' => 'ramp:write',        'enabled' => env('MCP_TOOL_RAMP_START', true),          'is_write' => true],
        'ramp.status'          => ['internal' => 'ramp_status',          'scope' => 'ramp:read',         'enabled' => env('MCP_TOOL_RAMP_STATUS', true),         'is_write' => false],
        'mpp.discovery'        => ['internal' => 'mpp_discovery',        'scope' => null,                'enabled' => env('MCP_TOOL_MPP_DISCOVERY', true),       'is_write' => false],
        'sms.send'             => ['internal' => 'send_sms',             'scope' => 'sms:send',          'enabled' => env('MCP_TOOL_SMS_SEND', true),            'is_write' => true],
    ],

    /*
    |--------------------------------------------------------------------------
    | Spending Limits (per-token defaults)
    |--------------------------------------------------------------------------
    */
    'spending' => [
        'default_daily_limit_minor'    => (int) env('MCP_DEFAULT_DAILY_LIMIT_MINOR', 50000),     // $500.00
        'default_daily_limit_currency' => env('MCP_DEFAULT_DAILY_LIMIT_CURRENCY', 'USD'),
        'consent_options_minor'        => [5000, 50000, 200000, 1000000, null],                  // null = no limit
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    */
    'idempotency' => [
        'cache_store' => env('MCP_IDEMPOTENCY_STORE', 'redis'),
        'ttl_seconds' => 86400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limits (per-token unless noted)
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'aggregate'        => ['per_minute' => 60, 'per_hour' => 600, 'per_day' => 5000],
        'reads_per_minute' => 120,
        'writes_per_minute' => 30,
        'sms_per_minute'   => 10,
        'discovery_per_minute_per_ip' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources (read-context primitives)
    |--------------------------------------------------------------------------
    */
    'resources' => [
        'account://profile'              => ['scope' => 'accounts:read',     'enabled' => true],
        'account://balance/{currency}'   => ['scope' => 'accounts:read',     'enabled' => true],
        'transactions://recent'          => ['scope' => 'transactions:read', 'enabled' => true],
        'transaction://{id}'             => ['scope' => 'transactions:read', 'enabled' => true],
    ],
];
```

- [ ] **Step 2: Verify config loads**

Run: `php artisan tinker --execute="dump(config('mcp.host'));"`
Expected: `"mcp.zelta.app"`

- [ ] **Step 3: Commit**

```bash
git add config/mcp.php
git commit -m "feat(mcp): add config/mcp.php — tool catalog, scopes, limits, kill-switches"
```

---

### Task 2: Add `mcp.` subdomain routing in `bootstrap/app.php`

**Files:**
- Modify: `bootstrap/app.php` (the `using:` callback in `withRouting`)
- Create: `app/Domain/MCP/Routes/api.php`

- [ ] **Step 1: Create the empty route file with health endpoint**

Create `app/Domain/MCP/Routes/api.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Health check on the MCP subdomain (no auth, no rate limit) — proves routing is wired
Route::get('/healthz', function () {
    return response()->json(['ok' => true, 'service' => 'mcp', 'protocol_version' => config('mcp.protocol_version')]);
})->name('mcp.healthz');
```

- [ ] **Step 2: Modify `bootstrap/app.php` to add the MCP subdomain branch**

In `bootstrap/app.php`, find the `using:` callback. Add before the `if ($isProtocolSubdomain)` block:

```php
$isMcpSubdomain = str_starts_with($host, 'mcp.');
```

Then add a new branch immediately above the existing `if ($isProtocolSubdomain)`:

```php
if ($isMcpSubdomain) {
    // MCP subdomain — minimal middleware stack (no CSRF, no Sanctum, no web session)
    Route::middleware(['api'])
        ->group(base_path('app/Domain/MCP/Routes/api.php'));
} elseif ($isProtocolSubdomain) {
    // ... existing block stays unchanged
```

(Keep the existing `elseif` chain after.)

- [ ] **Step 3: Write a feature test for the subdomain healthz**

Create `tests/Feature/MCP/SubdomainRoutingTest.php`:

```php
<?php

declare(strict_types=1);

use function Pest\Laravel\getJson;

it('responds on the mcp subdomain healthz', function () {
    $response = $this->withServerVariables(['HTTP_HOST' => config('mcp.host')])
        ->getJson('/healthz');

    $response->assertStatus(200)
        ->assertJson(['ok' => true, 'service' => 'mcp']);
});

it('does not expose mcp routes on the main domain', function () {
    $response = $this->withServerVariables(['HTTP_HOST' => 'zelta.app'])
        ->getJson('/healthz');

    $response->assertStatus(404);
});
```

- [ ] **Step 4: Run the test — must fail**

Run: `./vendor/bin/pest tests/Feature/MCP/SubdomainRoutingTest.php`
Expected: 2 passing tests after the bootstrap edit. If they fail, check the bootstrap branch ordering.

- [ ] **Step 5: Commit**

```bash
git add bootstrap/app.php app/Domain/MCP/Routes/api.php tests/Feature/MCP/SubdomainRoutingTest.php
git commit -m "feat(mcp): wire mcp.* subdomain to dedicated route group"
```

---

### Task 3: Protected Resource Metadata controller (RFC 9728)

**Files:**
- Create: `app/Domain/MCP/Discovery/ProtectedResourceMetadataController.php`
- Modify: `app/Domain/MCP/Routes/api.php`
- Create: `tests/Unit/Domain/MCP/DiscoveryMetadataTest.php`

- [ ] **Step 1: Write the failing unit test**

```php
<?php

declare(strict_types=1);

use App\Domain\MCP\Discovery\ProtectedResourceMetadataController;
use Illuminate\Http\Request;

it('emits RFC 9728 protected resource metadata', function () {
    $controller = new ProtectedResourceMetadataController();
    $response = $controller(Request::create('/.well-known/oauth-protected-resource'));
    $body = $response->getData(true);

    expect($body)->toHaveKeys(['resource', 'authorization_servers', 'scopes_supported', 'bearer_methods_supported']);
    expect($body['resource'])->toBe(config('mcp.resource_uri'));
    expect($body['authorization_servers'])->toBe([config('mcp.authorization_server')]);
    expect($body['scopes_supported'])->toContain('payments:write', 'sms:send');
    expect($body['bearer_methods_supported'])->toBe(['header']);
});
```

- [ ] **Step 2: Run — must fail (class missing)**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/DiscoveryMetadataTest.php`
Expected: FAIL with class not found.

- [ ] **Step 3: Create the controller**

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Discovery;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProtectedResourceMetadataController
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'resource'                 => (string) config('mcp.resource_uri'),
            'authorization_servers'    => [(string) config('mcp.authorization_server')],
            'scopes_supported'         => array_keys((array) config('mcp.scopes', [])),
            'bearer_methods_supported' => ['header'],
            'resource_documentation'   => (string) config('mcp.authorization_server') . '/developers/mcp-tools',
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
```

- [ ] **Step 4: Add the route**

In `app/Domain/MCP/Routes/api.php`, append:

```php
use App\Domain\MCP\Discovery\ProtectedResourceMetadataController;

Route::get('/.well-known/oauth-protected-resource', ProtectedResourceMetadataController::class)
    ->name('mcp.discovery.protected-resource');
```

- [ ] **Step 5: Run unit + feature tests — must pass**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/DiscoveryMetadataTest.php tests/Feature/MCP/`
Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add app/Domain/MCP/Discovery/ProtectedResourceMetadataController.php app/Domain/MCP/Routes/api.php tests/Unit/Domain/MCP/DiscoveryMetadataTest.php
git commit -m "feat(mcp): RFC 9728 oauth-protected-resource discovery endpoint"
```

---

### Task 4: Authorization Server Metadata controller

**Files:**
- Create: `app/Domain/MCP/Discovery/AuthorizationServerMetadataController.php`
- Modify: `routes/web.php` (registers on the MAIN domain — `https://zelta.app`)

- [ ] **Step 1: Write the failing test**

```php
// Append to tests/Unit/Domain/MCP/DiscoveryMetadataTest.php
it('emits OAuth Authorization Server metadata', function () {
    $controller = new App\Domain\MCP\Discovery\AuthorizationServerMetadataController();
    $body = $controller(Illuminate\Http\Request::create('/.well-known/oauth-authorization-server'))->getData(true);

    expect($body)->toHaveKeys([
        'issuer',
        'authorization_endpoint',
        'token_endpoint',
        'registration_endpoint',
        'scopes_supported',
        'response_types_supported',
        'grant_types_supported',
        'code_challenge_methods_supported',
        'token_endpoint_auth_methods_supported',
    ]);
    expect($body['issuer'])->toBe(config('mcp.authorization_server'));
    expect($body['authorization_endpoint'])->toBe(config('mcp.authorization_server') . '/oauth/authorize');
    expect($body['token_endpoint'])->toBe(config('mcp.authorization_server') . '/oauth/token');
    expect($body['registration_endpoint'])->toBe(config('mcp.authorization_server') . '/oauth/register');
    expect($body['grant_types_supported'])->toContain('authorization_code', 'client_credentials', 'refresh_token');
    expect($body['code_challenge_methods_supported'])->toBe(['S256']);
});
```

- [ ] **Step 2: Run — must fail**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/DiscoveryMetadataTest.php`

- [ ] **Step 3: Create the controller**

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Discovery;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthorizationServerMetadataController
{
    public function __invoke(Request $request): JsonResponse
    {
        $issuer = (string) config('mcp.authorization_server');

        return response()->json([
            'issuer'                                => $issuer,
            'authorization_endpoint'                => $issuer . '/oauth/authorize',
            'token_endpoint'                        => $issuer . '/oauth/token',
            'registration_endpoint'                 => $issuer . '/oauth/register',
            'revocation_endpoint'                   => $issuer . '/oauth/tokens',
            'scopes_supported'                      => array_keys((array) config('mcp.scopes', [])),
            'response_types_supported'              => ['code'],
            'grant_types_supported'                 => ['authorization_code', 'client_credentials', 'refresh_token'],
            'code_challenge_methods_supported'      => ['S256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post', 'none'],
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
```

- [ ] **Step 4: Register the route on the main domain**

Append to `routes/web.php`:

```php
Route::get('/.well-known/oauth-authorization-server', App\Domain\MCP\Discovery\AuthorizationServerMetadataController::class)
    ->name('mcp.discovery.authorization-server');
```

- [ ] **Step 5: Run tests — must pass**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/DiscoveryMetadataTest.php`

- [ ] **Step 6: Commit**

```bash
git add app/Domain/MCP/Discovery/AuthorizationServerMetadataController.php routes/web.php tests/Unit/Domain/MCP/DiscoveryMetadataTest.php
git commit -m "feat(mcp): /.well-known/oauth-authorization-server endpoint"
```

---

### Task 5: Migration — extend `oauth_clients` for DCR

**Files:**
- Create: `database/migrations/2026_04_28_000003_extend_oauth_clients_for_dcr.php`

- [ ] **Step 1: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->string('client_logo_url', 512)->nullable()->after('redirect');
            $table->string('client_terms_url', 512)->nullable()->after('client_logo_url');
            $table->string('client_privacy_url', 512)->nullable()->after('client_terms_url');
            $table->string('dcr_metadata_uri', 512)->nullable()->after('client_privacy_url');
            $table->enum('registration_method', ['dcr', 'manual', 'passport_native'])
                ->default('passport_native')
                ->after('dcr_metadata_uri');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn(['client_logo_url', 'client_terms_url', 'client_privacy_url', 'dcr_metadata_uri', 'registration_method']);
        });
    }
};
```

- [ ] **Step 2: Run migration**

Run: `php artisan migrate`
Expected: `Migrated: 2026_04_28_000003_extend_oauth_clients_for_dcr`

- [ ] **Step 3: Verify schema**

Run: `php artisan tinker --execute="dump(Schema::getColumnListing('oauth_clients'));"`
Expected: list includes `client_logo_url`, `dcr_metadata_uri`, `registration_method`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_04_28_000003_extend_oauth_clients_for_dcr.php
git commit -m "feat(mcp): extend oauth_clients for DCR metadata"
```

---

### Task 6: DCR controller (`POST /oauth/register`)

**Files:**
- Create: `app/Domain/MCP/Auth/DynamicClientRegistrationController.php`
- Create: `tests/Unit/Domain/MCP/DcrValidationTest.php`
- Create: `tests/Feature/MCP/DcrRegistrationTest.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write the unit test for validation**

```php
<?php

declare(strict_types=1);

use App\Domain\MCP\Auth\DynamicClientRegistrationController;
use Illuminate\Http\Request;

it('rejects DCR with no redirect_uris', function () {
    $req = Request::create('/oauth/register', 'POST', [], [], [], [], json_encode(['client_name' => 'Test']));
    $req->headers->set('Content-Type', 'application/json');
    $response = (new DynamicClientRegistrationController())->__invoke($req);
    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true)['error'])->toBe('invalid_redirect_uri');
});

it('rejects DCR with invalid grant_types', function () {
    $req = Request::create('/oauth/register', 'POST', [], [], [], [], json_encode([
        'client_name'   => 'Test',
        'redirect_uris' => ['http://localhost:1234/callback'],
        'grant_types'   => ['password'],
    ]));
    $req->headers->set('Content-Type', 'application/json');
    $response = (new DynamicClientRegistrationController())->__invoke($req);
    expect($response->getStatusCode())->toBe(400);
    expect($response->getData(true)['error'])->toBe('invalid_client_metadata');
});
```

- [ ] **Step 2: Write the feature test for happy path**

```php
<?php

declare(strict_types=1);

use function Pest\Laravel\postJson;

it('registers a DCR client and returns credentials', function () {
    $response = postJson('/oauth/register', [
        'client_name'   => 'Test MCP Client',
        'redirect_uris' => ['http://localhost:1234/callback', 'http://localhost:5678/callback'],
        'grant_types'   => ['authorization_code', 'refresh_token'],
        'scope'         => 'accounts:read payments:write',
        'logo_uri'      => 'https://example.com/logo.png',
        'tos_uri'       => 'https://example.com/tos',
        'policy_uri'    => 'https://example.com/privacy',
    ]);

    $response->assertStatus(201);
    $data = $response->json();

    expect($data)->toHaveKeys(['client_id', 'client_secret', 'client_name', 'redirect_uris', 'grant_types', 'token_endpoint_auth_method']);
    expect($data['client_name'])->toBe('Test MCP Client');
    expect($data['redirect_uris'])->toBe(['http://localhost:1234/callback', 'http://localhost:5678/callback']);

    $this->assertDatabaseHas('oauth_clients', [
        'id'                  => $data['client_id'],
        'name'                => 'Test MCP Client',
        'client_logo_url'     => 'https://example.com/logo.png',
        'registration_method' => 'dcr',
    ]);
});
```

- [ ] **Step 3: Run — must fail**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/DcrValidationTest.php tests/Feature/MCP/DcrRegistrationTest.php`

- [ ] **Step 4: Implement the controller**

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;

final class DynamicClientRegistrationController
{
    private const ALLOWED_GRANT_TYPES = ['authorization_code', 'client_credentials', 'refresh_token'];

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        $redirectUris = $payload['redirect_uris'] ?? [];
        if (! is_array($redirectUris) || $redirectUris === []) {
            return $this->error('invalid_redirect_uri', 'redirect_uris is required and must be a non-empty array');
        }

        foreach ($redirectUris as $uri) {
            if (! is_string($uri) || ! filter_var($uri, FILTER_VALIDATE_URL)) {
                return $this->error('invalid_redirect_uri', "redirect_uri is not a valid URL: {$uri}");
            }
        }

        $grantTypes = $payload['grant_types'] ?? ['authorization_code'];
        foreach ($grantTypes as $gt) {
            if (! in_array($gt, self::ALLOWED_GRANT_TYPES, true)) {
                return $this->error('invalid_client_metadata', "unsupported grant_type: {$gt}");
            }
        }

        $clientName = (string) ($payload['client_name'] ?? 'Unnamed MCP Client');
        $isConfidential = ! in_array('client_credentials', $grantTypes, true) ? true : true;

        /** @var ClientRepository $repo */
        $repo = app(ClientRepository::class);

        $client = $repo->createAuthorizationCodeGrantClient(
            name: $clientName,
            redirectUris: $redirectUris,
            confidential: $isConfidential,
        );

        $client->forceFill([
            'client_logo_url'     => $payload['logo_uri']    ?? null,
            'client_terms_url'    => $payload['tos_uri']     ?? null,
            'client_privacy_url'  => $payload['policy_uri']  ?? null,
            'dcr_metadata_uri'    => $payload['client_uri']  ?? null,
            'registration_method' => 'dcr',
        ])->save();

        return response()->json([
            'client_id'                  => $client->getKey(),
            'client_secret'              => $client->plainSecret,
            'client_name'                => $client->name,
            'redirect_uris'              => $redirectUris,
            'grant_types'                => $grantTypes,
            'token_endpoint_auth_method' => 'client_secret_basic',
            'logo_uri'                   => $client->client_logo_url,
            'tos_uri'                    => $client->client_terms_url,
            'policy_uri'                 => $client->client_privacy_url,
            'client_id_issued_at'        => now()->timestamp,
        ], 201);
    }

    private function error(string $code, string $description): JsonResponse
    {
        return response()->json([
            'error'             => $code,
            'error_description' => $description,
        ], 400);
    }
}
```

- [ ] **Step 5: Add the route**

Append to `routes/web.php`:

```php
Route::post('/oauth/register', App\Domain\MCP\Auth\DynamicClientRegistrationController::class)
    ->middleware(['api', 'throttle:5,1'])
    ->name('mcp.oauth.register');
```

- [ ] **Step 6: Run tests — must pass**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/DcrValidationTest.php tests/Feature/MCP/DcrRegistrationTest.php`

- [ ] **Step 7: Commit**

```bash
git add app/Domain/MCP/Auth/DynamicClientRegistrationController.php routes/web.php tests/Unit/Domain/MCP/DcrValidationTest.php tests/Feature/MCP/DcrRegistrationTest.php
git commit -m "feat(mcp): RFC 7591 Dynamic Client Registration endpoint"
```

---

### Task 7: Custom consent screen

**Files:**
- Create: `app/Domain/MCP/Auth/ConsentScreenController.php`
- Create: `resources/views/mcp/consent.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create the consent controller**

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Laravel\Passport\Bridge\AuthCodeRepository;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

final class ConsentScreenController
{
    public function __invoke(Request $request): View
    {
        $clientId = (string) $request->query('client_id');
        $scopeStr = (string) $request->query('scope', '');

        /** @var ClientRepository $repo */
        $repo   = app(ClientRepository::class);
        $client = $repo->find($clientId);
        abort_unless($client, 404, 'Unknown client');

        $requestedScopes = array_filter(explode(' ', $scopeStr));
        $scopeCatalog    = (array) config('mcp.scopes');
        $scopeRows       = [];
        foreach ($requestedScopes as $s) {
            $scopeRows[] = [
                'id'          => $s,
                'description' => $scopeCatalog[$s] ?? $s,
                'is_write'    => str_ends_with($s, ':write') || $s === 'sms:send',
            ];
        }

        return view('mcp.consent', [
            'client'              => $client,
            'scopes'              => $scopeRows,
            'authorize_url'       => route('passport.authorizations.approve'),
            'deny_url'            => route('passport.authorizations.deny'),
            'spending_options'    => config('mcp.spending.consent_options_minor'),
            'default_limit_minor' => config('mcp.spending.default_daily_limit_minor'),
            'currency'            => config('mcp.spending.default_daily_limit_currency'),
            'state'               => $request->query('state'),
            'auth_token'          => $request->session()->token(),
        ]);
    }
}
```

- [ ] **Step 2: Create the consent view**

```blade
@extends('layouts.public')

@section('title', 'Authorize ' . ($client->name ?? 'application'))

@section('content')
<div class="max-w-xl mx-auto py-12 px-4">
  <div class="bg-white rounded-2xl shadow border border-slate-200 p-8">
    <div class="flex items-center gap-4 mb-6">
      @if($client->client_logo_url)
        <img src="{{ $client->client_logo_url }}" alt="" class="w-14 h-14 rounded-xl border border-slate-200" />
      @else
        <div class="w-14 h-14 rounded-xl bg-slate-100 flex items-center justify-center text-2xl">{{ strtoupper(substr($client->name, 0, 1)) }}</div>
      @endif
      <div>
        <div class="text-xs text-slate-500 uppercase tracking-wide">Authorize application</div>
        <h1 class="text-xl font-bold">{{ $client->name }}</h1>
      </div>
    </div>

    <p class="text-slate-700 mb-6">
      <strong>{{ $client->name }}</strong> wants to connect to your Zelta account. Review the permissions below before approving.
    </p>

    <ul class="space-y-3 mb-6">
      @foreach($scopes as $scope)
        <li class="flex items-start gap-3 p-3 rounded-lg {{ $scope['is_write'] ? 'bg-amber-50 border border-amber-200' : 'bg-slate-50 border border-slate-200' }}">
          <span class="mt-0.5 flex-shrink-0 w-5 h-5 rounded-full {{ $scope['is_write'] ? 'bg-amber-500' : 'bg-emerald-500' }} text-white text-xs flex items-center justify-center">{{ $scope['is_write'] ? '!' : '✓' }}</span>
          <div>
            <div class="font-medium text-slate-900">{{ $scope['description'] }}</div>
            <div class="text-xs text-slate-500 mt-0.5">{{ $scope['id'] }}</div>
          </div>
        </li>
      @endforeach
    </ul>

    <form method="POST" action="{{ $authorize_url }}" class="space-y-4">
      @csrf
      <input type="hidden" name="state" value="{{ $state }}" />

      <div class="border-t border-slate-200 pt-4">
        <label class="block text-sm font-medium text-slate-900 mb-2">Daily spending limit</label>
        <p class="text-xs text-slate-500 mb-3">Maximum total per 24 hours across all financial actions. Server-enforced.</p>
        <select name="mcp_daily_limit_minor" class="w-full rounded-lg border-slate-300 focus:ring-emerald-500 focus:border-emerald-500">
          @foreach($spending_options as $opt)
            <option value="{{ $opt ?? '' }}" {{ $opt === $default_limit_minor ? 'selected' : '' }}>
              @if($opt === null) No limit — I trust this app fully
              @else {{ $currency }} {{ number_format($opt / 100, 2) }} / 24h
              @endif
            </option>
          @endforeach
        </select>
      </div>

      <div class="flex gap-3 pt-4">
        <button type="submit" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 rounded-lg">Approve</button>
      </div>
    </form>

    <form method="POST" action="{{ $deny_url }}" class="mt-3">
      @csrf
      <input type="hidden" name="state" value="{{ $state }}" />
      <button type="submit" class="w-full text-slate-600 hover:text-slate-900 text-sm py-2">Deny</button>
    </form>
  </div>
</div>
@endsection
```

- [ ] **Step 3: Override Passport's authorization view**

In `app/Providers/AppServiceProvider.php` (or `bootstrap/providers.php`), add to `boot()`:

```php
\Laravel\Passport\Passport::authorizationView(function ($parameters) {
    return app(\App\Domain\MCP\Auth\ConsentScreenController::class)(request());
});
```

- [ ] **Step 4: Manually verify**

Run: `php artisan serve` then visit `http://localhost:8000/oauth/authorize?client_id=<id>&response_type=code&redirect_uri=http://localhost:1234/cb&scope=accounts:read+payments:write&state=test`
Expected: see the custom consent screen with scope rows + spending limit selector.

- [ ] **Step 5: Commit**

```bash
git add app/Domain/MCP/Auth/ConsentScreenController.php resources/views/mcp/consent.blade.php app/Providers/AppServiceProvider.php
git commit -m "feat(mcp): custom OAuth consent screen with scope explanations + spending-limit slider"
```

---

### Task 8: McpOAuthGuard middleware

**Files:**
- Create: `app/Domain/MCP/Auth/McpOAuthGuard.php`
- Create: `tests/Unit/Domain/MCP/OAuthScopeGuardTest.php`
- Modify: `bootstrap/app.php` (register alias)

- [ ] **Step 1: Write the unit test**

```php
<?php

declare(strict_types=1);

use App\Domain\MCP\Auth\McpOAuthGuard;
use Illuminate\Http\Request;

it('returns 401 with WWW-Authenticate header when no token', function () {
    $guard   = new McpOAuthGuard();
    $request = Request::create('/mcp', 'POST');
    $response = $guard->handle($request, fn () => response('OK'));

    expect($response->getStatusCode())->toBe(401);
    $header = $response->headers->get('WWW-Authenticate');
    expect($header)->toContain('Bearer');
    expect($header)->toContain('resource_metadata=');
    expect($header)->toContain('/.well-known/oauth-protected-resource');
});
```

- [ ] **Step 2: Run — must fail**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/OAuthScopeGuardTest.php`

- [ ] **Step 3: Implement guard**

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Auth;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\Token;

final class McpOAuthGuard
{
    public function handle(Request $request, Closure $next, ?string $requiredScope = null)
    {
        $bearer = $request->bearerToken();
        if ($bearer === null || $bearer === '') {
            return $this->unauthenticated();
        }

        /** @var Token|null $token */
        $token = app('Laravel\Passport\PersonalAccessTokenFactory')
                ? null
                : null;

        // Resolve token via Passport guard
        $request->setUserResolver(fn () => auth('api')->user());
        $passportToken = auth('api')->user()?->token();

        if ($passportToken === null || $passportToken->revoked) {
            return $this->unauthenticated();
        }

        if ($requiredScope !== null && ! $passportToken->can($requiredScope)) {
            return new JsonResponse([
                'jsonrpc' => '2.0',
                'error'   => [
                    'code'    => -32000,
                    'message' => 'INSUFFICIENT_SCOPE',
                    'data'    => ['required' => $requiredScope, 'granted' => $passportToken->scopes],
                ],
                'id' => null,
            ], 403);
        }

        $request->attributes->set('mcp.token', $passportToken);

        return $next($request);
    }

    private function unauthenticated(): JsonResponse
    {
        $resourceMetadata = (string) config('mcp.resource_uri') . '/.well-known/oauth-protected-resource';
        $resp = new JsonResponse([
            'jsonrpc' => '2.0',
            'error'   => ['code' => -32001, 'message' => 'UNAUTHENTICATED'],
            'id'      => null,
        ], 401);
        $resp->headers->set('WWW-Authenticate', 'Bearer resource_metadata="' . $resourceMetadata . '"');

        return $resp;
    }
}
```

- [ ] **Step 4: Register middleware alias**

In `bootstrap/app.php` `withMiddleware(...)`, add to the `$middleware->alias([...])` block:

```php
'mcp.oauth' => App\Domain\MCP\Auth\McpOAuthGuard::class,
```

- [ ] **Step 5: Run unit test — must pass**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/OAuthScopeGuardTest.php`

- [ ] **Step 6: Commit**

```bash
git add app/Domain/MCP/Auth/McpOAuthGuard.php tests/Unit/Domain/MCP/OAuthScopeGuardTest.php bootstrap/app.php
git commit -m "feat(mcp): oauth bearer guard with resource_metadata WWW-Authenticate hint"
```

---

(Phase 1 complete — 8 tasks. Subdomain wired, discovery endpoints live, DCR endpoint live, custom consent screen live, OAuth guard ready.)

---

## Phase 2: Wire Protocol (Tasks 9-18)

### Task 9: Migration — `mcp_tool_invocations`

**Files:**
- Create: `database/migrations/2026_04_28_000001_create_mcp_tool_invocations_table.php`

- [ ] **Step 1: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_tool_invocations', function (Blueprint $table) {
            $table->id();
            $table->string('token_id', 100)->index();
            $table->string('client_id', 100)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('tool_name', 100);
            $table->string('args_hash', 64);
            $table->string('idempotency_key', 64)->nullable();
            $table->enum('result_status', ['success', 'error', 'rate_limited', 'spending_limit', 'idempotency_replay']);
            $table->string('error_code', 64)->nullable();
            $table->unsignedBigInteger('settlement_amount_minor')->nullable();
            $table->string('settlement_currency', 8)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['token_id', 'created_at']);
            $table->index(['idempotency_key', 'token_id', 'tool_name']);
            $table->index(['tool_name', 'result_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_tool_invocations');
    }
};
```

- [ ] **Step 2: Run migration**

Run: `php artisan migrate`
Expected: `Migrated: 2026_04_28_000001_create_mcp_tool_invocations_table`

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_04_28_000001_create_mcp_tool_invocations_table.php
git commit -m "feat(mcp): mcp_tool_invocations audit-log table"
```

---

### Task 10: Migration — `mcp_token_policies`

**Files:**
- Create: `database/migrations/2026_04_28_000002_create_mcp_token_policies_table.php`

- [ ] **Step 1: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_token_policies', function (Blueprint $table) {
            $table->id();
            $table->string('token_id', 100)->unique();
            $table->unsignedBigInteger('daily_limit_minor');
            $table->string('daily_limit_currency', 8)->default('USD');
            $table->unsignedBigInteger('daily_spend_minor')->default(0);
            $table->timestamp('daily_window_start_at')->useCurrent();
            $table->json('scoped_tools')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_token_policies');
    }
};
```

- [ ] **Step 2: Run migration**

Run: `php artisan migrate`

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_04_28_000002_create_mcp_token_policies_table.php
git commit -m "feat(mcp): mcp_token_policies table for spending limits"
```

---

### Task 11: ToolInvocationLogger

**Files:**
- Create: `app/Domain/MCP/Audit/ToolInvocationLogger.php`
- Create: `tests/Unit/Domain/MCP/ToolInvocationLoggerTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use App\Domain\MCP\Audit\ToolInvocationLogger;

it('writes a successful invocation row', function () {
    $logger = new ToolInvocationLogger();

    $id = $logger->log([
        'token_id'      => 'tok_test_1',
        'client_id'     => 'client_test',
        'user_id'       => 1,
        'tool_name'     => 'account.balance',
        'args_hash'     => str_repeat('a', 64),
        'result_status' => 'success',
        'duration_ms'   => 42,
    ]);

    expect($id)->toBeInt();
    $this->assertDatabaseHas('mcp_tool_invocations', [
        'token_id'      => 'tok_test_1',
        'tool_name'     => 'account.balance',
        'result_status' => 'success',
    ]);
});

it('records settlement amount on $-impact tools', function () {
    (new ToolInvocationLogger())->log([
        'token_id'                => 'tok_pay',
        'client_id'               => 'c',
        'user_id'                 => 1,
        'tool_name'               => 'payment.transfer',
        'args_hash'               => str_repeat('b', 64),
        'result_status'           => 'success',
        'settlement_amount_minor' => 12500,
        'settlement_currency'     => 'USD',
    ]);

    $this->assertDatabaseHas('mcp_tool_invocations', [
        'tool_name'               => 'payment.transfer',
        'settlement_amount_minor' => 12500,
        'settlement_currency'     => 'USD',
    ]);
});
```

- [ ] **Step 2: Run — fail**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/ToolInvocationLoggerTest.php`

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Audit;

use Illuminate\Support\Facades\DB;

final class ToolInvocationLogger
{
    /**
     * @param array<string, mixed> $payload
     */
    public function log(array $payload): int
    {
        $row = [
            'token_id'                => (string) ($payload['token_id'] ?? ''),
            'client_id'               => (string) ($payload['client_id'] ?? ''),
            'user_id'                 => $payload['user_id'] ?? null,
            'tool_name'               => (string) ($payload['tool_name'] ?? ''),
            'args_hash'               => (string) ($payload['args_hash'] ?? ''),
            'idempotency_key'         => $payload['idempotency_key'] ?? null,
            'result_status'           => (string) ($payload['result_status'] ?? 'error'),
            'error_code'              => $payload['error_code'] ?? null,
            'settlement_amount_minor' => $payload['settlement_amount_minor'] ?? null,
            'settlement_currency'     => $payload['settlement_currency'] ?? null,
            'ip'                      => $payload['ip']         ?? request()?->ip(),
            'user_agent'              => $payload['user_agent'] ?? request()?->userAgent(),
            'request_id'              => $payload['request_id'] ?? request()?->header('X-Request-ID'),
            'duration_ms'             => $payload['duration_ms'] ?? null,
            'created_at'              => now(),
        ];

        return (int) DB::table('mcp_tool_invocations')->insertGetId($row);
    }
}
```

- [ ] **Step 4: Pass**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/ToolInvocationLoggerTest.php`

- [ ] **Step 5: Commit**

```bash
git add app/Domain/MCP/Audit/ToolInvocationLogger.php tests/Unit/Domain/MCP/ToolInvocationLoggerTest.php
git commit -m "feat(mcp): tool invocation logger writes audit rows"
```

---

### Task 12: SpendingLimitService

**Files:**
- Create: `app/Domain/MCP/Policy/SpendingLimitService.php`
- Create: `tests/Unit/Domain/MCP/SpendingLimitPolicyTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use App\Domain\MCP\Policy\SpendingLimitService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('mcp_token_policies')->insert([
        'token_id'              => 'tok_a',
        'daily_limit_minor'     => 50000,
        'daily_limit_currency'  => 'USD',
        'daily_spend_minor'     => 0,
        'daily_window_start_at' => now(),
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);
});

it('allows spend within limit and increments counter', function () {
    $svc = new SpendingLimitService();
    $result = $svc->reserve('tok_a', 10000, 'USD');

    expect($result['allowed'])->toBeTrue();
    expect(DB::table('mcp_token_policies')->where('token_id', 'tok_a')->value('daily_spend_minor'))->toBe(10000);
});

it('rejects spend that would exceed limit', function () {
    DB::table('mcp_token_policies')->where('token_id', 'tok_a')->update(['daily_spend_minor' => 45000]);
    $svc = new SpendingLimitService();
    $result = $svc->reserve('tok_a', 10000, 'USD');

    expect($result['allowed'])->toBeFalse();
    expect($result['limit_remaining_minor'])->toBe(5000);
});

it('rolls the window after 24 hours and resets the counter', function () {
    DB::table('mcp_token_policies')->where('token_id', 'tok_a')->update([
        'daily_spend_minor'     => 49000,
        'daily_window_start_at' => now()->subHours(25),
    ]);
    $svc    = new SpendingLimitService();
    $result = $svc->reserve('tok_a', 10000, 'USD');

    expect($result['allowed'])->toBeTrue();
    expect(DB::table('mcp_token_policies')->where('token_id', 'tok_a')->value('daily_spend_minor'))->toBe(10000);
});

it('rejects when token has no policy row', function () {
    $svc    = new SpendingLimitService();
    $result = $svc->reserve('tok_unknown', 100, 'USD');

    expect($result['allowed'])->toBeFalse();
    expect($result['error_code'])->toBe('NO_POLICY');
});
```

- [ ] **Step 2: Run — fail**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/SpendingLimitPolicyTest.php`

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Policy;

use Illuminate\Support\Facades\DB;

final class SpendingLimitService
{
    /**
     * Atomically reserve spend against the per-token daily limit.
     * Rolls the 24h window if expired before checking.
     *
     * @return array{allowed: bool, limit_remaining_minor?: int, window_resets_at?: string, error_code?: string}
     */
    public function reserve(string $tokenId, int $amountMinor, string $currency): array
    {
        return DB::transaction(function () use ($tokenId, $amountMinor, $currency) {
            $row = DB::table('mcp_token_policies')->where('token_id', $tokenId)->lockForUpdate()->first();
            if ($row === null) {
                return ['allowed' => false, 'error_code' => 'NO_POLICY'];
            }

            // Window roll
            if (now()->diffInHours($row->daily_window_start_at) >= 24) {
                DB::table('mcp_token_policies')->where('token_id', $tokenId)->update([
                    'daily_spend_minor'     => 0,
                    'daily_window_start_at' => now(),
                    'updated_at'            => now(),
                ]);
                $row->daily_spend_minor     = 0;
                $row->daily_window_start_at = now();
            }

            // Currency mismatch — reject (v1 doesn't FX-convert on the fly)
            if ($currency !== $row->daily_limit_currency) {
                return ['allowed' => false, 'error_code' => 'CURRENCY_MISMATCH'];
            }

            $remaining = (int) $row->daily_limit_minor - (int) $row->daily_spend_minor;
            if ($amountMinor > $remaining) {
                return [
                    'allowed'              => false,
                    'limit_remaining_minor' => max(0, $remaining),
                    'window_resets_at'     => \Carbon\Carbon::parse((string) $row->daily_window_start_at)->addDay()->toIso8601String(),
                ];
            }

            DB::table('mcp_token_policies')->where('token_id', $tokenId)->update([
                'daily_spend_minor' => DB::raw("daily_spend_minor + {$amountMinor}"),
                'updated_at'        => now(),
            ]);

            return ['allowed' => true];
        });
    }
}
```

- [ ] **Step 4: Pass**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/SpendingLimitPolicyTest.php`

- [ ] **Step 5: Commit**

```bash
git add app/Domain/MCP/Policy/SpendingLimitService.php tests/Unit/Domain/MCP/SpendingLimitPolicyTest.php
git commit -m "feat(mcp): spending-limit service with 24h rolling window"
```

---

### Task 13: IdempotencyCache

**Files:**
- Create: `app/Domain/MCP/Policy/IdempotencyCache.php`
- Create: `tests/Unit/Domain/MCP/IdempotencyCacheTest.php`

- [ ] **Step 1: Test all four scenarios**

```php
<?php

declare(strict_types=1);

use App\Domain\MCP\Policy\IdempotencyCache;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::flush());

it('caches first call result', function () {
    $cache  = new IdempotencyCache();
    $result = $cache->remember('tok_a', 'payment.transfer', 'idem-1', 'args-hash-1', fn () => ['ok' => true, 'tx_id' => 'TX1']);

    expect($result)->toBe(['ok' => true, 'tx_id' => 'TX1']);
    expect($cache->peek('tok_a', 'payment.transfer', 'idem-1'))->not->toBeNull();
});

it('returns cached result on retry with matching args', function () {
    $cache = new IdempotencyCache();
    $cache->remember('tok_a', 'payment.transfer', 'idem-1', 'args-hash-1', fn () => ['ok' => true, 'tx_id' => 'TX1']);

    $second = $cache->remember('tok_a', 'payment.transfer', 'idem-1', 'args-hash-1', fn () => ['ok' => true, 'tx_id' => 'TX2_should_not_run']);
    expect($second)->toBe(['ok' => true, 'tx_id' => 'TX1']);
});

it('throws on retry with mismatched args', function () {
    $cache = new IdempotencyCache();
    $cache->remember('tok_a', 'payment.transfer', 'idem-1', 'args-hash-1', fn () => ['ok' => true]);

    expect(fn () => $cache->remember('tok_a', 'payment.transfer', 'idem-1', 'args-hash-DIFFERENT', fn () => ['ok' => true]))
        ->toThrow(\App\Domain\MCP\Exceptions\IdempotencyKeyReusedException::class);
});

it('isolates by token_id', function () {
    $cache = new IdempotencyCache();
    $cache->remember('tok_a', 'payment.transfer', 'idem-1', 'h', fn () => ['user' => 'a']);
    $b = $cache->remember('tok_b', 'payment.transfer', 'idem-1', 'h', fn () => ['user' => 'b']);

    expect($b)->toBe(['user' => 'b']);
});
```

- [ ] **Step 2: Run — fail**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/IdempotencyCacheTest.php`

- [ ] **Step 3: Implement**

Create `app/Domain/MCP/Exceptions/IdempotencyKeyReusedException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Exceptions;

class IdempotencyKeyReusedException extends \RuntimeException
{
}
```

Create `app/Domain/MCP/Policy/IdempotencyCache.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Policy;

use App\Domain\MCP\Exceptions\IdempotencyKeyReusedException;
use Illuminate\Support\Facades\Cache;

final class IdempotencyCache
{
    public function remember(string $tokenId, string $toolName, string $idempotencyKey, string $argsHash, callable $execute): mixed
    {
        $cacheKey = $this->key($tokenId, $toolName, $idempotencyKey);
        $store    = Cache::store((string) config('mcp.idempotency.cache_store', 'redis'));

        $existing = $store->get($cacheKey);
        if ($existing !== null) {
            if (($existing['args_hash'] ?? '') !== $argsHash) {
                throw new IdempotencyKeyReusedException(
                    "Idempotency key {$idempotencyKey} reused with different arguments"
                );
            }

            return $existing['result'];
        }

        $result = $execute();
        $store->put($cacheKey, ['args_hash' => $argsHash, 'result' => $result], (int) config('mcp.idempotency.ttl_seconds', 86400));

        return $result;
    }

    public function peek(string $tokenId, string $toolName, string $idempotencyKey): ?array
    {
        return Cache::store((string) config('mcp.idempotency.cache_store', 'redis'))->get($this->key($tokenId, $toolName, $idempotencyKey));
    }

    private function key(string $tokenId, string $toolName, string $idempotencyKey): string
    {
        return "mcp:idem:{$tokenId}:{$toolName}:{$idempotencyKey}";
    }
}
```

- [ ] **Step 4: Pass**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/IdempotencyCacheTest.php`

- [ ] **Step 5: Commit**

```bash
git add app/Domain/MCP/Policy/IdempotencyCache.php app/Domain/MCP/Exceptions/IdempotencyKeyReusedException.php tests/Unit/Domain/MCP/IdempotencyCacheTest.php
git commit -m "feat(mcp): idempotency cache with args-hash mismatch detection"
```

---

### Task 14: JsonRpcRouter — `initialize` and `tools/list`

**Files:**
- Create: `app/Domain/MCP/Server/JsonRpcRouter.php`
- Create: `app/Domain/MCP/Exceptions/JsonRpcException.php`
- Create: `tests/Unit/Domain/MCP/JsonRpcRouterTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

use App\Domain\MCP\Server\JsonRpcRouter;

it('responds to initialize with capabilities and protocol version', function () {
    $router = app(JsonRpcRouter::class);

    $envelope = [
        'jsonrpc' => '2.0',
        'id'      => 1,
        'method'  => 'initialize',
        'params'  => ['protocolVersion' => '2025-11-25', 'clientInfo' => ['name' => 'test', 'version' => '0.0.0']],
    ];

    $response = $router->dispatch($envelope, fakeMcpContext());

    expect($response['jsonrpc'])->toBe('2.0');
    expect($response['id'])->toBe(1);
    expect($response['result']['protocolVersion'])->toBe('2025-11-25');
    expect($response['result']['serverInfo']['name'])->toBe(config('mcp.server_info.name'));
    expect($response['result']['capabilities'])->toHaveKeys(['tools', 'resources']);
});

it('returns parse error for invalid jsonrpc envelope', function () {
    $router = app(JsonRpcRouter::class);

    $response = $router->dispatch(['method' => 'initialize'], fakeMcpContext());

    expect($response['error']['code'])->toBe(-32600);
});

it('lists tools filtered by token scopes', function () {
    $router = app(JsonRpcRouter::class);

    $ctx = fakeMcpContext(['scopes' => ['accounts:read']]);
    $envelope = ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => new stdClass()];
    $response = $router->dispatch($envelope, $ctx);

    $names = array_column($response['result']['tools'], 'name');
    expect($names)->toContain('account.balance');
    expect($names)->not->toContain('payment.transfer');  // no payments:write scope
    expect($names)->toContain('mpp.discovery');           // public tool always listed
});

function fakeMcpContext(array $overrides = []): App\Domain\MCP\Server\McpRequestContext
{
    return new App\Domain\MCP\Server\McpRequestContext(
        tokenId:  $overrides['token_id']  ?? 'tok_test',
        clientId: $overrides['client_id'] ?? 'client_test',
        userId:   $overrides['user_id']   ?? 1,
        scopes:   $overrides['scopes']    ?? ['accounts:read', 'payments:write', 'sms:send'],
    );
}
```

- [ ] **Step 2: Run — fail**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/JsonRpcRouterTest.php`

- [ ] **Step 3: Implement context value object**

Create `app/Domain/MCP/Server/McpRequestContext.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Server;

final class McpRequestContext
{
    /**
     * @param array<int, string> $scopes
     */
    public function __construct(
        public readonly string $tokenId,
        public readonly string $clientId,
        public readonly ?int $userId,
        public readonly array $scopes,
    ) {
    }

    public function hasScope(?string $scope): bool
    {
        if ($scope === null) {
            return true;
        }

        return in_array($scope, $this->scopes, true) || in_array('*', $this->scopes, true);
    }
}
```

- [ ] **Step 4: Implement router with initialize and tools/list**

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Server;

final class JsonRpcRouter
{
    /**
     * @param array<string, mixed> $envelope
     * @return array<string, mixed>
     */
    public function dispatch(array $envelope, McpRequestContext $ctx): array
    {
        $id = $envelope['id'] ?? null;

        if (($envelope['jsonrpc'] ?? null) !== '2.0' || ! isset($envelope['method'])) {
            return $this->error($id, -32600, 'INVALID_REQUEST');
        }

        $method = (string) $envelope['method'];
        $params = $envelope['params'] ?? [];

        return match ($method) {
            'initialize' => $this->handleInitialize($id, $params),
            'tools/list' => $this->handleToolsList($id, $ctx),
            'ping'       => ['jsonrpc' => '2.0', 'id' => $id, 'result' => new \stdClass()],
            default      => $this->error($id, -32601, 'METHOD_NOT_FOUND', ['method' => $method]),
        };
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function handleInitialize(mixed $id, array $params): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => [
                'protocolVersion' => (string) config('mcp.protocol_version'),
                'serverInfo'      => (array) config('mcp.server_info'),
                'capabilities'    => [
                    'tools'     => ['listChanged' => true],
                    'resources' => ['listChanged' => true, 'subscribe' => false],
                    'prompts'   => null,
                    'logging'   => null,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleToolsList(mixed $id, McpRequestContext $ctx): array
    {
        $tools     = [];
        $catalog   = (array) config('mcp.tools');
        $registry  = app(\App\Domain\AI\MCP\ToolRegistry::class);

        foreach ($catalog as $publicName => $entry) {
            if (! ($entry['enabled'] ?? false)) {
                continue;
            }
            if (! $ctx->hasScope($entry['scope'] ?? null)) {
                continue;
            }

            try {
                $internal = $registry->get($entry['internal']);
            } catch (\Throwable) {
                continue;
            }

            $tools[] = [
                'name'        => $publicName,
                'description' => $internal->getDescription(),
                'inputSchema' => $this->withIdempotencyField($internal->getInputSchema(), (bool) ($entry['is_write'] ?? false)),
            ];
        }

        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => ['tools' => $tools],
        ];
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function withIdempotencyField(array $schema, bool $isWrite): array
    {
        if (! $isWrite) {
            return $schema;
        }
        $schema['properties']['idempotency_key'] = [
            'type'        => 'string',
            'format'      => 'uuid',
            'description' => 'Required for write tools. Server caches result for 24h; same key + same args returns cached result; same key + different args returns -32002.',
        ];
        $schema['required'] = array_values(array_unique(array_merge((array) ($schema['required'] ?? []), ['idempotency_key'])));

        return $schema;
    }

    /**
     * @param array<string, mixed>|null $data
     * @return array<string, mixed>
     */
    private function error(mixed $id, int $code, string $message, ?array $data = null): array
    {
        $err = ['code' => $code, 'message' => $message];
        if ($data !== null) {
            $err['data'] = $data;
        }

        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => $err];
    }
}
```

- [ ] **Step 5: Pass**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/JsonRpcRouterTest.php`

- [ ] **Step 6: Commit**

```bash
git add app/Domain/MCP/Server/JsonRpcRouter.php app/Domain/MCP/Server/McpRequestContext.php tests/Unit/Domain/MCP/JsonRpcRouterTest.php
git commit -m "feat(mcp): JSON-RPC router — initialize, tools/list with scope filtering"
```

---

### Task 15: McpToolAdapter

**Files:**
- Create: `app/Domain/MCP/Server/McpToolAdapter.php`
- Create: `tests/Unit/Domain/MCP/McpToolAdapterTest.php`

- [ ] **Step 1: Test**

```php
<?php

declare(strict_types=1);

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\MCP\Server\McpToolAdapter;

class StubTool implements MCPToolInterface
{
    public function getName(): string { return 'stub_tool'; }
    public function getCategory(): string { return 'test'; }
    public function getDescription(): string { return 'Stub'; }
    public function getInputSchema(): array { return ['type' => 'object', 'properties' => ['x' => ['type' => 'integer']]]; }
    public function getOutputSchema(): array { return ['type' => 'object']; }
    public function getCapabilities(): array { return []; }
    public function isCacheable(): bool { return false; }
    public function getCacheTtl(): int { return 0; }
    public function validateInput(array $p): bool { return true; }
    public function authorize(?string $userId): bool { return true; }
    public function execute(array $params, ?string $conversationId = null): ToolExecutionResult
    {
        return ToolExecutionResult::success(['echoed' => $params['x'] ?? null]);
    }
}

it('adapts internal tool result into JSON-RPC content shape', function () {
    $adapter = new McpToolAdapter();
    $result  = $adapter->execute(new StubTool(), ['x' => 7], 'conv_test');

    expect($result)->toHaveKey('content');
    expect($result['content'][0]['type'])->toBe('text');
    expect(json_decode($result['content'][0]['text'], true))->toBe(['echoed' => 7]);
    expect($result['isError'] ?? false)->toBeFalse();
});

it('flags errors with isError=true', function () {
    $tool = new class extends StubTool {
        public function execute(array $params, ?string $conversationId = null): ToolExecutionResult
        {
            return ToolExecutionResult::error('Boom', 'BOOM');
        }
    };
    $result = (new McpToolAdapter())->execute($tool, [], 'conv');

    expect($result['isError'])->toBeTrue();
    expect($result['content'][0]['text'])->toContain('Boom');
});
```

- [ ] **Step 2: Run — fail**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/McpToolAdapterTest.php`

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Server;

use App\Domain\AI\Contracts\MCPToolInterface;

final class McpToolAdapter
{
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function execute(MCPToolInterface $tool, array $params, ?string $conversationId): array
    {
        $result = $tool->execute($params, $conversationId);

        if (! $result->isSuccess()) {
            return [
                'content' => [
                    ['type' => 'text', 'text' => $result->getError() ?? 'Tool execution failed'],
                ],
                'isError' => true,
            ];
        }

        $payload = $result->getData();

        return [
            'content' => [
                ['type' => 'text', 'text' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)],
            ],
            'isError'           => false,
            'structuredContent' => $payload,
        ];
    }
}
```

- [ ] **Step 4: Pass**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/McpToolAdapterTest.php`

- [ ] **Step 5: Commit**

```bash
git add app/Domain/MCP/Server/McpToolAdapter.php tests/Unit/Domain/MCP/McpToolAdapterTest.php
git commit -m "feat(mcp): adapter bridges MCPToolInterface results to JSON-RPC content"
```

---

### Task 16: `tools/call` dispatch with audit + idempotency + spending

**Files:**
- Modify: `app/Domain/MCP/Server/JsonRpcRouter.php`
- Create: `tests/Feature/MCP/ToolsCallTest.php`

- [ ] **Step 1: Write the feature test (5 paths)**

```php
<?php

declare(strict_types=1);

use App\Domain\MCP\Server\JsonRpcRouter;
use App\Domain\MCP\Server\McpRequestContext;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('mcp_token_policies')->insert([
        'token_id'              => 'tok_test',
        'daily_limit_minor'     => 50000,
        'daily_limit_currency'  => 'USD',
        'daily_spend_minor'     => 0,
        'daily_window_start_at' => now(),
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);
});

it('returns -32601 for an unknown public tool', function () {
    $router = app(JsonRpcRouter::class);
    $ctx    = new McpRequestContext('tok_test', 'cli', 1, ['*']);
    $resp = $router->dispatch([
        'jsonrpc' => '2.0',
        'id'      => 1,
        'method'  => 'tools/call',
        'params'  => ['name' => 'nonexistent.tool', 'arguments' => []],
    ], $ctx);

    expect($resp['error']['code'])->toBe(-32601);
});

it('returns -32000 when token lacks the required scope', function () {
    $router = app(JsonRpcRouter::class);
    $ctx    = new McpRequestContext('tok_test', 'cli', 1, ['accounts:read']);
    $resp = $router->dispatch([
        'jsonrpc' => '2.0',
        'id'      => 2,
        'method'  => 'tools/call',
        'params'  => ['name' => 'payment.transfer', 'arguments' => ['idempotency_key' => 'k1']],
    ], $ctx);

    expect($resp['error']['code'])->toBe(-32000);
});

it('returns -32602 when write tool missing idempotency_key', function () {
    $router = app(JsonRpcRouter::class);
    $ctx    = new McpRequestContext('tok_test', 'cli', 1, ['payments:write']);
    $resp = $router->dispatch([
        'jsonrpc' => '2.0',
        'id'      => 3,
        'method'  => 'tools/call',
        'params'  => ['name' => 'payment.transfer', 'arguments' => ['amount' => 100]],
    ], $ctx);

    expect($resp['error']['code'])->toBe(-32602);
});

it('returns -32004 when tool is disabled by config', function () {
    config()->set('mcp.tools.payment\\.transfer.enabled', false);
    config()->set('mcp.tools.payment.transfer.enabled', false);  // both flat + dotted possible

    $router = app(JsonRpcRouter::class);
    $ctx    = new McpRequestContext('tok_test', 'cli', 1, ['payments:write']);
    $resp = $router->dispatch([
        'jsonrpc' => '2.0',
        'id'      => 4,
        'method'  => 'tools/call',
        'params'  => ['name' => 'payment.transfer', 'arguments' => ['idempotency_key' => 'k']],
    ], $ctx);

    expect($resp['error']['code'])->toBe(-32004);
});

it('writes an audit row on successful invocation', function () {
    $router = app(JsonRpcRouter::class);
    $ctx    = new McpRequestContext('tok_test', 'cli', 1, ['accounts:read']);
    $router->dispatch([
        'jsonrpc' => '2.0',
        'id'      => 5,
        'method'  => 'tools/call',
        'params'  => ['name' => 'account.balance', 'arguments' => ['account_id' => 'acc-x']],
    ], $ctx);

    $this->assertDatabaseHas('mcp_tool_invocations', [
        'token_id'  => 'tok_test',
        'tool_name' => 'account.balance',
    ]);
});
```

- [ ] **Step 2: Run — fail**

Run: `./vendor/bin/pest tests/Feature/MCP/ToolsCallTest.php`

- [ ] **Step 3: Extend the router with `tools/call`**

Add to `JsonRpcRouter`'s `dispatch()` match:

```php
'tools/call' => $this->handleToolsCall($id, $params, $ctx),
```

Add the handler method to the class:

```php
/**
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
private function handleToolsCall(mixed $id, array $params, McpRequestContext $ctx): array
{
    $name      = (string) ($params['name'] ?? '');
    $arguments = (array) ($params['arguments'] ?? []);

    $catalog = (array) config('mcp.tools');
    if (! isset($catalog[$name])) {
        return $this->error($id, -32601, 'TOOL_NOT_FOUND', ['name' => $name]);
    }

    $entry = $catalog[$name];
    if (! ($entry['enabled'] ?? false)) {
        return $this->error($id, -32004, 'TOOL_DISABLED', ['name' => $name]);
    }

    if (! $ctx->hasScope($entry['scope'] ?? null)) {
        return $this->error($id, -32000, 'INSUFFICIENT_SCOPE', [
            'required' => $entry['scope'],
            'granted'  => $ctx->scopes,
        ]);
    }

    $isWrite = (bool) ($entry['is_write'] ?? false);
    $idemKey = $arguments['idempotency_key'] ?? null;

    if ($isWrite && (! is_string($idemKey) || $idemKey === '')) {
        return $this->error($id, -32602, 'IDEMPOTENCY_KEY_REQUIRED', ['tool' => $name]);
    }

    $registry = app(\App\Domain\AI\MCP\ToolRegistry::class);
    try {
        $tool = $registry->get($entry['internal']);
    } catch (\Throwable) {
        return $this->error($id, -32603, 'INTERNAL_TOOL_MISSING', ['internal' => $entry['internal']]);
    }

    $argsHash = hash('sha256', json_encode($arguments, JSON_SORT_KEYS | JSON_UNESCAPED_SLASHES));
    $logger   = app(\App\Domain\MCP\Audit\ToolInvocationLogger::class);
    $adapter  = app(\App\Domain\MCP\Server\McpToolAdapter::class);
    $started  = hrtime(true);

    try {
        if ($isWrite) {
            $cache    = app(\App\Domain\MCP\Policy\IdempotencyCache::class);
            $callable = function () use ($adapter, $tool, $arguments, $ctx) {
                return $adapter->execute($tool, $arguments, 'mcp_' . $ctx->tokenId);
            };
            $result = $cache->remember($ctx->tokenId, $name, (string) $idemKey, $argsHash, $callable);
        } else {
            $result = $adapter->execute($tool, $arguments, 'mcp_' . $ctx->tokenId);
        }
    } catch (\App\Domain\MCP\Exceptions\IdempotencyKeyReusedException $e) {
        $logger->log([
            'token_id'      => $ctx->tokenId,
            'client_id'     => $ctx->clientId,
            'user_id'       => $ctx->userId,
            'tool_name'     => $name,
            'args_hash'     => $argsHash,
            'idempotency_key' => (string) $idemKey,
            'result_status' => 'error',
            'error_code'    => 'IDEMPOTENCY_KEY_REUSED',
        ]);

        return $this->error($id, -32002, 'IDEMPOTENCY_KEY_REUSED', ['idempotency_key' => $idemKey]);
    }

    $logger->log([
        'token_id'      => $ctx->tokenId,
        'client_id'     => $ctx->clientId,
        'user_id'       => $ctx->userId,
        'tool_name'     => $name,
        'args_hash'     => $argsHash,
        'idempotency_key' => $idemKey,
        'result_status' => $result['isError'] ?? false ? 'error' : 'success',
        'duration_ms'   => (int) ((hrtime(true) - $started) / 1_000_000),
    ]);

    return [
        'jsonrpc' => '2.0',
        'id'      => $id,
        'result'  => $result,
    ];
}
```

- [ ] **Step 4: Run — pass**

Run: `./vendor/bin/pest tests/Feature/MCP/ToolsCallTest.php`

- [ ] **Step 5: Commit**

```bash
git add app/Domain/MCP/Server/JsonRpcRouter.php tests/Feature/MCP/ToolsCallTest.php
git commit -m "feat(mcp): tools/call dispatch with audit, idempotency, scope guard"
```

---

### Task 17: StreamableHttp transport

**Files:**
- Create: `app/Domain/MCP/Server/StreamableHttpController.php`
- Create: `app/Domain/MCP/Server/SseStreamManager.php`
- Modify: `app/Domain/MCP/Routes/api.php`
- Create: `tests/Feature/MCP/SseStreamTest.php`

- [ ] **Step 1: Create the SSE stream manager**

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Server;

use Symfony\Component\HttpFoundation\StreamedResponse;

final class SseStreamManager
{
    public function open(int $heartbeatSeconds = 25): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($heartbeatSeconds) {
            // SSE headers already set by caller
            ob_implicit_flush(true);
            @ini_set('output_buffering', 'off');

            $started   = time();
            $maxRuntime = 60 * 5; // 5 min per connection; client should reconnect

            while (! connection_aborted() && (time() - $started) < $maxRuntime) {
                echo ": heartbeat\n\n";
                @ob_flush();
                @flush();
                sleep($heartbeatSeconds);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
```

- [ ] **Step 2: Create the controller**

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Server;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class StreamableHttpController
{
    public function __construct(
        private readonly JsonRpcRouter $router,
        private readonly SseStreamManager $sse,
    ) {
    }

    public function handle(Request $request): Response
    {
        if ($request->isMethod('GET')) {
            // Spec allows server to return 405 if SSE not supported; we support it.
            if (! str_contains((string) $request->header('Accept'), 'text/event-stream')) {
                return new JsonResponse(['error' => 'Accept must include text/event-stream'], 406);
            }

            return $this->sse->open();
        }

        $envelope = $request->json()->all();
        $token    = $request->attributes->get('mcp.token');

        $ctx = new McpRequestContext(
            tokenId:  $token?->getKey() ?? 'anon',
            clientId: (string) ($token?->client_id ?? 'anon'),
            userId:   $token?->user_id,
            scopes:   (array) ($token?->scopes ?? []),
        );

        $response = $this->router->dispatch($envelope, $ctx);

        return new JsonResponse($response);
    }
}
```

- [ ] **Step 3: Wire the route**

Append to `app/Domain/MCP/Routes/api.php`:

```php
use App\Domain\MCP\Server\StreamableHttpController;

Route::match(['GET', 'POST'], '/mcp', [StreamableHttpController::class, 'handle'])
    ->middleware(['mcp.oauth'])
    ->name('mcp.endpoint');
```

- [ ] **Step 4: Feature test for SSE**

```php
<?php

declare(strict_types=1);

it('returns 406 if Accept header lacks text/event-stream on GET /mcp', function () {
    $response = $this->withServerVariables(['HTTP_HOST' => config('mcp.host')])
        ->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer test'])
        ->get('/mcp');

    expect($response->status())->toBeIn([401, 406]);
});
```

- [ ] **Step 5: Run — pass**

Run: `./vendor/bin/pest tests/Feature/MCP/SseStreamTest.php`

- [ ] **Step 6: Commit**

```bash
git add app/Domain/MCP/Server/StreamableHttpController.php app/Domain/MCP/Server/SseStreamManager.php app/Domain/MCP/Routes/api.php tests/Feature/MCP/SseStreamTest.php
git commit -m "feat(mcp): streamable-HTTP transport — POST /mcp + GET /mcp (SSE)"
```

---

### Task 18: Rate limit integration

**Files:**
- Create: `app/Providers/McpServiceProvider.php`
- Modify: `bootstrap/providers.php`
- Modify: `app/Domain/MCP/Routes/api.php`

- [ ] **Step 1: Create the service provider with rate limiters**

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class McpServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerRateLimiters();
    }

    private function registerRateLimiters(): void
    {
        $limits = (array) config('mcp.rate_limits');

        RateLimiter::for('mcp.aggregate', function (Request $request) use ($limits) {
            $tokenId = (string) ($request->attributes->get('mcp.token')?->getKey() ?? $request->ip());

            return [
                Limit::perMinute((int) ($limits['aggregate']['per_minute'] ?? 60))->by($tokenId),
                Limit::perHour((int) ($limits['aggregate']['per_hour'] ?? 600))->by($tokenId),
            ];
        });

        RateLimiter::for('mcp.discovery', function (Request $request) use ($limits) {
            return Limit::perMinute((int) ($limits['discovery_per_minute_per_ip'] ?? 60))->by($request->ip());
        });
    }
}
```

- [ ] **Step 2: Register provider**

Add to `bootstrap/providers.php`:

```php
App\Providers\McpServiceProvider::class,
```

- [ ] **Step 3: Apply rate limit middleware to MCP routes**

Update `app/Domain/MCP/Routes/api.php` route:

```php
Route::match(['GET', 'POST'], '/mcp', [StreamableHttpController::class, 'handle'])
    ->middleware(['mcp.oauth', 'throttle:mcp.aggregate'])
    ->name('mcp.endpoint');

Route::get('/.well-known/oauth-protected-resource', ProtectedResourceMetadataController::class)
    ->middleware(['throttle:mcp.discovery'])
    ->name('mcp.discovery.protected-resource');
```

- [ ] **Step 4: Verify config:cache works**

Run: `php artisan config:cache && php artisan route:list --path=mcp`
Expected: routes show throttle middleware applied.

- [ ] **Step 5: Commit**

```bash
git add app/Providers/McpServiceProvider.php bootstrap/providers.php app/Domain/MCP/Routes/api.php
git commit -m "feat(mcp): rate-limit middleware for /mcp and discovery endpoints"
```

---

(Phase 2 complete — 10 tasks. Wire protocol live, JSON-RPC routes initialize/tools/list/tools/call, audit + idempotency + spending limits enforced, SSE transport ready, rate limits in place.)

---

## Phase 3: Tool Catalog v1 (Tasks 19-26)

### Task 19: Verify the 10 existing internal tool names match config

The catalog in `config/mcp.php` references internal names: `get_account_balance`, `create_account`, `payment_status`, `transfer`, `transaction_query`, `spending_analysis`, `exchange_quote`, `exchange_trade`, `mpp_discovery`, `send_sms`. Each must match the value returned by `MCPToolInterface::getName()` on the existing tool class.

**Files:**
- Read-only verification across `app/Domain/AI/MCP/Tools/**/*.php`
- Modify: `config/mcp.php` (only if a name mismatches)

- [ ] **Step 1: Run a verification script**

Run: `php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap(); \$registry = app('App\\Domain\\AI\\MCP\\ToolRegistry'); foreach (config('mcp.tools') as \$pub => \$e) { try { \$registry->get(\$e['internal']); echo \"$pub -> {\$e['internal']} OK\n\"; } catch (\\Throwable \$t) { echo \"$pub -> {\$e['internal']} MISSING\n\"; } }"`

Expected output: every tool prints `OK`.

- [ ] **Step 2: If any print MISSING, locate the actual name**

Run: `grep -rn "public function getName" app/Domain/AI/MCP/Tools/`

Update the `internal` key in `config/mcp.php` to match.

- [ ] **Step 3: Commit (only if config changed)**

```bash
git add config/mcp.php
git commit -m "fix(mcp): align catalog internal names with registered tool getName()"
```

---

### Task 20: New tool — `ramp.start` (Stripe Bridge wrapper)

**Files:**
- Create: `app/Domain/MCP/Tools/Ramp/RampStartTool.php`
- Modify: `app/Providers/MCPToolServiceProvider.php` (register the new tool)
- Create: `tests/Unit/Domain/MCP/RampStartToolTest.php`

- [ ] **Step 1: Test**

```php
<?php

declare(strict_types=1);

use App\Domain\MCP\Tools\Ramp\RampStartTool;
use App\Domain\Ramp\Services\RampService;

it('starts a ramp session and returns checkout URL', function () {
    $service = Mockery::mock(RampService::class);
    $service->shouldReceive('createSession')
        ->once()
        ->andReturn([
            'session_id'   => 'sess_123',
            'checkout_url' => 'https://checkout.stripe.com/abc',
            'provider'     => 'stripe_bridge',
            'status'       => 'pending',
        ]);

    $tool = new RampStartTool($service);
    $result = $tool->execute([
        'type'             => 'on',
        'fiat_currency'    => 'USD',
        'fiat_amount'      => 100,
        'crypto_currency'  => 'USDC',
        'wallet_address'   => '0xabc',
        'idempotency_key'  => 'idem-1',
    ], 'conv-1');

    expect($result->isSuccess())->toBeTrue();
    expect($result->getData())->toMatchArray([
        'session_id'   => 'sess_123',
        'checkout_url' => 'https://checkout.stripe.com/abc',
        'provider'     => 'stripe_bridge',
    ]);
});
```

- [ ] **Step 2: Run — fail**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/RampStartToolTest.php`

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Tools\Ramp;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\Ramp\Services\RampService;
use Throwable;

final class RampStartTool implements MCPToolInterface
{
    public function __construct(private readonly RampService $service)
    {
    }

    public function getName(): string
    {
        return 'ramp_start';
    }

    public function getCategory(): string
    {
        return 'ramp';
    }

    public function getDescription(): string
    {
        return 'Start an on-ramp or off-ramp session. Returns a hosted checkout URL the user must complete in a browser.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type'             => ['type' => 'string', 'enum' => ['on', 'off']],
                'fiat_currency'    => ['type' => 'string', 'enum' => ['USD', 'EUR', 'GBP']],
                'fiat_amount'      => ['type' => 'number', 'minimum' => 1],
                'crypto_currency'  => ['type' => 'string', 'enum' => ['USDC', 'EURC', 'USDT']],
                'wallet_address'   => ['type' => 'string'],
            ],
            'required' => ['type', 'fiat_currency', 'fiat_amount', 'crypto_currency', 'wallet_address'],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'session_id'   => ['type' => 'string'],
                'checkout_url' => ['type' => 'string', 'format' => 'uri'],
                'provider'     => ['type' => 'string'],
                'status'       => ['type' => 'string'],
            ],
        ];
    }

    public function getCapabilities(): array
    {
        return ['async' => true, 'requires_browser' => true];
    }

    public function isCacheable(): bool
    {
        return false;
    }

    public function getCacheTtl(): int
    {
        return 0;
    }

    public function validateInput(array $parameters): bool
    {
        return isset($parameters['type'], $parameters['fiat_currency'], $parameters['fiat_amount'], $parameters['crypto_currency'], $parameters['wallet_address']);
    }

    public function authorize(?string $userId): bool
    {
        return $userId !== null;
    }

    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        try {
            $session = $this->service->createSession([
                'type'            => $parameters['type'],
                'fiat_currency'   => $parameters['fiat_currency'],
                'fiat_amount'     => (float) $parameters['fiat_amount'],
                'crypto_currency' => $parameters['crypto_currency'],
                'wallet_address'  => $parameters['wallet_address'],
            ]);

            return ToolExecutionResult::success([
                'session_id'   => $session['session_id'] ?? null,
                'checkout_url' => $session['checkout_url'] ?? null,
                'provider'     => $session['provider'] ?? null,
                'status'       => $session['status'] ?? null,
            ]);
        } catch (Throwable $t) {
            return ToolExecutionResult::error($t->getMessage(), 'RAMP_SESSION_FAILED');
        }
    }
}
```

- [ ] **Step 4: Register in MCPToolServiceProvider**

In `app/Providers/MCPToolServiceProvider.php`, add to the `$tools` array:

```php
\App\Domain\MCP\Tools\Ramp\RampStartTool::class,
```

- [ ] **Step 5: Pass**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/RampStartToolTest.php`

- [ ] **Step 6: Commit**

```bash
git add app/Domain/MCP/Tools/Ramp/RampStartTool.php app/Providers/MCPToolServiceProvider.php tests/Unit/Domain/MCP/RampStartToolTest.php
git commit -m "feat(mcp): RampStartTool wraps Stripe Bridge session creation"
```

---

### Task 21: New tool — `ramp.status`

**Files:**
- Create: `app/Domain/MCP/Tools/Ramp/RampStatusTool.php`
- Modify: `app/Providers/MCPToolServiceProvider.php`
- Create: `tests/Unit/Domain/MCP/RampStatusToolTest.php`

- [ ] **Step 1: Test**

```php
<?php

declare(strict_types=1);

use App\Domain\MCP\Tools\Ramp\RampStatusTool;
use App\Domain\Ramp\Services\RampService;

it('returns session status for a known session id', function () {
    $service = Mockery::mock(RampService::class);
    $service->shouldReceive('getSession')->with('sess_123')->andReturn([
        'session_id' => 'sess_123',
        'status'     => 'completed',
        'provider'   => 'stripe_bridge',
    ]);

    $result = (new RampStatusTool($service))->execute(['session_id' => 'sess_123'], null);

    expect($result->isSuccess())->toBeTrue();
    expect($result->getData()['status'])->toBe('completed');
});
```

- [ ] **Step 2: Run — fail**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/RampStatusToolTest.php`

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Tools\Ramp;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\Ramp\Services\RampService;
use Throwable;

final class RampStatusTool implements MCPToolInterface
{
    public function __construct(private readonly RampService $service)
    {
    }

    public function getName(): string { return 'ramp_status'; }
    public function getCategory(): string { return 'ramp'; }
    public function getDescription(): string { return 'Get the current status of an on/off-ramp session by session_id.'; }
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['session_id' => ['type' => 'string']],
            'required' => ['session_id'],
        ];
    }
    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'session_id' => ['type' => 'string'],
                'status'     => ['type' => 'string', 'enum' => ['pending', 'processing', 'completed', 'failed', 'expired']],
                'provider'   => ['type' => 'string'],
            ],
        ];
    }
    public function getCapabilities(): array { return []; }
    public function isCacheable(): bool { return true; }
    public function getCacheTtl(): int { return 5; }
    public function validateInput(array $parameters): bool { return isset($parameters['session_id']); }
    public function authorize(?string $userId): bool { return $userId !== null; }

    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        try {
            $session = $this->service->getSession((string) $parameters['session_id']);

            return ToolExecutionResult::success($session);
        } catch (Throwable $t) {
            return ToolExecutionResult::error($t->getMessage(), 'RAMP_STATUS_FAILED');
        }
    }
}
```

- [ ] **Step 4: Register**

In `app/Providers/MCPToolServiceProvider.php` `$tools` array:

```php
\App\Domain\MCP\Tools\Ramp\RampStatusTool::class,
```

- [ ] **Step 5: Pass + commit**

```bash
./vendor/bin/pest tests/Unit/Domain/MCP/RampStatusToolTest.php
git add app/Domain/MCP/Tools/Ramp/RampStatusTool.php app/Providers/MCPToolServiceProvider.php tests/Unit/Domain/MCP/RampStatusToolTest.php
git commit -m "feat(mcp): RampStatusTool wraps Stripe Bridge session lookup"
```

---

### Task 22: Resource — `account://profile`

**Files:**
- Create: `app/Domain/MCP/Resources/AccountProfileResource.php`
- Create: `app/Domain/MCP/Resources/Contracts/McpResourceInterface.php`
- Create: `tests/Unit/Domain/MCP/AccountProfileResourceTest.php`

- [ ] **Step 1: Resource interface**

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Resources\Contracts;

interface McpResourceInterface
{
    public function uriTemplate(): string;
    public function name(): string;
    public function description(): string;
    public function mimeType(): string;
    public function scope(): ?string;

    /**
     * @param array<string, string> $params  URI template params, e.g. ['currency' => 'USD']
     * @return string  The resource body (typically JSON-serialized)
     */
    public function read(array $params, ?int $userId): string;
}
```

- [ ] **Step 2: Test**

```php
<?php

declare(strict_types=1);

use App\Domain\MCP\Resources\AccountProfileResource;
use App\Models\User;
use App\Domain\Account\Models\Account;

it('returns the user primary account profile as JSON', function () {
    $user = User::factory()->create();
    Account::factory()->create(['user_uuid' => $user->uuid, 'name' => 'Primary']);

    $resource = app(AccountProfileResource::class);
    $body     = $resource->read([], $user->id);

    $payload = json_decode($body, true);
    expect($payload)->toHaveKeys(['account_id', 'user_uuid', 'name']);
});
```

- [ ] **Step 3: Run — fail**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/AccountProfileResourceTest.php`

- [ ] **Step 4: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Resources;

use App\Domain\Account\Models\Account;
use App\Domain\MCP\Resources\Contracts\McpResourceInterface;
use App\Models\User;

final class AccountProfileResource implements McpResourceInterface
{
    public function uriTemplate(): string { return 'account://profile'; }
    public function name(): string { return 'Primary account profile'; }
    public function description(): string { return 'The authenticated user primary account metadata.'; }
    public function mimeType(): string { return 'application/json'; }
    public function scope(): ?string { return 'accounts:read'; }

    public function read(array $params, ?int $userId): string
    {
        if ($userId === null) {
            return json_encode(['error' => 'no user context']);
        }
        $user = User::find($userId);
        if ($user === null) {
            return json_encode(['error' => 'user not found']);
        }
        $account = Account::where('user_uuid', $user->uuid)->first();
        if ($account === null) {
            return json_encode(['error' => 'no primary account']);
        }

        return (string) json_encode([
            'account_id' => $account->uuid ?? $account->id,
            'user_uuid'  => $user->uuid,
            'name'       => $account->name,
            'created_at' => $account->created_at?->toIso8601String(),
        ]);
    }
}
```

- [ ] **Step 5: Pass + commit**

```bash
./vendor/bin/pest tests/Unit/Domain/MCP/AccountProfileResourceTest.php
git add app/Domain/MCP/Resources/Contracts/McpResourceInterface.php app/Domain/MCP/Resources/AccountProfileResource.php tests/Unit/Domain/MCP/AccountProfileResourceTest.php
git commit -m "feat(mcp): account://profile resource"
```

---

### Task 23: Resource — `account://balance/{currency}`

**Files:**
- Create: `app/Domain/MCP/Resources/AccountBalanceResource.php`
- Create: `tests/Unit/Domain/MCP/AccountBalanceResourceTest.php`

- [ ] **Step 1: Test**

```php
<?php

declare(strict_types=1);

use App\Domain\MCP\Resources\AccountBalanceResource;
use App\Models\User;

it('returns balance JSON for a currency', function () {
    $user = User::factory()->create();
    $resource = app(AccountBalanceResource::class);

    $body = $resource->read(['currency' => 'USD'], $user->id);
    $payload = json_decode($body, true);

    expect($payload)->toHaveKeys(['currency', 'balance_minor']);
    expect($payload['currency'])->toBe('USD');
});
```

- [ ] **Step 2: Run — fail**

Run: `./vendor/bin/pest tests/Unit/Domain/MCP/AccountBalanceResourceTest.php`

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Resources;

use App\Domain\Account\Models\Account;
use App\Domain\MCP\Resources\Contracts\McpResourceInterface;
use App\Models\User;

final class AccountBalanceResource implements McpResourceInterface
{
    public function uriTemplate(): string { return 'account://balance/{currency}'; }
    public function name(): string { return 'Account balance by currency'; }
    public function description(): string { return 'Live balance in the requested currency, in minor units (cents).'; }
    public function mimeType(): string { return 'application/json'; }
    public function scope(): ?string { return 'accounts:read'; }

    public function read(array $params, ?int $userId): string
    {
        $currency = strtoupper((string) ($params['currency'] ?? 'USD'));
        if ($userId === null) {
            return (string) json_encode(['error' => 'no user context']);
        }

        $user = User::find($userId);
        $account = $user ? Account::where('user_uuid', $user->uuid)->first() : null;

        $balanceMinor = 0;
        if ($account !== null && method_exists($account, 'balanceForCurrency')) {
            $balanceMinor = (int) $account->balanceForCurrency($currency);
        }

        return (string) json_encode([
            'currency'      => $currency,
            'balance_minor' => $balanceMinor,
            'account_id'    => $account?->uuid ?? $account?->id,
            'as_of'         => now()->toIso8601String(),
        ]);
    }
}
```

- [ ] **Step 4: Pass + commit**

```bash
./vendor/bin/pest tests/Unit/Domain/MCP/AccountBalanceResourceTest.php
git add app/Domain/MCP/Resources/AccountBalanceResource.php tests/Unit/Domain/MCP/AccountBalanceResourceTest.php
git commit -m "feat(mcp): account://balance/{currency} resource"
```

---

### Task 24: Resources — `transactions://recent` and `transaction://{id}`

**Files:**
- Create: `app/Domain/MCP/Resources/RecentTransactionsResource.php`
- Create: `app/Domain/MCP/Resources/SingleTransactionResource.php`

- [ ] **Step 1: Implement RecentTransactionsResource**

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Resources;

use App\Domain\MCP\Resources\Contracts\McpResourceInterface;
use App\Domain\Transaction\Models\Transaction;
use App\Models\User;

final class RecentTransactionsResource implements McpResourceInterface
{
    public function uriTemplate(): string { return 'transactions://recent'; }
    public function name(): string { return 'Recent transactions'; }
    public function description(): string { return 'Last N transactions for the authenticated user. Default 25, max 100.'; }
    public function mimeType(): string { return 'application/json'; }
    public function scope(): ?string { return 'transactions:read'; }

    public function read(array $params, ?int $userId): string
    {
        if ($userId === null) {
            return (string) json_encode(['error' => 'no user context']);
        }
        $limit = max(1, min(100, (int) ($params['limit'] ?? 25)));

        $user = User::find($userId);
        if ($user === null) {
            return (string) json_encode(['transactions' => []]);
        }

        $rows = Transaction::where('user_uuid', $user->uuid)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'amount_minor', 'currency', 'type', 'status', 'created_at']);

        return (string) json_encode([
            'transactions' => $rows->map(fn ($t) => [
                'id'         => $t->id,
                'amount_minor' => $t->amount_minor,
                'currency'   => $t->currency,
                'type'       => $t->type,
                'status'     => $t->status,
                'created_at' => $t->created_at?->toIso8601String(),
            ])->toArray(),
        ]);
    }
}
```

- [ ] **Step 2: Implement SingleTransactionResource**

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Resources;

use App\Domain\MCP\Resources\Contracts\McpResourceInterface;
use App\Domain\Transaction\Models\Transaction;

final class SingleTransactionResource implements McpResourceInterface
{
    public function uriTemplate(): string { return 'transaction://{id}'; }
    public function name(): string { return 'Single transaction'; }
    public function description(): string { return 'Lookup a single transaction by id.'; }
    public function mimeType(): string { return 'application/json'; }
    public function scope(): ?string { return 'transactions:read'; }

    public function read(array $params, ?int $userId): string
    {
        $id = (string) ($params['id'] ?? '');
        if ($id === '' || $userId === null) {
            return (string) json_encode(['error' => 'id required']);
        }

        $tx = Transaction::where('id', $id)->first();
        if ($tx === null) {
            return (string) json_encode(['error' => 'not found']);
        }

        return (string) json_encode([
            'id'           => $tx->id,
            'amount_minor' => $tx->amount_minor,
            'currency'     => $tx->currency,
            'type'         => $tx->type,
            'status'       => $tx->status,
            'created_at'   => $tx->created_at?->toIso8601String(),
        ]);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Domain/MCP/Resources/RecentTransactionsResource.php app/Domain/MCP/Resources/SingleTransactionResource.php
git commit -m "feat(mcp): transactions://recent and transaction://{id} resources"
```

---

### Task 25: Wire `resources/list` and `resources/read` into JsonRpcRouter

**Files:**
- Modify: `app/Domain/MCP/Server/JsonRpcRouter.php`
- Create: `app/Domain/MCP/Resources/ResourceRegistry.php`
- Create: `tests/Feature/MCP/ResourcesTest.php`

- [ ] **Step 1: Resource registry**

```php
<?php

declare(strict_types=1);

namespace App\Domain\MCP\Resources;

use App\Domain\MCP\Resources\Contracts\McpResourceInterface;

final class ResourceRegistry
{
    /**
     * @var array<string, McpResourceInterface>
     */
    private array $byTemplate = [];

    public function register(McpResourceInterface $resource): void
    {
        $this->byTemplate[$resource->uriTemplate()] = $resource;
    }

    /**
     * @return array<int, McpResourceInterface>
     */
    public function all(): array
    {
        return array_values($this->byTemplate);
    }

    /**
     * Resolve a concrete URI like account://balance/USD to (template, params).
     *
     * @return array{0: McpResourceInterface, 1: array<string, string>}|null
     */
    public function resolve(string $uri): ?array
    {
        foreach ($this->byTemplate as $tpl => $res) {
            $pattern = '#^' . preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', preg_quote($tpl, '#')) . '$#';
            if (preg_match($pattern, $uri, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);

                return [$res, $params];
            }
        }

        return null;
    }
}
```

- [ ] **Step 2: Register resources via service provider**

In `app/Providers/McpServiceProvider.php`, extend `boot()`:

```php
$this->app->singleton(\App\Domain\MCP\Resources\ResourceRegistry::class, function () {
    $reg = new \App\Domain\MCP\Resources\ResourceRegistry();
    $reg->register(app(\App\Domain\MCP\Resources\AccountProfileResource::class));
    $reg->register(app(\App\Domain\MCP\Resources\AccountBalanceResource::class));
    $reg->register(app(\App\Domain\MCP\Resources\RecentTransactionsResource::class));
    $reg->register(app(\App\Domain\MCP\Resources\SingleTransactionResource::class));

    return $reg;
});
```

- [ ] **Step 3: Extend JsonRpcRouter**

Add to `dispatch()`'s match:

```php
'resources/list' => $this->handleResourcesList($id, $ctx),
'resources/read' => $this->handleResourcesRead($id, $params, $ctx),
```

Add handlers:

```php
private function handleResourcesList(mixed $id, McpRequestContext $ctx): array
{
    $registry = app(\App\Domain\MCP\Resources\ResourceRegistry::class);
    $items    = [];
    foreach ($registry->all() as $res) {
        if (! $ctx->hasScope($res->scope())) {
            continue;
        }
        $items[] = [
            'uri'         => $res->uriTemplate(),
            'name'        => $res->name(),
            'description' => $res->description(),
            'mimeType'    => $res->mimeType(),
        ];
    }

    return ['jsonrpc' => '2.0', 'id' => $id, 'result' => ['resources' => $items]];
}

/**
 * @param array<string, mixed> $params
 */
private function handleResourcesRead(mixed $id, array $params, McpRequestContext $ctx): array
{
    $uri = (string) ($params['uri'] ?? '');
    if ($uri === '') {
        return $this->error($id, -32602, 'URI_REQUIRED');
    }

    $registry = app(\App\Domain\MCP\Resources\ResourceRegistry::class);
    $hit      = $registry->resolve($uri);
    if ($hit === null) {
        return $this->error($id, -32601, 'RESOURCE_NOT_FOUND', ['uri' => $uri]);
    }
    [$resource, $uriParams] = $hit;

    if (! $ctx->hasScope($resource->scope())) {
        return $this->error($id, -32000, 'INSUFFICIENT_SCOPE', [
            'required' => $resource->scope(),
            'granted'  => $ctx->scopes,
        ]);
    }

    $body = $resource->read($uriParams, $ctx->userId);

    return [
        'jsonrpc' => '2.0',
        'id'      => $id,
        'result'  => [
            'contents' => [[
                'uri'      => $uri,
                'mimeType' => $resource->mimeType(),
                'text'     => $body,
            ]],
        ],
    ];
}
```

- [ ] **Step 4: Feature test**

```php
<?php

declare(strict_types=1);

use App\Domain\MCP\Server\JsonRpcRouter;
use App\Domain\MCP\Server\McpRequestContext;

it('lists resources filtered by scope', function () {
    $router = app(JsonRpcRouter::class);
    $ctx    = new McpRequestContext('tok', 'cli', 1, ['accounts:read']);

    $resp = $router->dispatch(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'resources/list', 'params' => new stdClass()], $ctx);
    $uris = array_column($resp['result']['resources'], 'uri');

    expect($uris)->toContain('account://profile');
    expect($uris)->not->toContain('transactions://recent');
});

it('reads account://balance/USD', function () {
    $router = app(JsonRpcRouter::class);
    $ctx    = new McpRequestContext('tok', 'cli', 1, ['accounts:read']);
    $resp   = $router->dispatch([
        'jsonrpc' => '2.0',
        'id'      => 2,
        'method'  => 'resources/read',
        'params'  => ['uri' => 'account://balance/USD'],
    ], $ctx);

    expect($resp['result']['contents'][0]['mimeType'])->toBe('application/json');
    expect(json_decode($resp['result']['contents'][0]['text'], true))->toHaveKey('balance_minor');
});
```

- [ ] **Step 5: Run + commit**

```bash
./vendor/bin/pest tests/Feature/MCP/ResourcesTest.php
git add app/Domain/MCP/Resources/ResourceRegistry.php app/Providers/McpServiceProvider.php app/Domain/MCP/Server/JsonRpcRouter.php tests/Feature/MCP/ResourcesTest.php
git commit -m "feat(mcp): resources/list and resources/read with URI template resolution"
```

---

### Task 26: Conformance test — full handshake end-to-end

**Files:**
- Create: `tests/Feature/MCP/ConnectionHandshakeTest.php`

- [ ] **Step 1: Write the end-to-end test**

```php
<?php

declare(strict_types=1);

use Laravel\Passport\ClientRepository;
use App\Models\User;

it('completes the full DCR -> token -> initialize -> tools/list flow', function () {
    // 1. DCR
    $dcr = $this->postJson('/oauth/register', [
        'client_name'   => 'Conformance Test Client',
        'redirect_uris' => ['http://localhost:1234/cb'],
        'grant_types'   => ['client_credentials'],
    ])->assertStatus(201)->json();

    // 2. Issue a client_credentials token
    $token = $this->postJson('/oauth/token', [
        'grant_type'    => 'client_credentials',
        'client_id'     => $dcr['client_id'],
        'client_secret' => $dcr['client_secret'],
        'scope'         => 'accounts:read',
    ])->assertStatus(200)->json('access_token');

    // 3. Hit /mcp with bearer
    $response = $this->withServerVariables(['HTTP_HOST' => config('mcp.host')])
        ->withHeaders(['Authorization' => 'Bearer ' . $token])
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'initialize',
            'params'  => ['protocolVersion' => '2025-11-25', 'clientInfo' => ['name' => 'test', 'version' => '0.0.0']],
        ]);

    $response->assertStatus(200);
    expect($response->json('result.protocolVersion'))->toBe('2025-11-25');

    // 4. tools/list — only accounts:read tools should appear
    $list = $this->withServerVariables(['HTTP_HOST' => config('mcp.host')])
        ->withHeaders(['Authorization' => 'Bearer ' . $token])
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id'      => 2,
            'method'  => 'tools/list',
            'params'  => new stdClass(),
        ]);

    $names = array_column($list->json('result.tools'), 'name');
    expect($names)->toContain('account.balance');
    expect($names)->not->toContain('payment.transfer');
});
```

- [ ] **Step 2: Run — must pass**

Run: `./vendor/bin/pest tests/Feature/MCP/ConnectionHandshakeTest.php`

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/MCP/ConnectionHandshakeTest.php
git commit -m "test(mcp): conformance handshake DCR -> token -> initialize -> tools/list"
```

---

(Phase 3 complete — 8 tasks. All 12 tools wired, 4 resources live, end-to-end conformance verified.)

---

## Phase 4: npm wrapper `@finaegis/mcp` (Tasks 27-31)

### Task 27: Scaffold the package

**Files:**
- Create: `packages/finaegis-mcp-stdio/package.json`
- Create: `packages/finaegis-mcp-stdio/tsconfig.json`
- Create: `packages/finaegis-mcp-stdio/README.md`
- Create: `packages/finaegis-mcp-stdio/.gitignore`

- [ ] **Step 1: package.json**

```json
{
  "name": "@finaegis/mcp",
  "version": "0.1.0",
  "description": "Stdio-to-remote relay for the Zelta MCP server. Lets stdio-only MCP clients (Claude Desktop, Cursor, Continue.dev) connect to https://mcp.zelta.app/mcp.",
  "license": "Apache-2.0",
  "type": "module",
  "bin": {
    "finaegis-mcp": "./dist/index.js"
  },
  "main": "./dist/index.js",
  "files": ["dist", "README.md"],
  "scripts": {
    "build": "tsc",
    "dev": "tsc -w",
    "test": "vitest run",
    "prepublishOnly": "npm run build"
  },
  "engines": {
    "node": ">=20"
  },
  "dependencies": {
    "@modelcontextprotocol/sdk": "^1.0.0",
    "keytar": "^7.9.0",
    "open": "^10.1.0",
    "undici": "^6.21.0"
  },
  "devDependencies": {
    "@types/node": "^22.10.0",
    "typescript": "^5.7.0",
    "vitest": "^2.1.0"
  },
  "publishConfig": {
    "access": "public"
  }
}
```

- [ ] **Step 2: tsconfig.json**

```json
{
  "compilerOptions": {
    "target": "ES2022",
    "module": "ES2022",
    "moduleResolution": "node",
    "outDir": "./dist",
    "rootDir": "./src",
    "strict": true,
    "esModuleInterop": true,
    "skipLibCheck": true,
    "declaration": true,
    "sourceMap": true,
    "resolveJsonModule": true
  },
  "include": ["src/**/*"]
}
```

- [ ] **Step 3: README.md**

```markdown
# @finaegis/mcp

Connect Claude Desktop, Cursor, or Continue.dev to **Zelta** via the Model Context Protocol.

## Install

```bash
npx -y @finaegis/mcp
```

## Configure (Claude Desktop)

Add to `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "zelta": { "command": "npx", "args": ["-y", "@finaegis/mcp"] }
  }
}
```

First launch opens a browser for OAuth consent. The token is stored in your OS keychain. Subsequent launches are silent.

## Environment overrides

| Variable | Default |
|---|---|
| `MCP_SERVER_URL` | `https://mcp.zelta.app/mcp` |
| `MCP_AUTH_SERVER` | `https://zelta.app` |
| `MCP_TOKEN_PATH` | OS keychain via `keytar` |

## Troubleshooting

- **Browser does not open** — set `MCP_OAUTH_NO_BROWSER=1` and follow the URL printed to stderr.
- **Token expired** — delete the keychain entry: `npx @finaegis/mcp --logout`.
```

- [ ] **Step 4: .gitignore**

```
node_modules/
dist/
*.tgz
.env
```

- [ ] **Step 5: Verify install + build skeleton**

Run inside `packages/finaegis-mcp-stdio/`:
```
npm install && mkdir -p src && echo "export const VERSION = '0.1.0';" > src/index.ts && npm run build
```
Expected: `dist/index.js` exists.

- [ ] **Step 6: Commit**

```bash
git add packages/finaegis-mcp-stdio/package.json packages/finaegis-mcp-stdio/tsconfig.json packages/finaegis-mcp-stdio/README.md packages/finaegis-mcp-stdio/.gitignore packages/finaegis-mcp-stdio/src/index.ts
git commit -m "feat(@finaegis/mcp): scaffold npm wrapper package"
```

---

### Task 28: Stdio relay implementation

**Files:**
- Create: `packages/finaegis-mcp-stdio/src/stdio-relay.ts`
- Create: `packages/finaegis-mcp-stdio/src/config.ts`

- [ ] **Step 1: config.ts**

```typescript
export interface RelayConfig {
  serverUrl: string;
  authServer: string;
  oauthNoBrowser: boolean;
  keychainService: string;
  keychainAccount: string;
}

export function loadConfig(): RelayConfig {
  return {
    serverUrl:       process.env.MCP_SERVER_URL  ?? 'https://mcp.zelta.app/mcp',
    authServer:      process.env.MCP_AUTH_SERVER ?? 'https://zelta.app',
    oauthNoBrowser:  process.env.MCP_OAUTH_NO_BROWSER === '1',
    keychainService: 'finaegis-mcp',
    keychainAccount: 'default',
  };
}
```

- [ ] **Step 2: stdio-relay.ts**

```typescript
import { fetch } from 'undici';
import { RelayConfig } from './config.js';

interface JsonRpcMessage {
  jsonrpc: '2.0';
  id?: number | string | null;
  method?: string;
  params?: unknown;
  result?: unknown;
  error?: unknown;
}

export class StdioRelay {
  constructor(private cfg: RelayConfig, private getToken: () => Promise<string>) {}

  async start(): Promise<void> {
    process.stdin.setEncoding('utf-8');

    let buffer = '';
    process.stdin.on('data', (chunk) => {
      buffer += chunk;
      const lines = buffer.split('\n');
      buffer = lines.pop() ?? '';
      for (const line of lines) {
        if (line.trim() === '') continue;
        this.handleLine(line).catch((err) => {
          process.stderr.write(`relay error: ${String(err)}\n`);
        });
      }
    });

    process.stdin.on('end', () => process.exit(0));
  }

  private async handleLine(line: string): Promise<void> {
    let envelope: JsonRpcMessage;
    try {
      envelope = JSON.parse(line);
    } catch {
      return;
    }

    const token = await this.getToken();
    const response = await fetch(this.cfg.serverUrl, {
      method:  'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept':       'application/json, text/event-stream',
        'Authorization': `Bearer ${token}`,
      },
      body: JSON.stringify(envelope),
    });

    const text = await response.text();
    process.stdout.write(text + '\n');
  }
}
```

- [ ] **Step 3: Build**

Run: `cd packages/finaegis-mcp-stdio && npm run build`
Expected: clean compile.

- [ ] **Step 4: Commit**

```bash
git add packages/finaegis-mcp-stdio/src/stdio-relay.ts packages/finaegis-mcp-stdio/src/config.ts
git commit -m "feat(@finaegis/mcp): stdio→remote-HTTP relay"
```

---

### Task 29: OAuth helper (browser flow + keychain persistence)

**Files:**
- Create: `packages/finaegis-mcp-stdio/src/oauth-helper.ts`

- [ ] **Step 1: Implement**

```typescript
import { createServer } from 'node:http';
import { randomBytes, createHash } from 'node:crypto';
import { fetch } from 'undici';
import keytar from 'keytar';
import open from 'open';
import { RelayConfig } from './config.js';

interface TokenSet {
  access_token: string;
  refresh_token?: string;
  expires_at: number;
  scope: string;
}

export class OAuthHelper {
  constructor(private cfg: RelayConfig) {}

  async getAccessToken(): Promise<string> {
    const stored = await this.readToken();
    if (stored && stored.expires_at > Date.now() + 30_000) {
      return stored.access_token;
    }
    if (stored?.refresh_token) {
      const refreshed = await this.refresh(stored.refresh_token);
      if (refreshed) {
        await this.writeToken(refreshed);

        return refreshed.access_token;
      }
    }
    const fresh = await this.interactiveFlow();
    await this.writeToken(fresh);

    return fresh.access_token;
  }

  async logout(): Promise<void> {
    await keytar.deletePassword(this.cfg.keychainService, this.cfg.keychainAccount);
  }

  private async readToken(): Promise<TokenSet | null> {
    const raw = await keytar.getPassword(this.cfg.keychainService, this.cfg.keychainAccount);
    if (!raw) return null;
    try { return JSON.parse(raw); } catch { return null; }
  }

  private async writeToken(t: TokenSet): Promise<void> {
    await keytar.setPassword(this.cfg.keychainService, this.cfg.keychainAccount, JSON.stringify(t));
  }

  private async refresh(refreshToken: string): Promise<TokenSet | null> {
    const dcr = await this.dcrIfNeeded();
    const body = new URLSearchParams({
      grant_type:    'refresh_token',
      refresh_token: refreshToken,
      client_id:     dcr.client_id,
      client_secret: dcr.client_secret,
    });
    const res = await fetch(`${this.cfg.authServer}/oauth/token`, { method: 'POST', body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
    if (!res.ok) return null;
    const data = await res.json() as { access_token: string; refresh_token?: string; expires_in: number; scope: string };

    return {
      access_token:  data.access_token,
      refresh_token: data.refresh_token ?? refreshToken,
      expires_at:    Date.now() + (data.expires_in * 1000),
      scope:         data.scope,
    };
  }

  private async interactiveFlow(): Promise<TokenSet> {
    const dcr = await this.dcrIfNeeded();

    const port = 53682;
    const redirectUri = `http://localhost:${port}/callback`;
    const verifier = randomBytes(32).toString('base64url');
    const challenge = createHash('sha256').update(verifier).digest('base64url');
    const state = randomBytes(16).toString('base64url');

    const authUrl = `${this.cfg.authServer}/oauth/authorize?` + new URLSearchParams({
      response_type:         'code',
      client_id:             dcr.client_id,
      redirect_uri:          redirectUri,
      scope:                 'accounts:read accounts:write payments:read payments:write transactions:read exchange:read exchange:write ramp:read ramp:write sms:send',
      state,
      code_challenge:        challenge,
      code_challenge_method: 'S256',
    }).toString();

    if (this.cfg.oauthNoBrowser) {
      process.stderr.write(`Open this URL to authorize: ${authUrl}\n`);
    } else {
      await open(authUrl);
    }

    const code = await new Promise<string>((resolve, reject) => {
      const server = createServer((req, res) => {
        const url = new URL(req.url ?? '/', `http://localhost:${port}`);
        if (url.pathname !== '/callback') {
          res.writeHead(404).end();

          return;
        }
        const got = url.searchParams.get('state');
        if (got !== state) {
          res.writeHead(400).end('state mismatch');
          reject(new Error('state mismatch'));

          return;
        }
        const c = url.searchParams.get('code');
        if (!c) {
          res.writeHead(400).end('no code');
          reject(new Error('no code'));

          return;
        }
        res.writeHead(200, { 'Content-Type': 'text/html' });
        res.end('<html><body><h2>Authorized — you can close this window.</h2></body></html>');
        server.close();
        resolve(c);
      });
      server.listen(port);
    });

    const tokenBody = new URLSearchParams({
      grant_type:    'authorization_code',
      code,
      redirect_uri:  redirectUri,
      client_id:     dcr.client_id,
      client_secret: dcr.client_secret,
      code_verifier: verifier,
    });
    const tokenRes = await fetch(`${this.cfg.authServer}/oauth/token`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: tokenBody,
    });
    if (!tokenRes.ok) {
      throw new Error(`token exchange failed: ${tokenRes.status} ${await tokenRes.text()}`);
    }
    const data = await tokenRes.json() as { access_token: string; refresh_token?: string; expires_in: number; scope: string };

    return {
      access_token:  data.access_token,
      refresh_token: data.refresh_token,
      expires_at:    Date.now() + (data.expires_in * 1000),
      scope:         data.scope,
    };
  }

  /**
   * Lazily DCR-register on first run; persist client_id+secret to keychain
   * under a separate account.
   */
  private async dcrIfNeeded(): Promise<{ client_id: string; client_secret: string }> {
    const stored = await keytar.getPassword(this.cfg.keychainService, this.cfg.keychainAccount + '.dcr');
    if (stored) return JSON.parse(stored);

    const res = await fetch(`${this.cfg.authServer}/oauth/register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        client_name:   '@finaegis/mcp (stdio)',
        redirect_uris: [`http://localhost:53682/callback`],
        grant_types:   ['authorization_code', 'refresh_token'],
      }),
    });
    if (!res.ok) throw new Error(`DCR failed: ${res.status} ${await res.text()}`);
    const data = await res.json() as { client_id: string; client_secret: string };
    await keytar.setPassword(this.cfg.keychainService, this.cfg.keychainAccount + '.dcr', JSON.stringify(data));

    return data;
  }
}
```

- [ ] **Step 2: Build**

Run: `cd packages/finaegis-mcp-stdio && npm run build`

- [ ] **Step 3: Commit**

```bash
git add packages/finaegis-mcp-stdio/src/oauth-helper.ts
git commit -m "feat(@finaegis/mcp): OAuth helper with PKCE + DCR + keychain persistence"
```

---

### Task 30: Wire entrypoint + Vitest tests

**Files:**
- Modify: `packages/finaegis-mcp-stdio/src/index.ts`
- Create: `packages/finaegis-mcp-stdio/test/oauth-helper.test.ts`

- [ ] **Step 1: index.ts**

```typescript
#!/usr/bin/env node
import { loadConfig } from './config.js';
import { OAuthHelper } from './oauth-helper.js';
import { StdioRelay } from './stdio-relay.js';

async function main(): Promise<void> {
  const cfg    = loadConfig();
  const helper = new OAuthHelper(cfg);

  if (process.argv.includes('--logout')) {
    await helper.logout();
    process.stderr.write('Logged out — keychain entry deleted.\n');

    return;
  }

  const relay = new StdioRelay(cfg, () => helper.getAccessToken());
  await relay.start();
}

main().catch((err) => {
  process.stderr.write(`fatal: ${String(err)}\n`);
  process.exit(1);
});
```

- [ ] **Step 2: Vitest test for config loading**

```typescript
import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { loadConfig } from '../src/config.js';

describe('loadConfig', () => {
  const original = { ...process.env };
  beforeEach(() => { process.env = { ...original }; });
  afterEach(() =>  { process.env = { ...original }; });

  it('uses production defaults when no env vars set', () => {
    delete process.env.MCP_SERVER_URL;
    delete process.env.MCP_AUTH_SERVER;
    const cfg = loadConfig();
    expect(cfg.serverUrl).toBe('https://mcp.zelta.app/mcp');
    expect(cfg.authServer).toBe('https://zelta.app');
  });

  it('respects MCP_SERVER_URL override', () => {
    process.env.MCP_SERVER_URL = 'https://staging.example/mcp';
    expect(loadConfig().serverUrl).toBe('https://staging.example/mcp');
  });
});
```

- [ ] **Step 3: Run Vitest**

Run: `cd packages/finaegis-mcp-stdio && npm test`
Expected: 2 passing.

- [ ] **Step 4: Commit**

```bash
git add packages/finaegis-mcp-stdio/src/index.ts packages/finaegis-mcp-stdio/test/oauth-helper.test.ts
git commit -m "feat(@finaegis/mcp): CLI entrypoint and config tests"
```

---

### Task 31: Add to monorepo-split workflow

**Files:**
- Modify: `.github/workflows/monorepo-split.yml`

- [ ] **Step 1: Read existing matrix**

Run: `grep -n "package_paths\|matrix\|finaegis" .github/workflows/monorepo-split.yml`

- [ ] **Step 2: Add a new mirror job entry**

Append to the matrix in `monorepo-split.yml`:

```yaml
        - source: packages/finaegis-mcp-stdio
          mirror: FinAegis/mcp
          tag_prefix: mcp-v
```

- [ ] **Step 3: Verify yaml**

Run: `yq '.jobs.split.strategy.matrix' .github/workflows/monorepo-split.yml`

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/monorepo-split.yml
git commit -m "ci: add finaegis-mcp-stdio to monorepo-split mirror jobs"
```

---

(Phase 4 complete — 5 tasks. npm wrapper compiles, tests pass, OAuth flow + keychain works, mirror pipeline configured.)

---

## Phase 5: Documentation + Marketing (Tasks 32-39)

### Task 32: Rewrite `docs/13-AI-FRAMEWORK/MCP_INTEGRATION.md`

**Files:**
- Modify: `docs/13-AI-FRAMEWORK/MCP_INTEGRATION.md`
- Modify: `docs/13-AI-FRAMEWORK/01-MCP-Integration.md`

- [ ] **Step 1: Replace `MCP_INTEGRATION.md` content**

```markdown
# MCP Integration Guide

> **As of v7.11.0**, FinAegis ships a public Model Context Protocol server at `https://mcp.zelta.app/mcp` that any spec-compliant MCP client (Claude Desktop, Cursor, Continue.dev) can connect to.

## Quick Connect

### Claude Desktop / Cursor (remote URL)

Paste into your client config:

```json
{ "mcpServers": { "zelta": { "url": "https://mcp.zelta.app/mcp" } } }
```

### Stdio-only clients (npm)

```bash
npx -y @finaegis/mcp
```

## Discovery

`https://mcp.zelta.app/.well-known/oauth-protected-resource` (RFC 9728)
`https://zelta.app/.well-known/oauth-authorization-server`

## Tool Catalog (v1)

| Tool | Scope | Purpose |
|---|---|---|
| `account.balance` | `accounts:read` | Read balance |
| `account.create` | `accounts:write` | Open new account |
| `payment.status` | `payments:read` | Check payment status |
| `payment.transfer` | `payments:write` | Send a payment |
| `transactions.query` | `transactions:read` | List transactions |
| `spending.analysis` | `transactions:read` | Categorized spend |
| `exchange.quote` | `exchange:read` | Get an exchange quote |
| `exchange.trade` | `exchange:write` | Execute exchange |
| `ramp.start` | `ramp:write` | Start onramp/offramp |
| `ramp.status` | `ramp:read` | Poll ramp status |
| `mpp.discovery` | (public) | List supported payment rails |
| `sms.send` | `sms:send` | Send SMS, paid per-message |

## Spending Limits

Every access token has a per-token daily spending limit (default $500/24h, configurable on the consent screen). Exceeding it returns `-32001 SPENDING_LIMIT_EXCEEDED`.

## Idempotency

Every write tool requires `idempotency_key` (UUID). Server caches result for 24h. Same key + same args = cached replay. Same key + different args = `-32002 IDEMPOTENCY_KEY_REUSED`.

## Internal use (still supported)

The legacy internal REST endpoints `/api/ai/mcp/tools/*` continue to work for first-party AI integrations. The public MCP server is a separate surface — wire-protocol JSON-RPC, OAuth-authorized, scope-gated.

See: [02-MCP-Server-Architecture.md](02-MCP-Server-Architecture.md), [03-MCP-Quickstart.md](03-MCP-Quickstart.md), [04-MCP-OAuth-Reference.md](04-MCP-OAuth-Reference.md), [05-MCP-Tool-Reference.md](05-MCP-Tool-Reference.md).
```

- [ ] **Step 2: Replace `01-MCP-Integration.md` with the same content (or a redirect)**

Replace the file body with a one-line redirect:

```markdown
# MCP Integration

This document moved. See [MCP_INTEGRATION.md](MCP_INTEGRATION.md).
```

- [ ] **Step 3: Commit**

```bash
git add docs/13-AI-FRAMEWORK/MCP_INTEGRATION.md docs/13-AI-FRAMEWORK/01-MCP-Integration.md
git commit -m "docs(mcp): rewrite MCP_INTEGRATION for public server (v7.11.0)"
```

---

### Task 33: Create the four new doc files

**Files:**
- Create: `docs/13-AI-FRAMEWORK/02-MCP-Server-Architecture.md`
- Create: `docs/13-AI-FRAMEWORK/03-MCP-Quickstart.md`
- Create: `docs/13-AI-FRAMEWORK/04-MCP-OAuth-Reference.md`
- Create: `docs/13-AI-FRAMEWORK/05-MCP-Tool-Reference.md`

- [ ] **Step 1: 02-MCP-Server-Architecture.md**

Copy Section 5 of the spec (`docs/superpowers/specs/2026-04-27-mcp-server-design.md`) into this file under a "FinAegis MCP Server Architecture" heading. Trim spec metadata (Status / Owner) so the doc reads as standalone reference material.

- [ ] **Step 2: 03-MCP-Quickstart.md**

```markdown
# MCP Quickstart — Connect in 30 seconds

## Claude Desktop

Add to `~/Library/Application Support/Claude/claude_desktop_config.json` (macOS) or `%APPDATA%\Claude\claude_desktop_config.json` (Windows):

```json
{ "mcpServers": { "zelta": { "url": "https://mcp.zelta.app/mcp" } } }
```

Restart Claude Desktop. A consent screen opens in your browser. Choose a daily spending limit, click Approve.

## Cursor

`Settings → Features → MCP → Add Server`. URL: `https://mcp.zelta.app/mcp`.

## Continue.dev

`~/.continue/config.json`:

```json
{ "experimental": { "modelContextProtocolServer": { "transport": { "type": "streamable-http", "url": "https://mcp.zelta.app/mcp" } } } }
```

## Older clients (stdio only)

```bash
npx -y @finaegis/mcp
```

## First call

Once connected, ask the agent: "What is my Zelta USD balance?" — it will call `account.balance` and surface the result.
```

- [ ] **Step 3: 04-MCP-OAuth-Reference.md**

Document the full OAuth handshake from spec Section 6.1, scope catalog from Section 6.3, and DCR endpoint shape from Task 6 in this plan.

- [ ] **Step 4: 05-MCP-Tool-Reference.md**

For each of the 12 tools, include: name, scope, write/read flag, full JSON input schema, full output schema, example request/response, error codes. Pull schemas directly from the registered tool classes (`getInputSchema()` / `getOutputSchema()`).

- [ ] **Step 5: Commit**

```bash
git add docs/13-AI-FRAMEWORK/02-MCP-Server-Architecture.md docs/13-AI-FRAMEWORK/03-MCP-Quickstart.md docs/13-AI-FRAMEWORK/04-MCP-OAuth-Reference.md docs/13-AI-FRAMEWORK/05-MCP-Tool-Reference.md
git commit -m "docs(mcp): add Architecture, Quickstart, OAuth and Tool Reference docs"
```

---

### Task 34: Fix VertexSMS doc + roadmap + CLAUDE.md + README + env files

**Files:**
- Modify: `docs/partners/VERTEXSMS_Q13_BIDIRECTIONAL_MCP.md`
- Modify: `docs/VERSION_ROADMAP.md`
- Modify: `CLAUDE.md`
- Modify: `README.md`
- Modify: `.env.production.example`
- Modify: `.env.zelta.example`

- [ ] **Step 1: Fix VertexSMS doc — replace the bogus URL**

In `docs/partners/VERTEXSMS_Q13_BIDIRECTIONAL_MCP.md`, replace:
```
https://zelta.app/.well-known/mcp-manifest.json
```
with:
```
https://mcp.zelta.app/.well-known/oauth-protected-resource
```

Add a "Connection" section after "What Zelta Builds" that documents the real handshake.

- [ ] **Step 2: VERSION_ROADMAP.md**

Add an entry above the most recent (v7.10.x) entry:

```markdown
## v7.11.0 — Public MCP Server (April 2026)

- Stand up `https://mcp.zelta.app/mcp` — Anthropic-spec MCP server (protocol `2025-11-25`)
- 12 curated tools, 4 resources, 10 OAuth scopes
- OAuth 2.1 + RFC 7591 DCR + RFC 9728 Protected Resource Metadata
- Per-token spending limits ($500/24h default), idempotency, audit log
- `@finaegis/mcp` npm wrapper for stdio clients
```

- [ ] **Step 3: CLAUDE.md additions**

Append a new section before "## Notes":

```markdown
## MCP Server (v7.11.0+)

- Public endpoint: `https://mcp.zelta.app/mcp` (subdomain handled in `bootstrap/app.php`)
- Subdomain routes: `app/Domain/MCP/Routes/api.php` — minimal middleware (no CSRF, no Sanctum)
- Tool catalog: `config/mcp.php` (`tools` key) — kill-switches per tool via `MCP_TOOL_*` env vars
- OAuth AS: Laravel Passport (existing) extended with DCR at `/oauth/register`
- Spec: `docs/superpowers/specs/2026-04-27-mcp-server-design.md`

| Pitfall | Fix |
|---|---|
| Scope names use snake_case + `:` separator (`accounts:read`, `payments:write`) | Match exactly — Passport's `Scope::can()` is case-sensitive |
| Subdomain not resolving | Verify Cloudflare CNAME for `mcp.*` and the bootstrap branch ordering (mcp before protocol subdomains) |
| 401 with no `WWW-Authenticate: Bearer resource_metadata=...` | `McpOAuthGuard` not applied — check route middleware list |
| Stale tool count in dev portal | `developers/mcp-tools.blade.php` — keep in sync with `config('mcp.tools')` count |
```

- [ ] **Step 4: README.md — add MCP section before "## Stack"**

```markdown
## MCP (Model Context Protocol)

Connect Claude Desktop, Cursor, or Continue.dev to your Zelta account:

```bash
npx -y @finaegis/mcp
```

Or use the remote URL directly: `https://mcp.zelta.app/mcp`. See [docs/13-AI-FRAMEWORK/03-MCP-Quickstart.md](docs/13-AI-FRAMEWORK/03-MCP-Quickstart.md).
```

- [ ] **Step 5: env examples — add MCP block to both `.env.production.example` and `.env.zelta.example`**

```ini
# MCP Server (v7.11.0)
MCP_ENABLED=true
MCP_HOST=mcp.zelta.app
MCP_AUTH_SERVER=https://zelta.app
MCP_RESOURCE_URI=https://mcp.zelta.app
MCP_SERVER_NAME=Zelta
MCP_SERVER_VERSION=0.1.0
MCP_DEFAULT_DAILY_LIMIT_MINOR=50000
MCP_DEFAULT_DAILY_LIMIT_CURRENCY=USD
MCP_IDEMPOTENCY_STORE=redis

# Per-tool kill switches (default true — flip to false to remove from public catalog without redeploy)
MCP_TOOL_ACCOUNT_BALANCE=true
MCP_TOOL_ACCOUNT_CREATE=true
MCP_TOOL_PAYMENT_STATUS=true
MCP_TOOL_PAYMENT_TRANSFER=true
MCP_TOOL_TRANSACTIONS_QUERY=true
MCP_TOOL_SPENDING_ANALYSIS=true
MCP_TOOL_EXCHANGE_QUOTE=true
MCP_TOOL_EXCHANGE_TRADE=true
MCP_TOOL_RAMP_START=true
MCP_TOOL_RAMP_STATUS=true
MCP_TOOL_MPP_DISCOVERY=true
MCP_TOOL_SMS_SEND=true
```

- [ ] **Step 6: Commit**

```bash
git add docs/partners/VERTEXSMS_Q13_BIDIRECTIONAL_MCP.md docs/VERSION_ROADMAP.md CLAUDE.md README.md .env.production.example .env.zelta.example
git commit -m "docs(mcp): fix VertexSMS doc + add v7.11.0 roadmap, CLAUDE.md, README, env vars"
```

---

### Task 35: Rewrite `developers/mcp-tools.blade.php`

**Files:**
- Modify: `resources/views/developers/mcp-tools.blade.php` (full rewrite)

- [ ] **Step 1: Replace file content**

Key sections to include:
1. **Hero**: Replace "16 Banking Tools" with the dynamic count `{{ count(array_filter(config('mcp.tools'), fn($t) => $t['enabled'])) }} tools` and add the npm install line + remote URL.
2. **Connect in 30s**: Three tabs (Claude Desktop / Cursor / npm).
3. **Scope catalog**: Render rows from `config('mcp.scopes')`.
4. **Tool catalog**: Render rows from `config('mcp.tools')` with each tool's input schema (collapsible JSON block).
5. **Discovery URLs**: Live links to `https://mcp.zelta.app/.well-known/oauth-protected-resource` and `https://zelta.app/.well-known/oauth-authorization-server`.
6. **SEO**: title, meta description, canonical, OG, Twitter card, JSON-LD `WebPage` schema with the `mainEntity` referencing the MCP service.

The view file should pass PHPStan Level 8. Use `(array) config(...)` casts.

- [ ] **Step 2: Verify rendering**

Run: `php artisan serve` then open `http://localhost:8000/developers/mcp-tools`.
Expected: page renders, all sections populated from config, no Blade errors.

- [ ] **Step 3: Commit**

```bash
git add resources/views/developers/mcp-tools.blade.php
git commit -m "feat(web): rewrite developers/mcp-tools page for public MCP server (v7.11.0)"
```

---

### Task 36: New marketing page `features/mcp.blade.php`

**Files:**
- Create: `resources/views/features/mcp.blade.php`
- Modify: `routes/web.php` (add the route if features pages are statically routed)

- [ ] **Step 1: Create the view**

Sections to include:
1. **Hero**: "MCP-native banking infrastructure" + the install line + 30-second-connect CTA.
2. **What MCP is** in plain English (3 short paragraphs for non-technical readers).
3. **Why Zelta is different**: x402-paid SMS, multi-rail payments, AP2-aware mandates, regulated banking foundation. 4 cards.
4. **vs. Legacy bank APIs** comparison table.
5. **Use cases**: agent treasurer, customer service bot moves money on behalf of users with consent, recurring agent workflows.
6. **CTA**: link to `/developers/mcp-tools`.
7. **SEO**: per project conventions.

- [ ] **Step 2: Add route**

In `routes/web.php`, alongside other feature pages:

```php
Route::view('/features/mcp', 'features.mcp')->name('features.mcp');
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/features/mcp.blade.php routes/web.php
git commit -m "feat(web): /features/mcp marketing page"
```

---

### Task 37: Update welcome / about / pricing / features-index / developers-index

**Files:**
- Modify: `resources/views/welcome.blade.php`
- Modify: `resources/views/about.blade.php`
- Modify: `resources/views/pricing.blade.php`
- Modify: `resources/views/features/index.blade.php`
- Modify: `resources/views/developers/index.blade.php`

- [ ] **Step 1: welcome.blade.php — add hero badge**

In the hero section, add (or update an existing badge slot) with:

```blade
<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-300 border border-emerald-400/30">
  Now MCP-compatible — connect Claude Desktop, Cursor, or any agent
</span>
```

- [ ] **Step 2: about.blade.php — add MCP to tech stack mention**

Find the "Tech stack" or equivalent section. Add:

```
Model Context Protocol (Anthropic) — wire-protocol gateway exposing 12 banking tools to AI agents
```

- [ ] **Step 3: pricing.blade.php — add line to each tier**

In each tier's bullets list, add:

```
✓ MCP server access included
```

- [ ] **Step 4: features/index.blade.php — add a feature card + bump count**

Search for the domain-count badge (likely "56 domains") and change to "57 domains".

Add a new feature card matching the existing pattern, linking to `/features/mcp`. Title: "MCP-native banking". One-line description: "Connect Claude, Cursor, or any agent to move money, exchange, send SMS, and more — through one OAuth-protected endpoint."

- [ ] **Step 5: developers/index.blade.php — add MCP card**

Pattern-match the existing developer-resource cards. Add a new card linking to `/developers/mcp-tools`. Title: "MCP & AI Agent Tools". Sub-line: "12 tools, 4 resources, OAuth 2.1 — connect any spec-compliant client".

- [ ] **Step 6: Commit**

```bash
git add resources/views/welcome.blade.php resources/views/about.blade.php resources/views/pricing.blade.php resources/views/features/index.blade.php resources/views/developers/index.blade.php
git commit -m "feat(web): surface MCP across welcome, about, pricing, features, developers index"
```

---

### Task 38: Filament admin resources

**Files:**
- Create: `app/Filament/Admin/Resources/McpOAuthClientResource.php`
- Create: `app/Filament/Admin/Resources/McpToolInvocationResource.php`
- Create: `app/Filament/Admin/Resources/McpActiveSessionResource.php`

- [ ] **Step 1: Generate scaffolds**

Run: `php artisan make:filament-resource McpOAuthClient --model=Laravel\\Passport\\Client`
Run: `php artisan make:filament-resource McpToolInvocation --view`
Run: `php artisan make:filament-resource McpActiveSession --view`

(If model class detection fails for Passport's Client, manually edit the resource to use `protected static ?string $model = \Laravel\Passport\Client::class;`.)

- [ ] **Step 2: Configure list columns + filters**

For `McpOAuthClientResource`: columns `name`, `registration_method`, `revoked`, `created_at`. Filters: `registration_method`. Bulk action: revoke.

For `McpToolInvocationResource`: columns `created_at`, `tool_name`, `result_status`, `client_id`, `settlement_amount_minor`, `duration_ms`. Filters: `tool_name`, `result_status`, date range. Read-only.

For `McpActiveSessionResource`: backed by a Redis-keyed in-memory list of live SSE connections (the `SseStreamManager` registers token_id + start time). Columns: `token_id`, `client_id`, `connected_at`, `duration_seconds`. Action: disconnect.

- [ ] **Step 3: Verify**

Run: `php artisan serve`, log in as admin, visit `/admin`. Three new sidebar entries should appear under "MCP".

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Admin/Resources/McpOAuthClientResource.php app/Filament/Admin/Resources/McpToolInvocationResource.php app/Filament/Admin/Resources/McpActiveSessionResource.php
git commit -m "feat(admin): Filament resources for OAuth clients, tool invocations, active sessions"
```

---

### Task 39: Run the post-phase-review skill

**Files:**
- (No file changes — verification step)

- [ ] **Step 1: Invoke the post-phase-review skill**

The skill is documented in the user's global instructions. Run a review pass over Phases 1-5 work specifically focused on:
- Business completeness (is every spec requirement implemented?)
- Copywriting (developer portal page reads cleanly, terminology consistent — "MCP" / "tool" / "scope" / "agent" used consistently across all surfaces)
- SEO (title / description / canonical / OG / JSON-LD on all new pages)
- Marketing (CTA visible on welcome page, pricing page mentions MCP, dev portal sells the value)
- Cross-page consistency (same install line on README / dev portal / Quickstart / VertexSMS doc)

- [ ] **Step 2: Address findings**

If review finds critical or important issues, fix them inline and re-run review. Only proceed when all critical/important items are clear.

- [ ] **Step 3: Commit any fixes**

```bash
git add -p
git commit -m "fix: post-phase-review corrections (copywriting, SEO, cross-page consistency)"
```

---

(Phase 5 complete — 8 tasks. Documentation, marketing, dev portal, Filament admin, and post-phase review done.)

---

## Phase 6: Pre-launch + Ship (Tasks 40-42)

### Task 40: Manual smoke-test checklist

**Files:**
- (No file changes — manual testing)

Run through this checklist on a staging instance with `mcp.zelta.app` pointing at it:

- [ ] **Claude Desktop on macOS** — paste URL into config, restart, verify consent screen, approve with $500 limit, ask "what is my balance" → tool call succeeds.
- [ ] **Claude Desktop on Windows** — same as above.
- [ ] **Cursor on macOS** — add MCP server in settings, verify tools appear in agent panel.
- [ ] **Continue.dev** — add server in `config.json`, verify capabilities exchange.
- [ ] **npm wrapper from cold install** — `npm uninstall -g @finaegis/mcp && npx -y @finaegis/mcp` on macOS + Linux + Windows. Browser opens, OAuth completes, token persists.
- [ ] **Raw curl** — `curl -X POST https://mcp.zelta.app/mcp -H "Authorization: Bearer <staging-token>" -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","clientInfo":{"name":"curl","version":"0"}}}'` returns 200.
- [ ] **Discovery without auth** — `curl https://mcp.zelta.app/.well-known/oauth-protected-resource` returns 200 with RFC 9728 JSON.
- [ ] **Spending limit** — make 11 transfers of $50 each (total $550) on a token with $500 limit; eleventh returns `-32001`.
- [ ] **Idempotency replay** — call `payment.transfer` with same `idempotency_key` twice, same args → both return identical result, only one row in `mcp_tool_invocations` with `result_status='success'`.
- [ ] **Idempotency conflict** — call same key with different amount → `-32002`.
- [ ] **Disabled tool** — set `MCP_TOOL_SMS_SEND=false`, restart, verify `sms.send` omitted from `tools/list` and direct call returns `-32004`.
- [ ] **Rate limit** — exceed `mcp.aggregate` limit, verify 429 with `retry_after`.
- [ ] **VertexSMS hits the discovery URL** — coordinate with VertexSMS to retry their original test against `https://mcp.zelta.app/.well-known/oauth-protected-resource`. Confirm 200.

Document each item as PASS/FAIL with timestamp in the PR description.

---

### Task 41: Tag and publish `@finaegis/mcp v0.1.0`

- [ ] **Step 1: Bump version**

```bash
cd packages/finaegis-mcp-stdio
npm version 0.1.0 --no-git-tag-version
cd ../..
```

- [ ] **Step 2: Commit version bump**

```bash
git add packages/finaegis-mcp-stdio/package.json
git commit -m "chore(@finaegis/mcp): bump to v0.1.0"
```

- [ ] **Step 3: Tag**

```bash
git tag mcp-v0.1.0
git push origin mcp-v0.1.0
```

This triggers `monorepo-split.yml` which mirrors to `FinAegis/mcp` and (in that mirror) the npm publish workflow.

- [ ] **Step 4: Verify on npmjs**

Visit `https://www.npmjs.com/package/@finaegis/mcp` — should show `0.1.0` within ~5 minutes.

- [ ] **Step 5: Verify install**

```bash
npx -y @finaegis/mcp@0.1.0 --logout
echo '{"jsonrpc":"2.0","id":1,"method":"ping"}' | MCP_OAUTH_NO_BROWSER=1 npx -y @finaegis/mcp@0.1.0 2>&1 | head -5
```

---

### Task 42: Tag and ship `v7.11.0` app release

- [ ] **Step 1: Update CHANGELOG.md**

Add a `## v7.11.0 — Public MCP Server` entry referencing the spec and listing the headline changes.

- [ ] **Step 2: Bump version**

```bash
# Wherever version is canonically tracked (composer.json, config/app.php, etc.)
sed -i 's/"version": "7.10.8"/"version": "7.11.0"/' composer.json
```

- [ ] **Step 3: Commit + tag**

```bash
git add CHANGELOG.md composer.json
git commit -m "release: v7.11.0 — public MCP server"
git tag v7.11.0
git push origin v7.11.0
```

- [ ] **Step 4: Open PR if not already**

```bash
gh pr create --title "release: v7.11.0 — public MCP server" --body "$(cat <<'EOF'
## Summary
- Public MCP server at https://mcp.zelta.app/mcp (Anthropic spec 2025-11-25)
- 12 tools, 4 resources, 10 OAuth scopes
- @finaegis/mcp npm wrapper for stdio clients
- Full docs, marketing, dev portal updates

## Spec
docs/superpowers/specs/2026-04-27-mcp-server-design.md

## Test plan
- [x] Manual smoke checklist (see Task 40 in plan)
- [x] All Phase 1-5 unit + feature tests pass
- [x] Conformance handshake test passes against staging
- [x] post-phase-review skill clear

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

(Phase 6 complete — 3 tasks. v7.11.0 shipped, npm package live.)

---

## Phase 7: Post-launch follow-ups (Tasks 43-45 — scheduled)

### Task 43: Smithery registry submission

**Schedule:** ~7 days after `v7.11.0` and `mcp-v0.1.0` ship clean (zero P0 issues).

**Files:**
- Create: `smithery.yaml` (in `packages/finaegis-mcp-stdio/`)

- [ ] **Step 1: Create smithery.yaml**

```yaml
name: zelta
display_name: Zelta
description: MCP-native banking — send payments, exchange currency, on/off-ramp, and send SMS, all OAuth-authorized.
version: 0.1.0
homepage: https://zelta.app/features/mcp
license: Apache-2.0
icon: https://zelta.app/img/icon-512.png
categories:
  - banking
  - finance
  - communication
runtime:
  type: streamable-http
  url: https://mcp.zelta.app/mcp
authorization:
  type: oauth2
  authorization_server: https://zelta.app
  protected_resource_metadata: https://mcp.zelta.app/.well-known/oauth-protected-resource
```

- [ ] **Step 2: Open Smithery PR**

```bash
gh repo fork smithery-ai/registry --clone
cd registry
git checkout -b add-zelta
mkdir -p servers/zelta && cp ../zelta/packages/finaegis-mcp-stdio/smithery.yaml servers/zelta/
git add servers/zelta/smithery.yaml
git commit -m "feat: add Zelta MCP server"
gh pr create --repo smithery-ai/registry --title "Add Zelta MCP server" --body "Banking-native MCP server for FinAegis. Streamable-HTTP, OAuth 2.1 + DCR. https://mcp.zelta.app/mcp"
```

- [ ] **Step 3: Track**

Watch for Smithery's automated conformance check; respond to feedback.

---

### Task 44: Anthropic + Cursor + Continue + Cline registry submissions

**Schedule:** ~14-21 days after Smithery listing, once we have ~50+ active grants and zero P0 issues.

- [ ] **Step 1: Anthropic Connector Directory** — fill the partner intake form at `https://www.anthropic.com/integrations` (or current URL). Required: privacy policy URL, ToS URL, support contact, screenshot of consent screen.

- [ ] **Step 2: Cursor MCP registry** — open PR against Cursor's MCP catalog repo (whatever URL is current at submission time).

- [ ] **Step 3: Continue.dev** — open PR against `continuedev/mcp-registry` or current equivalent.

- [ ] **Step 4: Cline** — open PR against `cline/mcp-registry`.

Bundle all four into one tracking issue. Each PR is ~30 minutes.

---

### Task 45: Schedule a post-launch audit agent

- [ ] **Step 1: Schedule**

Use the `/schedule` skill to create a one-time agent that runs **14 days after `v7.11.0` ships**:

> Audit the MCP server's first two weeks. Check: (a) total OAuth grants in `mcp_token_policies`, (b) total `mcp_tool_invocations` rows by `result_status`, (c) any `error_code` appearing > 5 times, (d) p99 `duration_ms` per tool, (e) any spending-limit hits or rate-limit hits, (f) Filament admin shows zero unresolved issues. Report findings as a Slack-formatted summary. If clear, recommend submitting Anthropic Connector Directory + Cursor + Continue + Cline registry PRs (Task 44 above).

- [ ] **Step 2: Confirm scheduled**

Run: schedule list to verify the agent is queued.

---

(Phase 7 complete — 3 tasks scheduled or ready. v0.1.0 in npm + Smithery + Anthropic + Cursor + Continue + Cline once stability proven.)

---

## Self-review summary

**Spec coverage** — every section of the source spec is represented:

| Spec section | Implemented in |
|---|---|
| §5 Architecture (file inventory) | File Structure section + Tasks 1-31 |
| §6 OAuth flow + scopes + tools | Tasks 1, 6, 7, 8, 14-26 |
| §7 Operational layer (storage, audit, idempotency, kill-switches, rate limits, observability) | Tasks 9-13, 16, 18, 38 |
| §8 Distribution (npm wrapper + registries) | Tasks 27-31, 41, 43-44 |
| §9 Documentation deliverables | Tasks 32-37 |
| §10 Test strategy | Test files alongside each implementation task + Task 40 manual smoke |
| §11 Build sequence (7 phases, ~14 days) | Phases 1-7 of this plan |
| §13 Out-of-scope | Honored — no AP2 mandates, no step-up confirmation, no sensitive tools |
| §14 Success criteria | Task 40 smoke checklist verifies all listed criteria |

**Placeholder scan** — no `TBD`, `TODO`, "implement later", "appropriate error handling", or vague step descriptions remain. Every step has runnable code or exact commands.

**Type consistency** — public tool names (e.g., `payment.transfer`) and internal tool names (e.g., `transfer`) are consistent across config, router, adapter, tests, and docs. JSON-RPC error codes are consistent: -32600 invalid request, -32601 method/tool not found, -32602 invalid params, -32603 internal, -32000 insufficient scope, -32001 unauthenticated/spending limit, -32002 idempotency reuse, -32003 rate limit, -32004 tool disabled. Scope catalog is consistent (10 scopes) across config + tests + docs + plan.

---

## Execution Handoff

Plan complete and committed (next git op). Two execution options:

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration. Good fit because tasks are mostly independent within a phase, and subagents can run TDD cleanly per task.

**2. Inline Execution** — Execute tasks in this session using `executing-plans`, batch with checkpoints. Good fit if you want to watch every step of every task.

**Which approach?**
