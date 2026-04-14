# Webhook Architecture Refactor + Backend Handover

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace EVM contract monitoring (receives ALL worldwide transfers) with per-user address monitoring for Alchemy (EVM) and Helius (Solana), with auto-provisioned webhooks stored in the database. Harden webhook processing (dedup, spam, reorgs, async). Implement mobile backend handover (card waitlist, paid KYC, Stripe Bridge ramp).

**Architecture:** One shared webhook per network per provider, addresses managed dynamically via API. Webhooks are auto-created when the first address for a network appears, with `webhook_id` and `signing_key` stored in an encrypted DB table (not env vars). Alchemy handles EVM chains (Ethereum, Polygon, Arbitrum, Base). Helius handles Solana. Webhook sharding at 100K addresses per webhook is supported via a `shard` column. Webhook processing is dispatched to a queue job for reliability.

**Tech Stack:** Laravel 12, PHP 8.4, MySQL 8, Alchemy Notify API, Helius API, Stripe Bridge API, Laravel encrypted casts, Pest

## Plan Sections

| Section | Tasks | Description |
|---------|-------|-------------|
| A. Webhook Infra | 1-7 | Per-user webhooks, DB storage, observers, commands |
| B. Webhook Hardening | 8-11 | Dedup constraints, async queue, spam filter, reorg handling |
| C. Mobile Handover | 12-14 | Card waitlist, paid KYC, Stripe Bridge ramp |

---

## File Structure

| Action | Path | Responsibility |
|--------|------|----------------|
| Create | `database/migrations/2026_04_07_000001_create_webhook_endpoints_table.php` | DB schema for webhook metadata |
| Create | `app/Domain/Wallet/Models/WebhookEndpoint.php` | Eloquent model with encrypted signing_key |
| Create | `app/Domain/Wallet/Services/AlchemyWebhookManager.php` | Creates/manages Alchemy webhooks + addresses per EVM chain |
| Create | `app/Domain/Wallet/Observers/SmartAccountObserver.php` | Triggers EVM address registration on SmartAccount creation |
| Create | `tests/Unit/Domain/Wallet/Services/AlchemyWebhookManagerTest.php` | Tests for webhook creation + address management |
| Create | `tests/Unit/Domain/Wallet/Observers/SmartAccountObserverTest.php` | Tests for observer triggering |
| Modify | `app/Domain/Wallet/Observers/BlockchainAddressObserver.php` | Keep Solana-only with Helius (remove Alchemy option) |
| Modify | `app/Http/Controllers/Api/Webhook/AlchemyWebhookController.php` | Load signing keys from DB, remove Solana path |
| Modify | `app/Domain/Wallet/Services/AlchemyWebhookSyncService.php` | Refactor to use DB-stored webhook IDs |
| Modify | `app/Providers/WalletServiceProvider.php` | Register SmartAccountObserver |
| Modify | `app/Console/Commands/SolanaWebhookSyncCommand.php` | Simplify to Helius-only |
| Modify | `config/relayer.php` | Remove per-chain signing key env vars |
| Modify | `config/services.php` | Remove `alchemy.solana_webhook_id` |
| **Section B: Webhook Hardening** | | |
| Create | `database/migrations/2026_04_07_000002_add_unique_tx_hash_constraint.php` | Unique constraint on (tx_hash, chain) |
| Create | `app/Jobs/ProcessAlchemyWebhookJob.php` | Async queue job for webhook processing |
| Modify | `app/Http/Controllers/Api/Webhook/AlchemyWebhookController.php` | Dispatch to queue, add spam filter + reorg check |
| Modify | `app/Domain/Wallet/Constants/SolanaTokens.php` | Extend known mints |
| **Section C: Mobile Handover** | | |
| Create | `database/migrations/2026_04_07_000003_create_card_waitlist_table.php` | Card waitlist schema |
| Create | `app/Domain/Card/Models/CardWaitlist.php` | Waitlist model |
| Create | `app/Http/Controllers/Api/V1/CardWaitlistController.php` | 2 endpoints: join + status |
| Create | `tests/Unit/Http/Controllers/Api/V1/CardWaitlistControllerTest.php` | Tests |
| Create | `database/migrations/2026_04_07_000004_add_paid_kyc_fields.php` | KYC payment fields on trustcert_applications |
| Create | `database/migrations/2026_04_07_000006_create_verification_payments_table.php` | Audit table for all KYC payments |
| Create | `app/Http/Controllers/Api/V1/TrustCertPaymentController.php` | 3 pay endpoints: wallet, card, IAP |
| Create | `app/Http/Controllers/Api/Webhook/StripeKycWebhookController.php` | Stripe Checkout webhook for card KYC |
| Create | `tests/Unit/Http/Controllers/Api/V1/TrustCertPaymentControllerTest.php` | Tests |
| Create | `app/Domain/Ramp/Services/StripeBridgeService.php` | Stripe Bridge API client |
| Create | `app/Http/Controllers/Api/Webhook/StripeBridgeWebhookController.php` | Stripe Bridge webhook handler |
| Create | `database/migrations/2026_04_07_000005_add_stripe_bridge_fields.php` | Stripe fields on ramp_sessions |
| Modify | `app/Http/Controllers/Api/V1/RampController.php` | Swap Onramper → Stripe Bridge |
| Modify | `routes/api.php` | Add new routes |

---

### Task 1: Create `webhook_endpoints` Migration and Model

**Files:**
- Create: `database/migrations/2026_04_07_000001_create_webhook_endpoints_table.php`
- Create: `app/Domain/Wallet/Models/WebhookEndpoint.php`

- [ ] **Step 1: Create the migration**

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
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);           // 'alchemy' or 'helius'
            $table->string('network', 30);             // 'ethereum', 'polygon', etc.
            $table->unsignedInteger('shard')->default(0); // For 100K+ sharding
            $table->string('external_webhook_id');     // 'wh_abc123' from provider
            $table->text('signing_key');               // Encrypted via model cast
            $table->string('webhook_url');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('address_count')->default(0);
            $table->timestamps();

            $table->unique(['provider', 'network', 'shard']);
            $table->index(['provider', 'network', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
```

- [ ] **Step 2: Create the Eloquent model**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores webhook endpoint metadata for Alchemy/Helius providers.
 *
 * @property int $id
 * @property string $provider
 * @property string $network
 * @property int $shard
 * @property string $external_webhook_id
 * @property string $signing_key
 * @property string $webhook_url
 * @property bool $is_active
 * @property int $address_count
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class WebhookEndpoint extends Model
{
    protected $table = 'webhook_endpoints';

    protected $fillable = [
        'provider',
        'network',
        'shard',
        'external_webhook_id',
        'signing_key',
        'webhook_url',
        'is_active',
        'address_count',
    ];

    protected $casts = [
        'signing_key'   => 'encrypted',
        'is_active'     => 'boolean',
        'shard'         => 'integer',
        'address_count' => 'integer',
    ];

    /** Maximum addresses per webhook (Alchemy limit) */
    public const MAX_ADDRESSES_PER_WEBHOOK = 100_000;

    public function hasCapacity(): bool
    {
        return $this->address_count < self::MAX_ADDRESSES_PER_WEBHOOK;
    }

    public function incrementAddressCount(): void
    {
        $this->increment('address_count');
    }

    public function decrementAddressCount(): void
    {
        if ($this->address_count > 0) {
            $this->decrement('address_count');
        }
    }
}
```

- [ ] **Step 3: Run the migration**

Run: `php artisan migrate`
Expected: `webhook_endpoints` table created successfully

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_04_07_000001_create_webhook_endpoints_table.php app/Domain/Wallet/Models/WebhookEndpoint.php
git commit -m "feat: add webhook_endpoints table and model for dynamic webhook management"
```

---

### Task 2: Create AlchemyWebhookManager Service

**Files:**
- Create: `app/Domain/Wallet/Services/AlchemyWebhookManager.php`
- Create: `tests/Unit/Domain/Wallet/Services/AlchemyWebhookManagerTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

declare(strict_types=1);

use App\Domain\Wallet\Models\WebhookEndpoint;
use App\Domain\Wallet\Services\AlchemyWebhookManager;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

beforeEach(function () {
    config(['services.alchemy.notify_token' => 'test-token']);
});

it('creates a new webhook when none exists for the network', function () {
    Http::fake([
        'dashboard.alchemy.com/api/create-webhook' => Http::response([
            'data' => [
                'id' => 'wh_test123',
                'signing_key' => 'whsec_test_signing_key',
                'is_active' => true,
            ],
        ], 200),
        'dashboard.alchemy.com/api/update-webhook-addresses' => Http::response([], 200),
    ]);

    $manager = app(AlchemyWebhookManager::class);
    $result = $manager->addAddress('0xabc123', 'ethereum');

    expect($result)->toBeTrue();

    $webhook = WebhookEndpoint::where('network', 'ethereum')
        ->where('provider', 'alchemy')
        ->first();

    expect($webhook)->not->toBeNull();
    expect($webhook->external_webhook_id)->toBe('wh_test123');
    expect($webhook->signing_key)->toBe('whsec_test_signing_key');
    expect($webhook->address_count)->toBe(1);
});

it('reuses existing webhook when one exists for the network', function () {
    $webhook = WebhookEndpoint::create([
        'provider' => 'alchemy',
        'network' => 'polygon',
        'shard' => 0,
        'external_webhook_id' => 'wh_existing',
        'signing_key' => 'whsec_existing',
        'webhook_url' => 'https://zelta.app/api/webhooks/alchemy/address-activity',
        'is_active' => true,
        'address_count' => 5,
    ]);

    Http::fake([
        'dashboard.alchemy.com/api/update-webhook-addresses' => Http::response([], 200),
    ]);

    $manager = app(AlchemyWebhookManager::class);
    $result = $manager->addAddress('0xdef456', 'polygon');

    expect($result)->toBeTrue();
    expect(WebhookEndpoint::where('network', 'polygon')->count())->toBe(1);

    $webhook->refresh();
    expect($webhook->address_count)->toBe(6);
});

it('returns all signing keys for active webhooks', function () {
    WebhookEndpoint::create([
        'provider' => 'alchemy',
        'network' => 'ethereum',
        'shard' => 0,
        'external_webhook_id' => 'wh_1',
        'signing_key' => 'key_eth',
        'webhook_url' => 'https://zelta.app/api/webhooks/alchemy/address-activity',
        'is_active' => true,
        'address_count' => 10,
    ]);

    WebhookEndpoint::create([
        'provider' => 'alchemy',
        'network' => 'polygon',
        'shard' => 0,
        'external_webhook_id' => 'wh_2',
        'signing_key' => 'key_poly',
        'webhook_url' => 'https://zelta.app/api/webhooks/alchemy/address-activity',
        'is_active' => true,
        'address_count' => 5,
    ]);

    $manager = app(AlchemyWebhookManager::class);
    $keys = $manager->getSigningKeys();

    expect($keys)->toHaveCount(2);
    expect($keys)->toContain('key_eth');
    expect($keys)->toContain('key_poly');
});

it('removes address and decrements count', function () {
    WebhookEndpoint::create([
        'provider' => 'alchemy',
        'network' => 'arbitrum',
        'shard' => 0,
        'external_webhook_id' => 'wh_arb',
        'signing_key' => 'key_arb',
        'webhook_url' => 'https://zelta.app/api/webhooks/alchemy/address-activity',
        'is_active' => true,
        'address_count' => 10,
    ]);

    Http::fake([
        'dashboard.alchemy.com/api/update-webhook-addresses' => Http::response([], 200),
    ]);

    $manager = app(AlchemyWebhookManager::class);
    $result = $manager->removeAddress('0xabc', 'arbitrum');

    expect($result)->toBeTrue();

    $webhook = WebhookEndpoint::where('network', 'arbitrum')->first();
    expect($webhook->address_count)->toBe(9);
});

it('skips when notify token is not configured', function () {
    config(['services.alchemy.notify_token' => '']);

    $manager = app(AlchemyWebhookManager::class);
    $result = $manager->addAddress('0xabc', 'ethereum');

    expect($result)->toBeFalse();
});

it('maps internal network names to Alchemy network enum', function () {
    Http::fake([
        'dashboard.alchemy.com/*' => Http::response([
            'data' => [
                'id' => 'wh_test',
                'signing_key' => 'whsec_test',
                'is_active' => true,
            ],
        ], 200),
    ]);

    $manager = app(AlchemyWebhookManager::class);
    $manager->addAddress('0xabc', 'base');

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), 'create-webhook')) {
            return $request['network'] === 'BASE_MAINNET';
        }
        return true;
    });
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `XDEBUG_MODE=off vendor/bin/pest tests/Unit/Domain/Wallet/Services/AlchemyWebhookManagerTest.php --stop-on-failure`
Expected: FAIL — class not found

- [ ] **Step 3: Write the AlchemyWebhookManager implementation**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Manages Alchemy Address Activity webhooks for EVM chains.
 *
 * Auto-creates webhooks via Alchemy Notify API when the first address for
 * a network is registered. Stores webhook_id and signing_key in the database.
 * Supports sharding at 100K addresses per webhook.
 *
 * API: POST https://dashboard.alchemy.com/api/create-webhook
 * API: PATCH https://dashboard.alchemy.com/api/update-webhook-addresses
 * Auth: X-Alchemy-Token header
 */
class AlchemyWebhookManager
{
    private const CREATE_URL = 'https://dashboard.alchemy.com/api/create-webhook';

    private const UPDATE_URL = 'https://dashboard.alchemy.com/api/update-webhook-addresses';

    private const WEBHOOK_URL = 'https://zelta.app/api/webhooks/alchemy/address-activity';

    /** @var array<string, string> Internal network → Alchemy network enum */
    private const NETWORK_MAP = [
        'ethereum' => 'ETH_MAINNET',
        'polygon'  => 'MATIC_MAINNET',
        'arbitrum' => 'ARB_MAINNET',
        'base'     => 'BASE_MAINNET',
    ];

    /**
     * Add an EVM address to the Alchemy webhook for the given network.
     * Creates the webhook if it doesn't exist yet.
     */
    public function addAddress(string $address, string $network): bool
    {
        $token = $this->getNotifyToken();
        if ($token === '') {
            Log::debug('AlchemyWebhookManager: skipped — notify token not configured');

            return false;
        }

        $webhook = $this->getOrCreateWebhook($network, $token);
        if ($webhook === null) {
            return false;
        }

        $success = $this->patchAddresses($token, $webhook->external_webhook_id, [strtolower($address)], []);

        if ($success) {
            $webhook->incrementAddressCount();
        }

        return $success;
    }

    /**
     * Remove an EVM address from the Alchemy webhook for the given network.
     */
    public function removeAddress(string $address, string $network): bool
    {
        $token = $this->getNotifyToken();
        if ($token === '') {
            return false;
        }

        $webhook = WebhookEndpoint::where('provider', 'alchemy')
            ->where('network', $network)
            ->where('is_active', true)
            ->first();

        if ($webhook === null) {
            return false;
        }

        $success = $this->patchAddresses($token, $webhook->external_webhook_id, [], [strtolower($address)]);

        if ($success) {
            $webhook->decrementAddressCount();
        }

        return $success;
    }

    /**
     * Sync all SmartAccount addresses for a network to Alchemy.
     */
    public function syncAllAddresses(string $network): int
    {
        $token = $this->getNotifyToken();
        if ($token === '') {
            Log::warning('AlchemyWebhookManager: Cannot sync — notify token not set');

            return 0;
        }

        $addresses = \App\Domain\Relayer\Models\SmartAccount::where('network', $network)
            ->pluck('account_address')
            ->unique()
            ->values()
            ->all();

        if ($addresses === []) {
            Log::info("AlchemyWebhookManager: No addresses to sync for {$network}");

            return 0;
        }

        $webhook = $this->getOrCreateWebhook($network, $token);
        if ($webhook === null) {
            return 0;
        }

        $success = $this->patchAddresses($token, $webhook->external_webhook_id, $addresses, []);

        if ($success) {
            $webhook->update(['address_count' => count($addresses)]);
            Log::info("AlchemyWebhookManager: Synced {$network} addresses", ['count' => count($addresses)]);
        }

        return $success ? count($addresses) : 0;
    }

    /**
     * Get all active signing keys for Alchemy webhooks (used by controller for verification).
     *
     * @return array<string>
     */
    public function getSigningKeys(): array
    {
        return WebhookEndpoint::where('provider', 'alchemy')
            ->where('is_active', true)
            ->pluck('signing_key')
            ->all();
    }

    /**
     * Get or create the webhook for a network.
     */
    private function getOrCreateWebhook(string $network, string $token): ?WebhookEndpoint
    {
        // Find an existing webhook with capacity
        $webhook = WebhookEndpoint::where('provider', 'alchemy')
            ->where('network', $network)
            ->where('is_active', true)
            ->where('address_count', '<', WebhookEndpoint::MAX_ADDRESSES_PER_WEBHOOK)
            ->orderBy('shard')
            ->first();

        if ($webhook !== null) {
            return $webhook;
        }

        // Create a new webhook via Alchemy API
        return $this->createWebhook($network, $token);
    }

    /**
     * Create a new Alchemy webhook via the Notify API.
     */
    private function createWebhook(string $network, string $token): ?WebhookEndpoint
    {
        $alchemyNetwork = self::NETWORK_MAP[$network] ?? null;
        if ($alchemyNetwork === null) {
            Log::error("AlchemyWebhookManager: Unknown network {$network}");

            return null;
        }

        // Determine next shard number
        $nextShard = (int) WebhookEndpoint::where('provider', 'alchemy')
            ->where('network', $network)
            ->max('shard') + 1;

        // If this is the very first webhook (no shards yet), start at 0
        if ($nextShard === 1 && ! WebhookEndpoint::where('provider', 'alchemy')->where('network', $network)->exists()) {
            $nextShard = 0;
        }

        $response = Http::timeout(15)
            ->withHeaders(['X-Alchemy-Token' => $token])
            ->post(self::CREATE_URL, [
                'network'      => $alchemyNetwork,
                'webhook_type' => 'ADDRESS_ACTIVITY',
                'webhook_url'  => self::WEBHOOK_URL,
                'addresses'    => [],
            ]);

        if (! $response->successful()) {
            Log::error('AlchemyWebhookManager: Failed to create webhook', [
                'network' => $network,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);

            return null;
        }

        $data = $response->json('data', []);
        $webhookId = (string) ($data['id'] ?? '');
        $signingKey = (string) ($data['signing_key'] ?? '');

        if ($webhookId === '' || $signingKey === '') {
            Log::error('AlchemyWebhookManager: Missing webhook_id or signing_key in response', [
                'network'  => $network,
                'response' => $data,
            ]);

            return null;
        }

        $webhook = WebhookEndpoint::create([
            'provider'            => 'alchemy',
            'network'             => $network,
            'shard'               => $nextShard,
            'external_webhook_id' => $webhookId,
            'signing_key'         => $signingKey,
            'webhook_url'         => self::WEBHOOK_URL,
            'is_active'           => true,
            'address_count'       => 0,
        ]);

        Log::info('AlchemyWebhookManager: Created webhook', [
            'network'    => $network,
            'webhook_id' => $webhookId,
            'shard'      => $nextShard,
        ]);

        return $webhook;
    }

    /**
     * PATCH addresses on an Alchemy webhook.
     *
     * @param array<string> $toAdd
     * @param array<string> $toRemove
     */
    private function patchAddresses(string $token, string $webhookId, array $toAdd, array $toRemove): bool
    {
        $response = Http::timeout(15)
            ->withHeaders(['X-Alchemy-Token' => $token])
            ->patch(self::UPDATE_URL, [
                'webhook_id'          => $webhookId,
                'addresses_to_add'    => array_values($toAdd),
                'addresses_to_remove' => array_values($toRemove),
            ]);

        if (! $response->successful()) {
            Log::error('AlchemyWebhookManager: Failed to patch addresses', [
                'webhook_id' => $webhookId,
                'status'     => $response->status(),
                'body'       => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    private function getNotifyToken(): string
    {
        return (string) config('services.alchemy.notify_token', '');
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `XDEBUG_MODE=off vendor/bin/pest tests/Unit/Domain/Wallet/Services/AlchemyWebhookManagerTest.php --stop-on-failure`
Expected: All 6 tests PASS

- [ ] **Step 5: Run PHPStan**

Run: `XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G app/Domain/Wallet/Services/AlchemyWebhookManager.php app/Domain/Wallet/Models/WebhookEndpoint.php`
Expected: No errors

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Wallet/Services/AlchemyWebhookManager.php tests/Unit/Domain/Wallet/Services/AlchemyWebhookManagerTest.php
git commit -m "feat: add AlchemyWebhookManager for auto-provisioned per-user webhooks"
```

---

### Task 3: Create SmartAccountObserver for EVM Webhook Registration

**Files:**
- Create: `app/Domain/Wallet/Observers/SmartAccountObserver.php`
- Create: `tests/Unit/Domain/Wallet/Observers/SmartAccountObserverTest.php`
- Modify: `app/Providers/WalletServiceProvider.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

declare(strict_types=1);

use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\Wallet\Services\AlchemyWebhookManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(Tests\TestCase::class);

beforeEach(function () {
    config(['services.alchemy.notify_token' => 'test-token']);
});

it('registers EVM address with Alchemy when SmartAccount is created', function () {
    Http::fake([
        'dashboard.alchemy.com/*' => Http::response([
            'data' => ['id' => 'wh_test', 'signing_key' => 'whsec_test', 'is_active' => true],
        ], 200),
    ]);

    $user = \App\Models\User::factory()->create();

    SmartAccount::create([
        'user_id' => $user->id,
        'owner_address' => '0xowner123',
        'account_address' => '0xaccount456',
        'network' => 'polygon',
        'deployed' => false,
        'nonce' => 0,
        'pending_ops' => 0,
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'update-webhook-addresses')
            && in_array('0xaccount456', $request['addresses_to_add']);
    });
});

it('does not call Alchemy when notify token is missing', function () {
    config(['services.alchemy.notify_token' => '']);
    Http::fake();

    $user = \App\Models\User::factory()->create();

    SmartAccount::create([
        'user_id' => $user->id,
        'owner_address' => '0xowner789',
        'account_address' => '0xaccount012',
        'network' => 'ethereum',
        'deployed' => false,
        'nonce' => 0,
        'pending_ops' => 0,
    ]);

    Http::assertNothingSent();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `XDEBUG_MODE=off vendor/bin/pest tests/Unit/Domain/Wallet/Observers/SmartAccountObserverTest.php --stop-on-failure`
Expected: FAIL

- [ ] **Step 3: Create the SmartAccountObserver**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Observers;

use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\Wallet\Services\AlchemyWebhookManager;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Observes SmartAccount model events to register/unregister
 * EVM addresses with Alchemy webhook monitoring.
 */
class SmartAccountObserver
{
    public function __construct(
        private readonly AlchemyWebhookManager $webhookManager,
    ) {
    }

    public function created(SmartAccount $account): void
    {
        dispatch(function () use ($account): void {
            try {
                $this->webhookManager->addAddress(
                    $account->account_address,
                    $account->network,
                );
            } catch (Throwable $e) {
                Log::error('EVM webhook: Failed to register address', [
                    'address' => $account->account_address,
                    'network' => $account->network,
                    'error'   => $e->getMessage(),
                ]);
            }
        })->afterCommit();
    }

    public function deleted(SmartAccount $account): void
    {
        dispatch(function () use ($account): void {
            try {
                $this->webhookManager->removeAddress(
                    $account->account_address,
                    $account->network,
                );
            } catch (Throwable $e) {
                Log::error('EVM webhook: Failed to unregister address', [
                    'address' => $account->account_address,
                    'network' => $account->network,
                    'error'   => $e->getMessage(),
                ]);
            }
        })->afterCommit();
    }
}
```

- [ ] **Step 4: Register the observer in WalletServiceProvider**

In `app/Providers/WalletServiceProvider.php`, add to the `boot()` method:

```php
use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\Wallet\Observers\SmartAccountObserver;

// Inside boot():
SmartAccount::observe(SmartAccountObserver::class);
```

- [ ] **Step 5: Run tests**

Run: `XDEBUG_MODE=off vendor/bin/pest tests/Unit/Domain/Wallet/Observers/SmartAccountObserverTest.php --stop-on-failure`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Domain/Wallet/Observers/SmartAccountObserver.php tests/Unit/Domain/Wallet/Observers/SmartAccountObserverTest.php app/Providers/WalletServiceProvider.php
git commit -m "feat: add SmartAccountObserver to auto-register EVM addresses with Alchemy"
```

---

### Task 4: Refactor AlchemyWebhookController to Use DB-Stored Keys

**Files:**
- Modify: `app/Http/Controllers/Api/Webhook/AlchemyWebhookController.php`
- Modify: `tests/Unit/Http/Controllers/Api/Webhook/AlchemyWebhookSolanaTest.php` → rename/refactor

- [ ] **Step 1: Update `verifySignature()` to load keys from DB**

Replace the `verifySignature()` method in `AlchemyWebhookController.php`:

```php
private function verifySignature(Request $request): bool
{
    /** @var array<string> $signingKeys */
    $signingKeys = app(AlchemyWebhookManager::class)->getSigningKeys();

    if ($signingKeys === []) {
        Log::critical('Alchemy webhook rejected: no signing keys in database');

        return app()->environment('local', 'testing');
    }

    $signature = $request->header('X-Alchemy-Signature');
    if ($signature === null) {
        return false;
    }

    $payload = $request->getContent();

    foreach ($signingKeys as $key) {
        if (hash_equals(hash_hmac('sha256', $payload, $key), $signature)) {
            return true;
        }
    }

    return false;
}
```

Add the import at the top of the controller:

```php
use App\Domain\Wallet\Services\AlchemyWebhookManager;
```

- [ ] **Step 2: Remove the Solana processing path from AlchemyWebhookController**

In the `handle()` method, remove the Solana branch. Solana is handled by `HeliusWebhookController` now:

```php
// In handle(), after resolving network:
if ($network === 'solana') {
    return response()->json(['status' => 'ignored', 'reason' => 'solana handled by helius']);
}
```

- [ ] **Step 3: Remove unused Solana methods from AlchemyWebhookController**

Remove these private methods (they're now dead code):
- `processSolanaActivities()`
- `convertToHeliusFormat()`
- `sendSolanaPushNotification()`

Remove the unused imports:
- `App\Domain\Wallet\Constants\SolanaCacheKeys`
- `App\Domain\Wallet\Factories\BlockchainConnectorFactory`
- `App\Domain\Wallet\Services\HeliusTransactionProcessor`
- `App\Domain\Account\Models\BlockchainAddress`

- [ ] **Step 4: Update tests to use DB-stored keys**

In existing Alchemy webhook tests, replace config-based key setup with DB insertion:

```php
// Before (in test setup):
// config(['relayer.alchemy_webhook_signing_keys' => ['test-key']]);

// After:
WebhookEndpoint::create([
    'provider' => 'alchemy',
    'network' => 'ethereum',
    'shard' => 0,
    'external_webhook_id' => 'wh_test',
    'signing_key' => 'test-key',
    'webhook_url' => 'https://zelta.app/api/webhooks/alchemy/address-activity',
    'is_active' => true,
    'address_count' => 10,
]);
```

- [ ] **Step 5: Run all webhook tests**

Run: `XDEBUG_MODE=off vendor/bin/pest tests/Unit/Http/Controllers/Api/Webhook/ --stop-on-failure`
Expected: All tests PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/Webhook/AlchemyWebhookController.php tests/
git commit -m "refactor: AlchemyWebhookController loads signing keys from DB, removes Solana path"
```

---

### Task 5: Simplify BlockchainAddressObserver to Helius-Only

**Files:**
- Modify: `app/Domain/Wallet/Observers/BlockchainAddressObserver.php`

- [ ] **Step 1: Remove Alchemy option from observer**

Solana uses Helius only. Remove the provider switch and Alchemy dependency:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Observers;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Wallet\Services\HeliusWebhookSyncService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Observes BlockchainAddress model events to sync Solana
 * addresses with Helius webhook provider.
 */
class BlockchainAddressObserver
{
    public function __construct(
        private readonly HeliusWebhookSyncService $heliusSync,
    ) {
    }

    public function created(BlockchainAddress $address): void
    {
        if ($address->chain !== 'solana' || ! $address->is_active) {
            return;
        }

        dispatch(function () use ($address): void {
            try {
                $this->heliusSync->addAddress($address->address);
            } catch (Throwable $e) {
                Log::error('Solana webhook: Failed to sync new Solana address', [
                    'address' => $address->address,
                    'error'   => $e->getMessage(),
                ]);
            }
        })->afterCommit();
    }

    public function deleted(BlockchainAddress $address): void
    {
        if ($address->chain !== 'solana') {
            return;
        }

        dispatch(function () use ($address): void {
            try {
                $this->heliusSync->removeAddress($address->address);
            } catch (Throwable $e) {
                Log::error('Solana webhook: Failed to remove Solana address', [
                    'address' => $address->address,
                    'error'   => $e->getMessage(),
                ]);
            }
        })->afterCommit();
    }
}
```

- [ ] **Step 2: Simplify SolanaWebhookSyncCommand to Helius-only**

In `app/Console/Commands/SolanaWebhookSyncCommand.php`, remove the `--provider` option and Alchemy branch. Solana always uses Helius now.

- [ ] **Step 3: Run tests**

Run: `XDEBUG_MODE=off vendor/bin/pest tests/Unit/Http/Controllers/Api/Webhook/ --stop-on-failure`
Expected: All PASS

- [ ] **Step 4: Commit**

```bash
git add app/Domain/Wallet/Observers/BlockchainAddressObserver.php app/Console/Commands/SolanaWebhookSyncCommand.php
git commit -m "refactor: simplify Solana to Helius-only, remove Alchemy option for Solana"
```

---

### Task 6: Clean Up Config and Env Vars

**Files:**
- Modify: `config/relayer.php` — remove `alchemy_webhook_signing_keys` array
- Modify: `config/services.php` — remove `alchemy.solana_webhook_id`
- Modify: `.env.example`, `.env.production.example`, `.env.zelta.example` — remove per-chain signing key vars, remove `ALCHEMY_SOLANA_WEBHOOK_ID`
- Delete: `app/Domain/Wallet/Services/AlchemyWebhookSyncService.php` (replaced by AlchemyWebhookManager)
- Delete: `tests/Unit/Domain/Wallet/Services/AlchemyWebhookSyncServiceTest.php`

- [ ] **Step 1: Remove `alchemy_webhook_signing_keys` from `config/relayer.php`**

Delete the entire `alchemy_webhook_signing_keys` array block.

- [ ] **Step 2: Remove `alchemy.solana_webhook_id` from `config/services.php`**

Remove the `solana_webhook_id` key from the `alchemy` config section.

- [ ] **Step 3: Clean env example files**

Remove these vars from all `.env.*.example` files:
- `ALCHEMY_WEBHOOK_SIGNING_KEY_POLYGON`
- `ALCHEMY_WEBHOOK_SIGNING_KEY_ARBITRUM`
- `ALCHEMY_WEBHOOK_SIGNING_KEY_ETHEREUM`
- `ALCHEMY_WEBHOOK_SIGNING_KEY_BASE`
- `ALCHEMY_WEBHOOK_SIGNING_KEY_SOLANA`
- `ALCHEMY_WEBHOOK_SIGNING_KEY` (legacy)
- `ALCHEMY_SOLANA_WEBHOOK_ID`
- `SOLANA_WEBHOOK_PROVIDER`

Keep: `ALCHEMY_NOTIFY_TOKEN` and `ALCHEMY_API_KEY`

- [ ] **Step 4: Delete old AlchemyWebhookSyncService**

```bash
rm app/Domain/Wallet/Services/AlchemyWebhookSyncService.php
rm tests/Unit/Domain/Wallet/Services/AlchemyWebhookSyncServiceTest.php
```

- [ ] **Step 5: Run full quality checks**

```bash
./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php
XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G
XDEBUG_MODE=off vendor/bin/pest --parallel --stop-on-failure
```

Expected: All pass with zero errors

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor: remove per-chain signing key env vars, delete old AlchemyWebhookSyncService"
```

---

### Task 7: Add EVM Webhook Sync Command

**Files:**
- Create: `app/Console/Commands/EvmWebhookSyncCommand.php`

- [ ] **Step 1: Create the command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Wallet\Services\AlchemyWebhookManager;
use Illuminate\Console\Command;

/**
 * Bulk-sync all EVM SmartAccount addresses to Alchemy webhooks.
 *
 * Creates webhooks for networks that don't have one yet.
 */
class EvmWebhookSyncCommand extends Command
{
    protected $signature = 'evm:sync-webhooks
        {--network= : Sync a specific network (ethereum, polygon, arbitrum, base)}
        {--dry-run : Show count without syncing}';

    protected $description = 'Sync all EVM smart account addresses to Alchemy webhooks';

    public function handle(AlchemyWebhookManager $manager): int
    {
        $networks = $this->option('network')
            ? [$this->option('network')]
            : ['ethereum', 'polygon', 'arbitrum', 'base'];

        foreach ($networks as $network) {
            if ($this->option('dry-run')) {
                $count = \App\Domain\Relayer\Models\SmartAccount::where('network', $network)->count();
                $this->info("{$network}: {$count} addresses would be synced");

                continue;
            }

            $count = $manager->syncAllAddresses($network);
            $this->info("{$network}: synced {$count} addresses");
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 2: Test the command**

Run: `php artisan evm:sync-webhooks --dry-run`
Expected: Shows address counts per network

- [ ] **Step 3: Commit**

```bash
git add app/Console/Commands/EvmWebhookSyncCommand.php
git commit -m "feat: add evm:sync-webhooks command for bulk address sync"
```

---

---

## Section B: Webhook Hardening

### Task 8: Add Unique Constraint on Transaction Hash

**Files:**
- Create: `database/migrations/2026_04_07_000002_add_unique_tx_hash_constraint.php`

Currently `blockchain_address_transactions.tx_hash` is indexed but NOT unique. The `firstOrCreate()` pattern has a race condition under concurrent webhook retries.

- [ ] **Step 1: Create migration**

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
        Schema::table('blockchain_address_transactions', function (Blueprint $table) {
            $table->dropIndex(['tx_hash']);
            $table->unique(['tx_hash', 'chain'], 'uq_tx_hash_chain');
        });
    }

    public function down(): void
    {
        Schema::table('blockchain_address_transactions', function (Blueprint $table) {
            $table->dropUnique('uq_tx_hash_chain');
            $table->index('tx_hash');
        });
    }
};
```

- [ ] **Step 2: Run migration**

Run: `php artisan migrate`
Expected: Unique constraint added

- [ ] **Step 3: Update HeliusTransactionProcessor to catch duplicate insert**

In `app/Domain/Wallet/Services/HeliusTransactionProcessor.php`, wrap the `firstOrCreate()` in a try-catch for `QueryException` with duplicate entry error code — return the existing record gracefully.

- [ ] **Step 4: Run tests**

Run: `XDEBUG_MODE=off vendor/bin/pest tests/Unit/Http/Controllers/Api/Webhook/ --stop-on-failure`
Expected: All PASS (dedup test should still work)

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_07_000002_add_unique_tx_hash_constraint.php app/Domain/Wallet/Services/HeliusTransactionProcessor.php
git commit -m "fix: add unique constraint on (tx_hash, chain) to prevent duplicate transactions"
```

---

### Task 9: Queue-Based Webhook Processing

**Files:**
- Create: `app/Jobs/ProcessAlchemyWebhookJob.php`
- Modify: `app/Http/Controllers/Api/Webhook/AlchemyWebhookController.php`

The controller currently processes synchronously, blocking until DB writes complete. Move processing to a queue job — controller validates signature, responds 200 immediately, dispatches job.

- [ ] **Step 1: Create the queue job**

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Mobile\Services\PushNotificationService;
use App\Domain\Relayer\Contracts\WalletBalanceProviderInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Wallet\Events\Broadcast\WalletBalanceUpdated;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAlchemyWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly array $payload,
    ) {
    }

    public function handle(
        WalletBalanceProviderInterface $balanceProvider,
        PushNotificationService $pushService,
    ): void {
        $activities = $this->payload['event']['activity'] ?? [];
        $network = $this->resolveNetwork($this->payload['event']['network'] ?? '');

        if ($network === null) {
            return;
        }

        $notifiedUsers = [];

        foreach ($activities as $activity) {
            $category = $activity['category'] ?? '';
            if (! in_array($category, ['token', 'erc20'], true)) {
                continue;
            }

            // Skip reorged transactions
            if (($activity['removed'] ?? false) === true) {
                Log::info('Alchemy webhook: Skipping reorged transaction', [
                    'hash' => $activity['hash'] ?? 'unknown',
                    'network' => $network,
                ]);
                continue;
            }

            // Spam filter: only process known tokens (USDC, USDT)
            $asset = strtoupper((string) ($activity['asset'] ?? ''));
            if (! in_array($asset, ['USDC', 'USDT'], true)) {
                continue;
            }

            $addresses = array_filter([
                $activity['fromAddress'] ?? null,
                $activity['toAddress'] ?? null,
            ]);

            foreach ($addresses as $address) {
                $address = strtolower($address);
                $userId = $this->resolveUserId($address);
                if ($userId === null) {
                    continue;
                }

                if (isset($notifiedUsers[$userId])) {
                    continue;
                }
                $notifiedUsers[$userId] = true;

                $this->invalidateBalanceCache($address, $network, $balanceProvider);
                broadcast(new WalletBalanceUpdated($userId, $network));
                $this->sendPushNotification($userId, $activity, $address, $pushService);

                Log::info('Alchemy webhook: balance update broadcast', [
                    'user_id' => $userId,
                    'address' => $address,
                    'network' => $network,
                    'asset' => $asset,
                ]);
            }
        }
    }

    private function resolveUserId(string $address): ?int
    {
        $cacheKey = "webhook:addr_to_user:{$address}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached === 0 ? null : (int) $cached;
        }

        $blockchainAddress = BlockchainAddress::where('address', $address)->first();
        if ($blockchainAddress === null) {
            // Also check smart_accounts table for EVM
            $smartAccount = \App\Domain\Relayer\Models\SmartAccount::where('account_address', $address)->first();
            $userId = $smartAccount?->user_id;
        } else {
            $userId = $blockchainAddress->user?->id;
        }

        $ttl = $userId !== null ? 3600 : 300;
        Cache::put($cacheKey, $userId ?? 0, $ttl);

        return $userId;
    }

    private function invalidateBalanceCache(string $address, string $network, WalletBalanceProviderInterface $balanceProvider): void
    {
        $supportedNetwork = SupportedNetwork::tryFrom($network);
        if ($supportedNetwork === null) {
            return;
        }

        /** @var array<string, mixed> $tokenConfig */
        $tokenConfig = config('relayer.balance_checking.tokens', ['USDC' => [], 'USDT' => []]);
        foreach (array_keys($tokenConfig) as $token) {
            $balanceProvider->invalidateCache($address, (string) $token, $supportedNetwork);
        }
    }

    private function sendPushNotification(int $userId, array $activity, string $matchedAddress, PushNotificationService $pushService): void
    {
        try {
            $user = User::find($userId);
            if ($user === null) {
                return;
            }

            $fromAddress = strtolower((string) ($activity['fromAddress'] ?? ''));
            $toAddress = strtolower((string) ($activity['toAddress'] ?? ''));
            $isIncoming = strtolower($matchedAddress) === $toAddress;

            $asset = (string) ($activity['asset'] ?? 'unknown');
            $amount = (string) ($activity['value'] ?? '0');

            $counterpartyAddr = $isIncoming ? ($fromAddress ?: 'unknown') : ($toAddress ?: 'unknown');
            $truncatedAddr = substr($counterpartyAddr, 0, 6) . '...' . substr($counterpartyAddr, -4);

            if ($isIncoming) {
                $pushService->sendTransactionReceived($user, $amount, $asset, $truncatedAddr);
            } else {
                $pushService->sendTransactionSent($user, $amount, $asset, $truncatedAddr);
            }
        } catch (Throwable $e) {
            Log::warning('Alchemy EVM: Push notification failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveNetwork(string $alchemyNetwork): ?string
    {
        return match (strtolower($alchemyNetwork)) {
            'eth-mainnet', 'eth_mainnet' => 'ethereum',
            'polygon-mainnet', 'matic_mainnet' => 'polygon',
            'arb-mainnet', 'arb_mainnet' => 'arbitrum',
            'base-mainnet', 'base_mainnet' => 'base',
            default => null,
        };
    }
}
```

- [ ] **Step 2: Simplify AlchemyWebhookController to dispatch-only**

The controller becomes thin: verify signature → dispatch job → return 200.

```php
public function handle(Request $request): JsonResponse
{
    if (! $this->verifySignature($request)) {
        Log::warning('Alchemy webhook signature verification failed', [
            'ip' => $request->ip(),
        ]);
        return response()->json(['error' => 'Invalid signature'], 401);
    }

    $payload = $request->all();

    if (($payload['type'] ?? null) !== 'ADDRESS_ACTIVITY') {
        return response()->json(['status' => 'ignored']);
    }

    // Solana handled by Helius
    $network = strtolower($payload['event']['network'] ?? '');
    if (str_contains($network, 'sol')) {
        return response()->json(['status' => 'ignored', 'reason' => 'solana handled by helius']);
    }

    ProcessAlchemyWebhookJob::dispatch($payload);

    return response()->json(['status' => 'queued']);
}
```

- [ ] **Step 3: Run tests**

Run: `XDEBUG_MODE=off vendor/bin/pest tests/Unit/Http/Controllers/Api/Webhook/ --stop-on-failure`
Expected: All PASS

- [ ] **Step 4: Commit**

```bash
git add app/Jobs/ProcessAlchemyWebhookJob.php app/Http/Controllers/Api/Webhook/AlchemyWebhookController.php
git commit -m "refactor: move webhook processing to queue job, add spam filter + reorg check"
```

---

### Task 10: Spam Token Filtering

Already handled in Task 9 — the `ProcessAlchemyWebhookJob` filters by `asset` name, only processing `USDC` and `USDT`. This prevents push notifications for scam/spam tokens.

For Solana, the existing `SolanaTokens::KNOWN_MINTS` already handles this in `HeliusTransactionProcessor`.

No additional task needed — spam filtering is built into the queue job.

---

### Task 11: Block Reorg Handling

Already handled in Task 9 — the `ProcessAlchemyWebhookJob` checks `$activity['removed'] === true` and skips reorged transactions.

For a more robust approach in future (marking stored transactions as reverted), this can be a follow-up task after the initial architecture ships.

No additional task needed — basic reorg handling is built into the queue job.

---

## Section C: Mobile Backend Handover

> Source: `finaegis-mobile/docs/BACKEND_HANDOVER_CARDS_KYC_RAMP.md`

### Task 12: Card Pre-Order Waitlist

**Files:**
- Create: `database/migrations/2026_04_07_000003_create_card_waitlist_table.php`
- Create: `app/Domain/Card/Models/CardWaitlist.php`
- Create: `app/Http/Controllers/Api/V1/CardWaitlistController.php`
- Create: `tests/Unit/Http/Controllers/Api/V1/CardWaitlistControllerTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

uses(Tests\TestCase::class);

it('joins the card waitlist', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/cards/waitlist');

    $response->assertStatus(201)
        ->assertJsonStructure(['id', 'position', 'joinedAt']);

    $this->assertDatabaseHas('card_waitlist', ['user_id' => $user->id]);
});

it('returns 409 if already on waitlist', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $this->postJson('/api/v1/cards/waitlist');
    $response = $this->postJson('/api/v1/cards/waitlist');

    $response->assertStatus(409)
        ->assertJsonStructure(['id', 'position', 'joinedAt']);
});

it('returns waitlist status for enrolled user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $this->postJson('/api/v1/cards/waitlist');
    $response = $this->getJson('/api/v1/cards/waitlist/status');

    $response->assertOk()
        ->assertJson(['joined' => true]);
});

it('returns not joined status for new user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->getJson('/api/v1/cards/waitlist/status');

    $response->assertOk()
        ->assertJson(['joined' => false, 'position' => null]);
});
```

- [ ] **Step 2: Create migration**

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
        Schema::create('card_waitlist', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->timestamp('joined_at');
            $table->timestamp('notified_at')->nullable();
            $table->boolean('converted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_waitlist');
    }
};
```

- [ ] **Step 3: Create model**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Card\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardWaitlist extends Model
{
    use HasUuids;

    protected $table = 'card_waitlist';

    protected $fillable = ['user_id', 'position', 'joined_at', 'notified_at', 'converted'];

    protected $casts = [
        'joined_at' => 'datetime',
        'notified_at' => 'datetime',
        'converted' => 'boolean',
        'position' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 4: Create controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Card\Models\CardWaitlist;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardWaitlistController extends Controller
{
    public function join(Request $request): JsonResponse
    {
        $user = $request->user();

        $existing = CardWaitlist::where('user_id', $user->id)->first();
        if ($existing !== null) {
            return response()->json([
                'id' => $existing->id,
                'position' => $existing->position,
                'joinedAt' => $existing->joined_at->toIso8601String(),
            ], 409);
        }

        $position = CardWaitlist::count() + 1;

        $waitlist = CardWaitlist::create([
            'user_id' => $user->id,
            'position' => $position,
            'joined_at' => now(),
        ]);

        return response()->json([
            'id' => $waitlist->id,
            'position' => $waitlist->position,
            'joinedAt' => $waitlist->joined_at->toIso8601String(),
        ], 201);
    }

    public function status(Request $request): JsonResponse
    {
        $waitlist = CardWaitlist::where('user_id', $request->user()->id)->first();

        if ($waitlist === null) {
            return response()->json([
                'joined' => false,
                'position' => null,
                'joinedAt' => null,
            ]);
        }

        return response()->json([
            'joined' => true,
            'position' => $waitlist->position,
            'joinedAt' => $waitlist->joined_at->toIso8601String(),
        ]);
    }
}
```

- [ ] **Step 5: Add routes to `routes/api.php`**

```php
// Card Waitlist (v1)
Route::prefix('v1/cards/waitlist')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/', [App\Http\Controllers\Api\V1\CardWaitlistController::class, 'join'])->name('api.v1.cards.waitlist.join');
    Route::get('/status', [App\Http\Controllers\Api\V1\CardWaitlistController::class, 'status'])->name('api.v1.cards.waitlist.status');
});
```

- [ ] **Step 6: Run tests**

Run: `XDEBUG_MODE=off vendor/bin/pest tests/Unit/Http/Controllers/Api/V1/CardWaitlistControllerTest.php --stop-on-failure`
Expected: All 4 tests PASS

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_04_07_000003_create_card_waitlist_table.php app/Domain/Card/Models/CardWaitlist.php app/Http/Controllers/Api/V1/CardWaitlistController.php tests/Unit/Http/Controllers/Api/V1/CardWaitlistControllerTest.php routes/api.php
git commit -m "feat: add card pre-order waitlist endpoints (POST join + GET status)"
```

---

### Task 13: Paid KYC Verification (3 Payment Methods)

> Source: `finaegis-mobile/docs/BACKEND_HANDOVER_CARDS_KYC_RAMP.md` section 2

**Files:**
- Create: `database/migrations/2026_04_07_000004_add_paid_kyc_fields.php`
- Create: `database/migrations/2026_04_07_000006_create_verification_payments_table.php`
- Create: `app/Http/Controllers/Api/V1/TrustCertPaymentController.php`
- Create: `app/Http/Controllers/Api/Webhook/StripeKycWebhookController.php`
- Create: `tests/Unit/Http/Controllers/Api/V1/TrustCertPaymentControllerTest.php`
- Modify: `routes/api.php`
- Modify: existing TrustCert requirements endpoint (add `verificationFee` field)

The mobile app supports 3 payment methods for KYC:
1. **`POST .../pay`** — Wallet balance (USDC deduction, instant)
2. **`POST .../pay/card`** — Stripe Checkout session (hosted payment page)
3. **`POST .../pay/iap`** — In-App Purchase receipt verification (iOS/Android)

All 3 result in the same `paid` status. A `verification_payments` audit table records all payments.

- [ ] **Step 1: Read existing TrustCert domain**

Read `app/Domain/TrustCert/` to understand the application model, status flow, and existing controller. Also read the full handover doc at `../finaegis-mobile/docs/BACKEND_HANDOVER_CARDS_KYC_RAMP.md` section 2.

- [ ] **Step 2: Create migrations**

Migration 1 — add payment fields to `trustcert_applications`:
```php
Schema::table('trustcert_applications', function (Blueprint $table) {
    $table->timestamp('paid_at')->nullable()->after('status');
    $table->string('payment_method', 20)->nullable()->after('paid_at');   // 'wallet', 'card', 'iap'
    $table->string('payment_receipt_id', 128)->nullable()->after('payment_method');
    $table->decimal('payment_amount', 10, 2)->nullable()->after('payment_receipt_id');
});
```

Migration 2 — create `verification_payments` audit table:
```php
Schema::create('verification_payments', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->uuid('application_id');
    $table->string('method', 20);           // 'wallet', 'card', 'iap'
    $table->decimal('amount', 10, 2);
    $table->string('currency', 3)->default('USD');
    $table->string('status', 20)->default('completed');
    $table->string('stripe_session_id', 255)->nullable();
    $table->string('iap_transaction_id', 255)->nullable();
    $table->string('platform', 10)->nullable(); // 'ios', 'android'
    $table->timestamps();
});
```

- [ ] **Step 3: Create TrustCertPaymentController with 3 methods**

```php
class TrustCertPaymentController extends Controller
{
    // Method 1: POST /api/v1/trustcert/applications/{id}/pay
    // Deducts USDC from wallet balance (internal ledger, NOT on-chain)
    // Returns: { receiptId, amount, currency, paidAt }
    // Errors: 402 (insufficient), 409 (already paid), 404

    // Method 2: POST /api/v1/trustcert/applications/{id}/pay/card
    // Creates Stripe Checkout Session for the fee amount
    // Returns: { sessionId, checkoutUrl, expiresAt }
    // Stripe webhook marks as paid asynchronously
    // success_url: 'zelta://kyc-payment-success?session_id={CHECKOUT_SESSION_ID}'
    // cancel_url: 'zelta://kyc-payment-cancel'
    // metadata: { application_id, user_id, level }

    // Method 3: POST /api/v1/trustcert/applications/{id}/pay/iap
    // Request: { receipt: "base64...", platform: "ios"|"android" }
    // iOS: Call Apple /verifyReceipt or App Store Server API v2
    // Android: Call Google Play purchases.products.get + acknowledge
    // Validate product_id = "kyc_verification_level_{N}"
    // Prevent receipt replay (check iap_transaction_id uniqueness)
}
```

Fee schedule config:
- Level 1 (Basic): $4.99
- Level 2 (Verified): $4.99
- Level 3 (Premium): $9.99

- [ ] **Step 4: Create StripeKycWebhookController**

Handle `checkout.session.completed` events:
1. Verify Stripe webhook signature (`STRIPE_KYC_WEBHOOK_SECRET`)
2. Extract `application_id` from session metadata
3. Verify payment amount matches expected fee
4. Mark application as `paid` with `payment_method = 'card'`
5. Store Stripe session ID in `verification_payments` audit table

- [ ] **Step 5: Update requirements endpoint**

Add `verificationFee` field to `GET /api/v1/trustcert/requirements/{level}` response.

- [ ] **Step 6: Add routes**

```php
// KYC Payment — 3 methods
Route::prefix('v1/trustcert/applications/{applicationId}')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/pay', [TrustCertPaymentController::class, 'payWallet'])->name('api.v1.trustcert.pay');
    Route::post('/pay/card', [TrustCertPaymentController::class, 'payCard'])->name('api.v1.trustcert.pay.card');
    Route::post('/pay/iap', [TrustCertPaymentController::class, 'payIap'])->name('api.v1.trustcert.pay.iap');
});

// Stripe KYC webhook (internal)
Route::post('webhooks/stripe/kyc', [StripeKycWebhookController::class, 'handle'])
    ->middleware('api.rate_limit:webhook')
    ->name('api.webhooks.stripe.kyc');
```

- [ ] **Step 7: Add env vars**

```
STRIPE_SECRET_KEY=sk_live_...
STRIPE_KYC_WEBHOOK_SECRET=whsec_...
```

- [ ] **Step 8: Write tests and run**

Test scenarios:
- Wallet pay with sufficient balance → 200 + receipt
- Wallet pay with insufficient balance → 402 ERR_CERT_501
- Card pay creates Stripe session → 200 + checkoutUrl
- IAP pay with valid receipt → 200 + receipt
- IAP pay with invalid receipt → 402 ERR_CERT_502
- Already paid → 409 for all methods
- Application not found → 404

Run: `XDEBUG_MODE=off vendor/bin/pest tests/Unit/Http/Controllers/Api/V1/TrustCertPaymentControllerTest.php --stop-on-failure`

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: add paid KYC verification with 3 payment methods (wallet, card, IAP)"
```

---

### Task 14: Stripe Bridge Ramp Migration

**Files:**
- Create: `app/Domain/Ramp/Services/StripeBridgeService.php` — Stripe Bridge API client
- Create: `app/Http/Controllers/Api/Webhook/StripeBridgeWebhookController.php`
- Create: `database/migrations/2026_04_07_000005_add_stripe_bridge_fields.php`
- Modify: `app/Http/Controllers/Api/V1/RampController.php` — swap Onramper → Stripe
- Modify: `routes/api.php` — add Stripe Bridge webhook route
- Modify: `.env.example` files — add `STRIPE_SECRET_KEY`, `STRIPE_BRIDGE_WEBHOOK_SECRET`, `STRIPE_BRIDGE_ENABLED`

This is the largest task. The subagent should:

1. Read the existing `RampController` and `RampSession` model to understand current structure
2. Read the mobile handover doc at `../finaegis-mobile/docs/BACKEND_HANDOVER_CARDS_KYC_RAMP.md` section 3 for exact API contracts
3. Implement the Stripe Bridge service with: session creation, quote fetching, status mapping
4. Keep the same endpoint paths (`/api/v1/ramp/*`) — mobile app doesn't need changes
5. Implement webhook handler for `crypto_onramp.session.updated` and `crypto_onramp.session.completed`
6. Map Stripe statuses: initialized→pending, payment_pending→processing, fulfilled→completed, payment_failed→failed, expired→expired

- [ ] **Step 1: Create migration for Stripe fields**

```php
Schema::table('ramp_sessions', function (Blueprint $table) {
    $table->string('stripe_session_id', 255)->nullable();
    $table->string('stripe_client_secret', 255)->nullable();
});
```

- [ ] **Step 2: Create StripeBridgeService**

API client wrapping Stripe's crypto onramp API. Methods:
- `createSession(type, fiatCurrency, fiatAmount, cryptoCurrency, walletAddress): array`
- `getQuote(type, fiatCurrency, amount, cryptoCurrency): array`
- `getSupportedCurrencies(): array`

- [ ] **Step 3: Create StripeBridgeWebhookController**

Verify Stripe webhook signature, update session status in DB.

- [ ] **Step 4: Update RampController**

Swap the existing Onramper integration to call `StripeBridgeService` instead. Maintain identical response shapes per the handover doc.

- [ ] **Step 5: Add routes**

```php
Route::post('webhooks/stripe/bridge', [StripeBridgeWebhookController::class, 'handle'])
    ->middleware('api.rate_limit:webhook')
    ->name('api.webhooks.stripe.bridge');
```

- [ ] **Step 6: Add env vars to example files**

```
STRIPE_SECRET_KEY=
STRIPE_BRIDGE_WEBHOOK_SECRET=
STRIPE_BRIDGE_ENABLED=false
```

- [ ] **Step 7: Run quality checks**

```bash
./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php
XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G
XDEBUG_MODE=off vendor/bin/pest --parallel --stop-on-failure
```

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: migrate ramp from Onramper to Stripe Bridge with webhook handler"
```

---

## Deletion Summary

After all tasks complete, these Alchemy webhook configurations are **removed from env vars** and managed in the `webhook_endpoints` DB table instead:

| Removed Env Var | Replacement |
|----------------|-------------|
| `ALCHEMY_WEBHOOK_SIGNING_KEY_*` (6 vars) | `webhook_endpoints.signing_key` (encrypted) |
| `ALCHEMY_SOLANA_WEBHOOK_ID` | Solana uses Helius, not Alchemy |
| `SOLANA_WEBHOOK_PROVIDER` | Always Helius now |

| Kept Env Var | Purpose |
|-------------|---------|
| `ALCHEMY_NOTIFY_TOKEN` | API auth for webhook creation/management |
| `ALCHEMY_API_KEY` | RPC/balance queries |
| `HELIUS_API_KEY` | Solana webhook + RPC |
| `HELIUS_WEBHOOK_ID` | Solana webhook (pre-created in Helius dashboard) |

| New Env Var | Purpose |
|-------------|---------|
| `STRIPE_SECRET_KEY` | Stripe API for KYC card payments + Bridge ramp |
| `STRIPE_KYC_WEBHOOK_SECRET` | Stripe webhook signing for KYC checkout |
| `STRIPE_BRIDGE_WEBHOOK_SECRET` | Stripe webhook signing for ramp sessions |
| `STRIPE_BRIDGE_ENABLED` | Feature flag for Stripe Bridge ramp |

## Production Migration Checklist

### Section A: Webhook Infrastructure
1. Deploy code + run migrations (webhook_endpoints, unique tx constraint)
2. Run `php artisan evm:sync-webhooks` — auto-creates webhooks and syncs addresses
3. Verify webhooks appear in Alchemy Dashboard
4. Delete old contract-monitoring webhooks from Alchemy Dashboard (the 6 USDC/USDT contract addresses)
5. Remove old `ALCHEMY_WEBHOOK_SIGNING_KEY_*` env vars from production
6. Verify Helius Solana webhook is active and receiving

### Section B: Webhook Hardening
7. Verify unique constraint migration ran (no duplicate tx_hash rows pre-existing)
8. Verify queue workers are processing `ProcessAlchemyWebhookJob` (Redis + Horizon)

### Section C: Mobile Handover
9. Run card_waitlist + verification_payments + ramp migrations
10. Set `STRIPE_SECRET_KEY`, `STRIPE_KYC_WEBHOOK_SECRET` in production
11. Create Stripe webhook endpoint for KYC: `https://zelta.app/api/webhooks/stripe/kyc`
12. Set `STRIPE_BRIDGE_WEBHOOK_SECRET`, `STRIPE_BRIDGE_ENABLED=true` when ready
13. Create Stripe webhook endpoint for ramp: `https://zelta.app/api/webhooks/stripe/bridge`
14. Create IAP products in App Store Connect + Google Play Console (6 products total)
15. Remove Onramper API keys from production after Stripe Bridge is verified
