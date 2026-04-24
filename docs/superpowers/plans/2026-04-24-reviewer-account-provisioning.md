# Reviewer/Demo Account Provisioning Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a general-purpose account-provisioning domain that lets operators seed app-store reviewer accounts (and future personas) with scoped, auditable, reversible security bypasses.

**Architecture:** Hybrid model — a new `account_flags` table drives explicit security bypasses at ~5 existing enforcement points, while UI content (cards, TrustCerts, balances, rewards) is real seeded rows. A minimal `AccountProfile` interface with one concrete `ReviewerAccountProfile` keeps the system extensible. Feature branch: `feat/account-provisioning-reviewer` (already created).

**Tech Stack:** PHP 8.4 / Laravel 12 / MySQL 8 / Redis / Pest / PHPStan Level 8. Follows existing DDD layout under `app/Domain/` (55 existing domains → 56 after this).

**Spec:** `docs/superpowers/specs/2026-04-24-reviewer-account-provisioning-design.md`

---

## File Structure

### Create

- `database/migrations/2026_04_24_000001_create_account_flags_table.php`
- `app/Domain/AccountProvisioning/Models/AccountFlag.php`
- `app/Domain/AccountProvisioning/Contracts/AccountProfile.php`
- `app/Domain/AccountProvisioning/ValueObjects/ProvisioningContext.php`
- `app/Domain/AccountProvisioning/Services/AccountFlagsService.php`
- `app/Domain/AccountProvisioning/Services/AccountProvisioningService.php`
- `app/Domain/AccountProvisioning/Profiles/ReviewerAccountProfile.php`
- `app/Domain/AccountProvisioning/Seeders/WalletSeeder.php`
- `app/Domain/AccountProvisioning/Seeders/BalanceSeeder.php`
- `app/Domain/AccountProvisioning/Seeders/CardSeeder.php`
- `app/Domain/AccountProvisioning/Seeders/TrustCertSeeder.php`
- `app/Domain/AccountProvisioning/Seeders/RewardsSeeder.php`
- `app/Domain/AccountProvisioning/module.json`
- `app/Console/Commands/AccountProvisionReviewerCommand.php`
- `app/Console/Commands/AccountListReviewersCommand.php`
- `app/Console/Commands/AccountDisableReviewerCommand.php`
- `app/Console/Commands/AccountPurgeReviewerCommand.php`
- `tests/Unit/AccountProvisioning/AccountFlagsServiceTest.php`
- `tests/Unit/AccountProvisioning/Seeders/WalletSeederTest.php`
- `tests/Unit/AccountProvisioning/Seeders/BalanceSeederTest.php`
- `tests/Unit/AccountProvisioning/Seeders/CardSeederTest.php`
- `tests/Unit/AccountProvisioning/Seeders/TrustCertSeederTest.php`
- `tests/Unit/AccountProvisioning/Seeders/RewardsSeederTest.php`
- `tests/Unit/AccountProvisioning/ReviewerAccountProfileTest.php`
- `tests/Unit/Models/UserEffectiveKycLevelTest.php`
- `tests/Feature/AccountProvisioning/ProvisionReviewerCommandTest.php`
- `tests/Feature/AccountProvisioning/DisableReviewerCommandTest.php`
- `tests/Feature/AccountProvisioning/ListReviewersCommandTest.php`
- `tests/Feature/AccountProvisioning/PurgeReviewerCommandTest.php`
- `tests/Feature/AccountProvisioning/Bypasses/AttestationBypassTest.php`
- `tests/Feature/AccountProvisioning/Bypasses/RateLimitBypassTest.php`
- `tests/Feature/AccountProvisioning/Bypasses/SanctionsBypassTest.php`
- `tests/Feature/AccountProvisioning/Bypasses/NotificationSuppressionTest.php`
- `tests/Feature/AccountProvisioning/ExpirySweepTest.php`
- `docs/operations/reviewer-accounts.md`
- `docs/security/account-flags.md`

### Modify

- `app/Models/User.php` — add `accountFlag()` relation, `effectiveKycLevel()` accessor
- `app/Domain/Mobile/Services/BiometricJWTService.php:236-237` — consult flag before calling attestation verifiers
- `app/Http/Middleware/ApiRateLimitMiddleware.php` — short-circuit when flag set
- `app/Http/Middleware/GraphQLRateLimitMiddleware.php` — same
- `app/Domain/AgentProtocol/Workflows/Activities/PerformAmlScreeningActivity.php` — return cleared when flag set
- `routes/console.php` — schedule daily `account:disable-reviewer --all-expired`
- `graphql/schema.graphql` — add `#import accountProvisioning.graphql` (only if any GraphQL surface lands; skip if CLI-only — decide in Task 18)
- `CLAUDE.md` — add Essential Commands entry, CI row, domain count 56 → 57
- `docs/VERSION_ROADMAP.md` — patch-version entry
- `.env.production.example` + `.env.zelta.example` — no new env vars expected; skip unless Task 18 adds one

---

## Phase 1 — Foundation

### Task 1: Create the `account_flags` migration

**Files:**
- Create: `database/migrations/2026_04_24_000001_create_account_flags_table.php`

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
        Schema::create('account_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_review_account')->default(false)->index();
            $table->boolean('bypass_device_attestation')->default(false);
            $table->boolean('bypass_rate_limit')->default(false);
            $table->boolean('bypass_sanctions_screening')->default(false);
            $table->boolean('bypass_sms_otp')->default(false);
            $table->boolean('suppress_notifications')->default(false);
            $table->tinyInteger('kyc_override_level')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_flags');
    }
};
```

- [ ] **Step 2: Run migration locally, verify schema**

```bash
php artisan migrate --pretend  # dry-run first
php artisan migrate
php artisan db --execute="DESCRIBE account_flags" --database=mysql 2>/dev/null || php artisan tinker --execute="dump(Schema::getColumnListing('account_flags'));"
```

Expected: 14 columns listed (id, user_id, is_review_account, …, timestamps).

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_04_24_000001_create_account_flags_table.php
git commit -m "feat(provisioning): add account_flags migration

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: `AccountFlag` model + `AccountProvisioning` domain skeleton

**Files:**
- Create: `app/Domain/AccountProvisioning/Models/AccountFlag.php`
- Create: `app/Domain/AccountProvisioning/module.json`
- Modify: `app/Models/User.php` — add relation

- [ ] **Step 1: Write the model**

```php
<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountFlag extends Model
{
    protected $table = 'account_flags';

    protected $fillable = [
        'user_id',
        'is_review_account',
        'bypass_device_attestation',
        'bypass_rate_limit',
        'bypass_sanctions_screening',
        'bypass_sms_otp',
        'suppress_notifications',
        'kyc_override_level',
        'note',
        'expires_at',
        'created_by',
        'disabled_at',
    ];

    protected $casts = [
        'is_review_account'          => 'bool',
        'bypass_device_attestation'  => 'bool',
        'bypass_rate_limit'          => 'bool',
        'bypass_sanctions_screening' => 'bool',
        'bypass_sms_otp'             => 'bool',
        'suppress_notifications'     => 'bool',
        'kyc_override_level'         => 'int',
        'expires_at'                 => 'datetime',
        'disabled_at'                => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        if ($this->disabled_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
```

- [ ] **Step 2: Write `module.json`**

```json
{
    "$schema": "https://finaegis.io/schemas/module.json",
    "name": "finaegis/account-provisioning",
    "version": "1.0.0",
    "description": "Operator-only provisioning of pre-seeded personas (reviewer/demo accounts) with scoped security bypasses",
    "type": "core",
    "dependencies": {
        "required": {
            "finaegis/shared": "^1.0",
            "finaegis/user": "^1.3"
        },
        "optional": {
            "finaegis/wallet": "*",
            "finaegis/card-issuance": "*",
            "finaegis/rewards": "*",
            "finaegis/trust-cert": "*"
        }
    },
    "provides": {
        "interfaces": [
            "App\\Domain\\AccountProvisioning\\Contracts\\AccountProfile"
        ],
        "events": [],
        "commands": [
            "account:provision-reviewer",
            "account:list-reviewers",
            "account:disable-reviewer",
            "account:purge-reviewer"
        ]
    }
}
```

- [ ] **Step 3: Add relation + accessor stub to `User` model**

Modify `app/Models/User.php` — add after the existing relations block:

```php
public function accountFlag(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(\App\Domain\AccountProvisioning\Models\AccountFlag::class);
}
```

- [ ] **Step 4: Run PHPStan to verify no regressions**

```bash
XDEBUG_MODE=off vendor/bin/phpstan analyse app/Domain/AccountProvisioning app/Models/User.php --memory-limit=2G
```

Expected: 0 errors on these paths.

- [ ] **Step 5: Commit**

```bash
git add app/Domain/AccountProvisioning/Models/AccountFlag.php \
        app/Domain/AccountProvisioning/module.json \
        app/Models/User.php
git commit -m "feat(provisioning): AccountFlag model, User relation, module.json

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: `AccountFlagsService` with per-request cache

**Files:**
- Create: `app/Domain/AccountProvisioning/Services/AccountFlagsService.php`
- Test: `tests/Unit/AccountProvisioning/AccountFlagsServiceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\AccountProvisioning;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Domain\AccountProvisioning\Services\AccountFlagsService;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

it('returns null when user has no flag row', function () {
    $user = User::factory()->create();
    $service = new AccountFlagsService();

    expect($service->forUser($user))->toBeNull();
    expect($service->hasReviewBypass($user, 'device_attestation'))->toBeFalse();
});

it('returns the flag row and reports bypasses', function () {
    $user = User::factory()->create();
    AccountFlag::create([
        'user_id'                   => $user->id,
        'is_review_account'         => true,
        'bypass_device_attestation' => true,
        'bypass_rate_limit'         => false,
    ]);

    $service = new AccountFlagsService();

    expect($service->forUser($user))->not->toBeNull();
    expect($service->hasReviewBypass($user, 'device_attestation'))->toBeTrue();
    expect($service->hasReviewBypass($user, 'rate_limit'))->toBeFalse();
});

it('short-circuits when flag is disabled or expired', function () {
    $user = User::factory()->create();
    AccountFlag::create([
        'user_id'                   => $user->id,
        'is_review_account'         => true,
        'bypass_device_attestation' => true,
        'disabled_at'               => now(),
    ]);

    $service = new AccountFlagsService();

    expect($service->hasReviewBypass($user, 'device_attestation'))->toBeFalse();
});

it('caches lookups per request', function () {
    $user = User::factory()->create();
    AccountFlag::create(['user_id' => $user->id, 'is_review_account' => true]);

    $service = new AccountFlagsService();
    $service->forUser($user);

    \DB::enableQueryLog();
    $service->forUser($user);
    $service->forUser($user);
    expect(\DB::getQueryLog())->toBeEmpty();
});
```

- [ ] **Step 2: Run test, verify it fails**

```bash
./vendor/bin/pest tests/Unit/AccountProvisioning/AccountFlagsServiceTest.php
```

Expected: FAIL — class `AccountFlagsService` does not exist.

- [ ] **Step 3: Implement the service**

```php
<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Services;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Models\User;

class AccountFlagsService
{
    /** @var array<int, ?AccountFlag> */
    private array $cache = [];

    public function forUser(User|int $user): ?AccountFlag
    {
        $userId = $user instanceof User ? (int) $user->id : $user;

        if (array_key_exists($userId, $this->cache)) {
            return $this->cache[$userId];
        }

        $flag = AccountFlag::where('user_id', $userId)->first();

        return $this->cache[$userId] = $flag;
    }

    public function hasReviewBypass(User|int $user, string $bypass): bool
    {
        $flag = $this->forUser($user);

        if ($flag === null || ! $flag->isActive()) {
            return false;
        }

        $column = 'bypass_' . $bypass;

        if (! in_array($column, [
            'bypass_device_attestation',
            'bypass_rate_limit',
            'bypass_sanctions_screening',
            'bypass_sms_otp',
        ], true)) {
            return false;
        }

        $hit = (bool) $flag->{$column};

        if ($hit) {
            logger()->info('bypass.fired', [
                'user_id' => $flag->user_id,
                'bypass'  => $bypass,
                'reason'  => 'review_account',
            ]);
        }

        return $hit;
    }

    public function shouldSuppressNotifications(User|int $user): bool
    {
        $flag = $this->forUser($user);

        return $flag !== null && $flag->isActive() && $flag->suppress_notifications;
    }

    public function kycOverrideLevel(User|int $user): ?int
    {
        $flag = $this->forUser($user);

        if ($flag === null || ! $flag->isActive()) {
            return null;
        }

        return $flag->kyc_override_level;
    }

    public function forget(User|int $user): void
    {
        $userId = $user instanceof User ? (int) $user->id : $user;
        unset($this->cache[$userId]);
    }
}
```

Register as a singleton in `app/Providers/AppServiceProvider.php::register()`:

```php
$this->app->singleton(\App\Domain\AccountProvisioning\Services\AccountFlagsService::class);
```

- [ ] **Step 4: Run test, verify it passes**

```bash
./vendor/bin/pest tests/Unit/AccountProvisioning/AccountFlagsServiceTest.php
```

Expected: 4 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Domain/AccountProvisioning/Services/AccountFlagsService.php \
        app/Providers/AppServiceProvider.php \
        tests/Unit/AccountProvisioning/AccountFlagsServiceTest.php
git commit -m "feat(provisioning): AccountFlagsService with per-request cache + bypass.fired logging

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: `User::effectiveKycLevel()` accessor

**Files:**
- Modify: `app/Models/User.php`
- Test: `tests/Unit/Models/UserEffectiveKycLevelTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

it('returns real kyc_level when no flag override exists', function () {
    $user = User::factory()->create(['kyc_level' => 1]);
    expect($user->effectiveKycLevel())->toBe(1);
});

it('returns override level when flag is active and override is set', function () {
    $user = User::factory()->create(['kyc_level' => 0]);
    AccountFlag::create([
        'user_id'            => $user->id,
        'is_review_account'  => true,
        'kyc_override_level' => 2,
    ]);

    expect($user->effectiveKycLevel())->toBe(2);
});

it('falls back to real level when flag is disabled', function () {
    $user = User::factory()->create(['kyc_level' => 1]);
    AccountFlag::create([
        'user_id'            => $user->id,
        'is_review_account'  => true,
        'kyc_override_level' => 2,
        'disabled_at'        => now(),
    ]);

    expect($user->effectiveKycLevel())->toBe(1);
});

it('falls back to real level when override is null', function () {
    $user = User::factory()->create(['kyc_level' => 1]);
    AccountFlag::create([
        'user_id'            => $user->id,
        'is_review_account'  => true,
        'kyc_override_level' => null,
    ]);

    expect($user->effectiveKycLevel())->toBe(1);
});
```

- [ ] **Step 2: Run test, verify it fails**

```bash
./vendor/bin/pest tests/Unit/Models/UserEffectiveKycLevelTest.php
```

Expected: FAIL — method `effectiveKycLevel` does not exist.

- [ ] **Step 3: Add the method to `app/Models/User.php`**

Add right after the existing `accountFlag()` relation:

```php
public function effectiveKycLevel(): int
{
    $service = app(\App\Domain\AccountProvisioning\Services\AccountFlagsService::class);
    $override = $service->kycOverrideLevel($this);

    if ($override !== null) {
        return $override;
    }

    return (int) ($this->kyc_level ?? 0);
}
```

- [ ] **Step 4: Verify tests pass**

```bash
./vendor/bin/pest tests/Unit/Models/UserEffectiveKycLevelTest.php
```

Expected: 4 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Models/User.php tests/Unit/Models/UserEffectiveKycLevelTest.php
git commit -m "feat(provisioning): User::effectiveKycLevel() respects flag override

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Phase 2 — Profile framework + sub-seeders

### Task 5: `AccountProfile` interface + `ProvisioningContext` VO

**Files:**
- Create: `app/Domain/AccountProvisioning/Contracts/AccountProfile.php`
- Create: `app/Domain/AccountProvisioning/ValueObjects/ProvisioningContext.php`

- [ ] **Step 1: Write the interface**

```php
<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Contracts;

use App\Domain\AccountProvisioning\ValueObjects\ProvisioningContext;
use App\Models\User;

interface AccountProfile
{
    /** Profile slug, e.g. 'reviewer'. */
    public function name(): string;

    /**
     * Flag column => value map that will be written to account_flags.
     *
     * @return array<string, bool|int|string|null>
     */
    public function flags(ProvisioningContext $ctx): array;

    /** Apply seeded content (wallets, balances, cards, etc.) for the user. */
    public function provision(User $user, ProvisioningContext $ctx): void;
}
```

- [ ] **Step 2: Write the VO**

```php
<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\ValueObjects;

use Carbon\CarbonImmutable;

final readonly class ProvisioningContext
{
    public function __construct(
        public string $email,
        public string $name,
        public string $region,
        public ?CarbonImmutable $expiresAt,
        public ?string $note,
        public int $operatorId,
        public bool $dryRun = false,
    ) {}
}
```

- [ ] **Step 3: PHPStan check, commit**

```bash
XDEBUG_MODE=off vendor/bin/phpstan analyse app/Domain/AccountProvisioning --memory-limit=2G
```

Expected: 0 errors.

```bash
git add app/Domain/AccountProvisioning/Contracts app/Domain/AccountProvisioning/ValueObjects
git commit -m "feat(provisioning): AccountProfile interface + ProvisioningContext VO

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 6: `WalletSeeder`

**Files:**
- Create: `app/Domain/AccountProvisioning/Seeders/WalletSeeder.php`
- Test: `tests/Unit/AccountProvisioning/Seeders/WalletSeederTest.php`

**Prerequisite research (do this first, ~3 minutes):** the engineer must trace the existing user-registration wallet-provisioning call in this codebase. Start by grepping: `grep -rn "RegisteredUser\|UserRegistered" app/Listeners app/Domain/User --include="*.php"`. Use whichever service is invoked there (likely a `WalletCreationService` or similar). Do NOT fabricate a new wallet-creation path — re-use production's. The seeder is a thin idempotent wrapper: `firstOrCreate` on the natural key (e.g. `user_id + chain`), invoke the existing service only when the row doesn't exist.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\AccountProvisioning\Seeders;

use App\Domain\AccountProvisioning\Seeders\WalletSeeder;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

it('creates a polygon EVM wallet and a solana wallet for the user', function () {
    $user = User::factory()->create();
    $seeder = app(WalletSeeder::class);

    $seeder->seed($user);

    // Adjust these assertions to match the production wallet-model table(s)
    expect($user->fresh()->walletAddresses()->where('chain', 'polygon')->exists())->toBeTrue();
    expect($user->fresh()->walletAddresses()->where('chain', 'solana')->exists())->toBeTrue();
});

it('is idempotent: second call does not duplicate rows', function () {
    $user = User::factory()->create();
    $seeder = app(WalletSeeder::class);

    $seeder->seed($user);
    $seeder->seed($user);

    expect($user->fresh()->walletAddresses()->count())->toBe(2);
});
```

- [ ] **Step 2: Run test, verify it fails**

```bash
./vendor/bin/pest tests/Unit/AccountProvisioning/Seeders/WalletSeederTest.php
```

Expected: FAIL — class does not exist, OR relation `walletAddresses` does not exist. If the latter, adjust the test to the real relation name discovered in prerequisite research.

- [ ] **Step 3: Implement seeder**

```php
<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Seeders;

use App\Models\User;

class WalletSeeder
{
    public function seed(User $user): void
    {
        // 1. Call the production wallet-provisioning service once per chain.
        // 2. Wrap each call in firstOrCreate semantics on (user_id, chain).
        // 3. Reuse SolanaAddressHelper::deriveForUser(userId, appKey) per CLAUDE.md.

        // Replace the following stubs with real service calls discovered in prerequisite research.
        $walletService = app(\App\Domain\Wallet\Services\WalletProvisioningService::class);
        $walletService->ensureFor($user, 'polygon');
        $walletService->ensureFor($user, 'solana');
    }
}
```

Note: if the production service does not expose an `ensureFor`-style idempotent method, wrap `firstOrCreate` at the Eloquent level inside the seeder instead of calling the service. The test in Step 1 is the contract — make it pass with the cleanest wrapper.

- [ ] **Step 4: Run test, verify it passes**

```bash
./vendor/bin/pest tests/Unit/AccountProvisioning/Seeders/WalletSeederTest.php
```

Expected: 2 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Domain/AccountProvisioning/Seeders/WalletSeeder.php \
        tests/Unit/AccountProvisioning/Seeders/WalletSeederTest.php
git commit -m "feat(provisioning): idempotent WalletSeeder for EVM+Solana

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 7: `BalanceSeeder`

**Files:**
- Create: `app/Domain/AccountProvisioning/Seeders/BalanceSeeder.php`
- Test: `tests/Unit/AccountProvisioning/Seeders/BalanceSeederTest.php`

**Prerequisite:** grep for the production balance-credit service (likely `app/Domain/Wallet/Services/` or `app/Domain/Account/Services/`). Must use bcmath (per CLAUDE.md: never `(float)` for money). Must wrap writes in `DB::transaction()` with `lockForUpdate()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\AccountProvisioning\Seeders;

use App\Domain\AccountProvisioning\Seeders\BalanceSeeder;
use App\Domain\AccountProvisioning\Seeders\WalletSeeder;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

it('sets unshielded USDC balance on polygon to target', function () {
    $user = User::factory()->create();
    app(WalletSeeder::class)->seed($user);
    $seeder = app(BalanceSeeder::class);

    $seeder->seed($user, unshieldedUsdc: '25.00', shieldedUsdc: '10.00');

    expect($user->balanceFor('USDC', 'polygon', shielded: false))->toEqualString('25.00');
    expect($user->balanceFor('USDC', 'polygon', shielded: true))->toEqualString('10.00');
});

it('is idempotent: re-seeding sets the same target, not accumulative', function () {
    $user = User::factory()->create();
    app(WalletSeeder::class)->seed($user);

    app(BalanceSeeder::class)->seed($user, unshieldedUsdc: '25.00', shieldedUsdc: '10.00');
    app(BalanceSeeder::class)->seed($user, unshieldedUsdc: '25.00', shieldedUsdc: '10.00');

    expect($user->balanceFor('USDC', 'polygon', shielded: false))->toEqualString('25.00');
});
```

- [ ] **Step 2: Run, verify failure**

```bash
./vendor/bin/pest tests/Unit/AccountProvisioning/Seeders/BalanceSeederTest.php
```

Expected: FAIL.

- [ ] **Step 3: Implement seeder**

```php
<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class BalanceSeeder
{
    public function seed(User $user, string $unshieldedUsdc, string $shieldedUsdc): void
    {
        DB::transaction(function () use ($user, $unshieldedUsdc, $shieldedUsdc): void {
            $this->setBalance($user, asset: 'USDC', chain: 'polygon', shielded: false, target: $unshieldedUsdc);
            $this->setBalance($user, asset: 'USDC', chain: 'polygon', shielded: true, target: $shieldedUsdc);
        });
    }

    private function setBalance(User $user, string $asset, string $chain, bool $shielded, string $target): void
    {
        // Normalize to 4 decimals per bcmath convention (CLAUDE.md).
        $normalized = bcadd($target, '0', 4);

        // Replace with production's set-balance-to-target service.
        // If none exists, compute delta vs current balance and credit/debit the difference.
        app(\App\Domain\Wallet\Services\BalanceService::class)
            ->setBalanceForReviewAccount($user, $asset, $chain, $shielded, $normalized);
    }
}
```

If the production codebase has no "set to target" balance method, add one *only* in the review-account domain (scoped, never exposed outside). Never mutate balances directly without the existing accounting/ledger domain's approval — this is a bcmath-critical area per CLAUDE.md. If in doubt, route through a compensating ledger entry.

- [ ] **Step 4: Run, verify pass**

```bash
./vendor/bin/pest tests/Unit/AccountProvisioning/Seeders/BalanceSeederTest.php
```

- [ ] **Step 5: Commit**

```bash
git add app/Domain/AccountProvisioning/Seeders/BalanceSeeder.php \
        tests/Unit/AccountProvisioning/Seeders/BalanceSeederTest.php
git commit -m "feat(provisioning): idempotent BalanceSeeder with bcmath + DB::transaction

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 8: `CardSeeder`

**Files:**
- Create: `app/Domain/AccountProvisioning/Seeders/CardSeeder.php`
- Test: `tests/Unit/AccountProvisioning/Seeders/CardSeederTest.php`

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\AccountProvisioning\Seeders;

use App\Domain\AccountProvisioning\Seeders\CardSeeder;
use App\Domain\CardIssuance\Models\Card;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

it('creates one active virtual card for the user', function () {
    $user = User::factory()->create();

    app(CardSeeder::class)->seed($user);

    $cards = Card::where('user_id', $user->id)->where('status', 'active')->get();
    expect($cards)->toHaveCount(1);
    expect($cards->first()->type)->toBe('virtual');
});

it('is idempotent', function () {
    $user = User::factory()->create();
    app(CardSeeder::class)->seed($user);
    app(CardSeeder::class)->seed($user);

    expect(Card::where('user_id', $user->id)->count())->toBe(1);
});
```

- [ ] **Step 2: Run, fail**

```bash
./vendor/bin/pest tests/Unit/AccountProvisioning/Seeders/CardSeederTest.php
```

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Seeders;

use App\Domain\CardIssuance\Models\Card;
use App\Models\User;

class CardSeeder
{
    public function seed(User $user): void
    {
        Card::firstOrCreate(
            [
                'user_id' => $user->id,
                'type'    => 'virtual',
            ],
            [
                'status'     => 'active',
                'brand'      => 'visa',
                'last_four'  => '4242',
                'expires_at' => now()->addYears(3),
            ]
        );
    }
}
```

Note: the column set shown is the expected shape. Inspect `database/migrations/*card*` and `app/Domain/CardIssuance/Models/Card.php::$fillable` and adjust to the actual columns. Don't skip required columns the model enforces (e.g. `cardholder_id` FK).

- [ ] **Step 4: Run, pass**

- [ ] **Step 5: Commit**

```bash
git add app/Domain/AccountProvisioning/Seeders/CardSeeder.php \
        tests/Unit/AccountProvisioning/Seeders/CardSeederTest.php
git commit -m "feat(provisioning): idempotent CardSeeder (one active virtual card)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 9: `TrustCertSeeder`

**Files:**
- Create: `app/Domain/AccountProvisioning/Seeders/TrustCertSeeder.php`
- Test: `tests/Unit/AccountProvisioning/Seeders/TrustCertSeederTest.php`

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\AccountProvisioning\Seeders;

use App\Domain\AccountProvisioning\Seeders\TrustCertSeeder;
use App\Domain\TrustCert\Models\Certificate;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

it('issues one active trust certificate', function () {
    $user = User::factory()->create();

    app(TrustCertSeeder::class)->seed($user);

    expect(Certificate::where('user_id', $user->id)->where('status', 'active')->count())->toBe(1);
});

it('is idempotent', function () {
    $user = User::factory()->create();
    app(TrustCertSeeder::class)->seed($user);
    app(TrustCertSeeder::class)->seed($user);

    expect(Certificate::where('user_id', $user->id)->count())->toBe(1);
});
```

- [ ] **Step 2: Run, fail**

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Seeders;

use App\Domain\TrustCert\Models\Certificate;
use App\Models\User;

class TrustCertSeeder
{
    public function seed(User $user): void
    {
        Certificate::firstOrCreate(
            [
                'user_id' => $user->id,
                'tier'    => 'basic',
            ],
            [
                'status'     => 'active',
                'issued_at'  => now(),
                'expires_at' => now()->addYear(),
                'provider'   => 'review_bypass',
            ]
        );
    }
}
```

Inspect `app/Domain/TrustCert/Models/Certificate.php` for actual column names and adjust — `tier`, `status`, and `provider` column names may differ.

- [ ] **Step 4: Run, pass**

- [ ] **Step 5: Commit**

```bash
git add app/Domain/AccountProvisioning/Seeders/TrustCertSeeder.php \
        tests/Unit/AccountProvisioning/Seeders/TrustCertSeederTest.php
git commit -m "feat(provisioning): idempotent TrustCertSeeder

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 10: `RewardsSeeder`

**Files:**
- Create: `app/Domain/AccountProvisioning/Seeders/RewardsSeeder.php`
- Test: `tests/Unit/AccountProvisioning/Seeders/RewardsSeederTest.php`

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\AccountProvisioning\Seeders;

use App\Domain\AccountProvisioning\Seeders\RewardsSeeder;
use App\Domain\Rewards\Models\RewardProfile;
use App\Domain\Rewards\Models\RewardQuestCompletion;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

it('creates a reward profile with XP and one completed quest', function () {
    $user = User::factory()->create();

    app(RewardsSeeder::class)->seed($user);

    $profile = RewardProfile::where('user_id', $user->id)->first();
    expect($profile)->not->toBeNull();
    expect($profile->xp)->toBeGreaterThan(0);
    expect(RewardQuestCompletion::where('user_id', $user->id)->count())->toBe(1);
});

it('is idempotent', function () {
    $user = User::factory()->create();
    app(RewardsSeeder::class)->seed($user);
    app(RewardsSeeder::class)->seed($user);

    expect(RewardQuestCompletion::where('user_id', $user->id)->count())->toBe(1);
});
```

- [ ] **Step 2: Run, fail**

- [ ] **Step 3: Implement**

Inspect `MobileRewardsDemoSeeder` in `database/seeders/` for a reference pattern. Port the reviewer-appropriate subset (one profile, a modest XP, one quest completion). All writes `firstOrCreate`.

```php
<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Seeders;

use App\Domain\Rewards\Models\RewardProfile;
use App\Domain\Rewards\Models\RewardQuest;
use App\Domain\Rewards\Models\RewardQuestCompletion;
use App\Models\User;

class RewardsSeeder
{
    public function seed(User $user): void
    {
        $profile = RewardProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['xp' => 250, 'tier' => 'bronze'],
        );

        $quest = RewardQuest::firstWhere('slug', 'welcome') ?? RewardQuest::first();

        if ($quest !== null) {
            RewardQuestCompletion::firstOrCreate(
                ['user_id' => $user->id, 'quest_id' => $quest->id],
                ['completed_at' => now()]
            );
        }
    }
}
```

Adjust column names (`xp`, `tier`, `slug`, `quest_id`) to match the real models.

- [ ] **Step 4: Run, pass**

- [ ] **Step 5: Commit**

```bash
git add app/Domain/AccountProvisioning/Seeders/RewardsSeeder.php \
        tests/Unit/AccountProvisioning/Seeders/RewardsSeederTest.php
git commit -m "feat(provisioning): idempotent RewardsSeeder

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 11: `ReviewerAccountProfile`

**Files:**
- Create: `app/Domain/AccountProvisioning/Profiles/ReviewerAccountProfile.php`
- Test: `tests/Unit/AccountProvisioning/ReviewerAccountProfileTest.php`

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\AccountProvisioning;

use App\Domain\AccountProvisioning\Profiles\ReviewerAccountProfile;
use App\Domain\AccountProvisioning\ValueObjects\ProvisioningContext;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\TrustCert\Models\Certificate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

it('returns the expected flag payload', function () {
    $profile = app(ReviewerAccountProfile::class);
    $ctx = new ProvisioningContext(
        email: 'appreview@finaegis.com',
        name: 'App Reviewer',
        region: 'US',
        expiresAt: CarbonImmutable::now()->addDays(60),
        note: 'App Store review 2026-Q2',
        operatorId: 1,
    );

    $flags = $profile->flags($ctx);

    expect($flags['is_review_account'])->toBeTrue();
    expect($flags['bypass_device_attestation'])->toBeTrue();
    expect($flags['bypass_rate_limit'])->toBeTrue();
    expect($flags['bypass_sanctions_screening'])->toBeTrue();
    expect($flags['bypass_sms_otp'])->toBeTrue();
    expect($flags['suppress_notifications'])->toBeTrue();
    expect($flags['kyc_override_level'])->toBe(2);
    expect($flags['note'])->toBe('App Store review 2026-Q2');
});

it('provisions all content sub-seeders in one transaction', function () {
    $user = User::factory()->create();
    $ctx = new ProvisioningContext('e', 'n', 'US', null, null, 1);

    app(ReviewerAccountProfile::class)->provision($user, $ctx);

    expect(Card::where('user_id', $user->id)->count())->toBe(1);
    expect(Certificate::where('user_id', $user->id)->count())->toBe(1);
});

it('is idempotent end-to-end', function () {
    $user = User::factory()->create();
    $ctx = new ProvisioningContext('e', 'n', 'US', null, null, 1);

    $profile = app(ReviewerAccountProfile::class);
    $profile->provision($user, $ctx);
    $profile->provision($user, $ctx);

    expect(Card::where('user_id', $user->id)->count())->toBe(1);
    expect(Certificate::where('user_id', $user->id)->count())->toBe(1);
});
```

- [ ] **Step 2: Run, fail**

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Profiles;

use App\Domain\AccountProvisioning\Contracts\AccountProfile;
use App\Domain\AccountProvisioning\Seeders\BalanceSeeder;
use App\Domain\AccountProvisioning\Seeders\CardSeeder;
use App\Domain\AccountProvisioning\Seeders\RewardsSeeder;
use App\Domain\AccountProvisioning\Seeders\TrustCertSeeder;
use App\Domain\AccountProvisioning\Seeders\WalletSeeder;
use App\Domain\AccountProvisioning\ValueObjects\ProvisioningContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReviewerAccountProfile implements AccountProfile
{
    public function __construct(
        private readonly WalletSeeder $wallets,
        private readonly BalanceSeeder $balances,
        private readonly CardSeeder $cards,
        private readonly TrustCertSeeder $trustCert,
        private readonly RewardsSeeder $rewards,
    ) {}

    public function name(): string
    {
        return 'reviewer';
    }

    /** @return array<string, bool|int|string|null> */
    public function flags(ProvisioningContext $ctx): array
    {
        return [
            'is_review_account'          => true,
            'bypass_device_attestation'  => true,
            'bypass_rate_limit'          => true,
            'bypass_sanctions_screening' => true,
            'bypass_sms_otp'             => true,
            'suppress_notifications'     => true,
            'kyc_override_level'         => 2,
            'note'                       => $ctx->note,
            'expires_at'                 => $ctx->expiresAt,
            'created_by'                 => $ctx->operatorId,
        ];
    }

    public function provision(User $user, ProvisioningContext $ctx): void
    {
        DB::transaction(function () use ($user): void {
            $this->wallets->seed($user);
            $this->balances->seed($user, unshieldedUsdc: '25.00', shieldedUsdc: '10.00');
            $this->cards->seed($user);
            $this->trustCert->seed($user);
            $this->rewards->seed($user);
        });
    }
}
```

- [ ] **Step 4: Run, pass**

- [ ] **Step 5: Commit**

```bash
git add app/Domain/AccountProvisioning/Profiles/ReviewerAccountProfile.php \
        tests/Unit/AccountProvisioning/ReviewerAccountProfileTest.php
git commit -m "feat(provisioning): ReviewerAccountProfile orchestrates sub-seeders

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 12: `AccountProvisioningService` (top-level orchestrator)

**Files:**
- Create: `app/Domain/AccountProvisioning/Services/AccountProvisioningService.php`

This service is called by the CLI command. It does user creation/lookup, writes the flag row, and invokes the profile. No test file yet — the feature test for the CLI command in Task 18 is the contract for this service. Keeping it thin avoids double-testing.

- [ ] **Step 1: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Services;

use App\Domain\AccountProvisioning\Contracts\AccountProfile;
use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Domain\AccountProvisioning\ValueObjects\ProvisioningContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AccountProvisioningService
{
    public function __construct(
        private readonly AccountFlagsService $flags,
    ) {}

    /**
     * @return array{user: User, password_action: 'created'|'rotated'|'unchanged'}
     */
    public function apply(
        AccountProfile $profile,
        ProvisioningContext $ctx,
        ?string $password,
        bool $rotatePassword,
        bool $forceConvert,
    ): array {
        return DB::transaction(function () use ($profile, $ctx, $password, $rotatePassword, $forceConvert) {
            $existing = User::where('email', $ctx->email)->first();

            if ($existing !== null) {
                $flag = $existing->accountFlag;

                if (($flag === null || ! $flag->is_review_account) && ! $forceConvert) {
                    throw new RuntimeException(
                        "Email {$ctx->email} belongs to a non-review user. Use --force-convert to override (blocked in production)."
                    );
                }

                $user = $existing;
                $action = 'unchanged';

                if ($rotatePassword && $password !== null) {
                    $user->password = Hash::make($password);
                    $user->save();
                    $this->flags->forget($user);
                    $action = 'rotated';
                }
            } else {
                if ($password === null) {
                    throw new RuntimeException('Password must be provided or generated before calling apply().');
                }

                $user = User::create([
                    'name'              => $ctx->name,
                    'email'             => $ctx->email,
                    'password'          => Hash::make($password),
                    'email_verified_at' => now(),
                ]);
                $action = 'created';
            }

            AccountFlag::updateOrCreate(
                ['user_id' => $user->id],
                $profile->flags($ctx),
            );

            $this->flags->forget($user);

            if (! $ctx->dryRun) {
                $profile->provision($user, $ctx);
            }

            return ['user' => $user, 'password_action' => $action];
        });
    }

    public function disable(User $user): void
    {
        $flag = $user->accountFlag;
        if ($flag === null) {
            return;
        }

        $flag->update([
            'bypass_device_attestation'  => false,
            'bypass_rate_limit'          => false,
            'bypass_sanctions_screening' => false,
            'bypass_sms_otp'             => false,
            'suppress_notifications'     => false,
            'kyc_override_level'         => null,
            'disabled_at'                => now(),
        ]);

        $user->tokens()->delete();
        $this->flags->forget($user);
    }

    public function reEnable(User $user, AccountProfile $profile, ProvisioningContext $ctx): void
    {
        AccountFlag::updateOrCreate(
            ['user_id' => $user->id],
            array_merge($profile->flags($ctx), ['disabled_at' => null]),
        );
        $this->flags->forget($user);
    }
}
```

- [ ] **Step 2: PHPStan check, commit**

```bash
XDEBUG_MODE=off vendor/bin/phpstan analyse app/Domain/AccountProvisioning --memory-limit=2G
```

```bash
git add app/Domain/AccountProvisioning/Services/AccountProvisioningService.php
git commit -m "feat(provisioning): AccountProvisioningService orchestrates user + flag + profile

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Phase 3 — Enforcement plumbing

### Task 13: Attestation bypass in `BiometricJWTService`

**Files:**
- Modify: `app/Domain/Mobile/Services/BiometricJWTService.php:236-237`
- Test: `tests/Feature/AccountProvisioning/Bypasses/AttestationBypassTest.php`

**Context:** attestation verifiers are called from `BiometricJWTService`. The user is known at that call site — patch the caller, not the verifiers (keeps verifiers pure).

- [ ] **Step 1: Failing feature test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\AccountProvisioning\Bypasses;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Domain\Mobile\Services\BiometricJWTService;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

it('short-circuits attestation when bypass flag is set', function () {
    Log::spy();
    $user = User::factory()->create();
    AccountFlag::create([
        'user_id'                   => $user->id,
        'is_review_account'         => true,
        'bypass_device_attestation' => true,
    ]);

    $service = app(BiometricJWTService::class);

    // Call whatever public method on BiometricJWTService takes (user, platform, attestation) — find via public API.
    $result = $service->verifyAttestationForUser($user, 'ios', 'AAAA');

    expect($result)->toBeTrue();
    Log::shouldHaveReceived('info')->withArgs(fn ($msg, $ctx) => $msg === 'bypass.fired' && $ctx['bypass'] === 'device_attestation');
});

it('does not bypass for a regular user', function () {
    $user = User::factory()->create();
    $service = app(BiometricJWTService::class);

    // Regular user: verifier returns false for empty attestation.
    $result = $service->verifyAttestationForUser($user, 'ios', 'AAAA');

    expect($result)->toBeFalse();
});
```

- [ ] **Step 2: Run, fail**

- [ ] **Step 3: Modify `BiometricJWTService`**

Find the method currently containing lines 236-237 (the `match` expression around `app(AppleAttestationVerifier::class)->verify(...)`). The method already takes a user (or is called with user context nearby). Inject `AccountFlagsService` via constructor and short-circuit before the verifier calls:

```php
public function verifyAttestationForUser(User $user, string $platform, string $attestation): bool
{
    if ($this->flags->hasReviewBypass($user, 'device_attestation')) {
        return true;
    }

    return match ($platform) {
        'ios'     => app(AppleAttestationVerifier::class)->verify($attestation, ''),
        'android' => app(GoogleIntegrityVerifier::class)->verify($attestation),
        default   => false,
    };
}
```

If `verifyAttestationForUser` doesn't exist verbatim — *it's the name the test expects* — extract the existing `match` block into a new method with that exact name, or rename appropriately. The existing call sites must be updated to pass `User`.

- [ ] **Step 4: Run, pass**

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Mobile/Services/BiometricJWTService.php \
        tests/Feature/AccountProvisioning/Bypasses/AttestationBypassTest.php
git commit -m "feat(provisioning): attestation bypass for review accounts

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 14: Rate limit bypass

**Files:**
- Modify: `app/Http/Middleware/ApiRateLimitMiddleware.php`
- Modify: `app/Http/Middleware/GraphQLRateLimitMiddleware.php`
- Test: `tests/Feature/AccountProvisioning/Bypasses/RateLimitBypassTest.php`

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\AccountProvisioning\Bypasses;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

it('rate-limits a regular user after threshold', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    // Hit an auth-bucket endpoint 6x; limit is 5/min.
    $responses = collect(range(1, 6))->map(fn () => $this->postJson('/api/v1/auth/login', []));

    expect($responses->last()->status())->toBe(429);
});

it('does not rate-limit a review user', function () {
    $user = User::factory()->create();
    AccountFlag::create([
        'user_id'            => $user->id,
        'is_review_account'  => true,
        'bypass_rate_limit'  => true,
    ]);
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $responses = collect(range(1, 20))->map(fn () => $this->postJson('/api/v1/auth/login', []));

    expect($responses->every(fn ($r) => $r->status() !== 429))->toBeTrue();
});
```

- [ ] **Step 2: Run, fail**

- [ ] **Step 3: Patch middleware**

In `app/Http/Middleware/ApiRateLimitMiddleware.php::handle()`, at the top of the method (after the existing "skip if disabled" guard), add:

```php
$user = $request->user();
if ($user !== null) {
    $flags = app(\App\Domain\AccountProvisioning\Services\AccountFlagsService::class);
    if ($flags->hasReviewBypass($user, 'rate_limit')) {
        return $next($request);
    }
}
```

Same pattern in `GraphQLRateLimitMiddleware::handle()`.

- [ ] **Step 4: Run, pass**

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/ApiRateLimitMiddleware.php \
        app/Http/Middleware/GraphQLRateLimitMiddleware.php \
        tests/Feature/AccountProvisioning/Bypasses/RateLimitBypassTest.php
git commit -m "feat(provisioning): rate-limit bypass for review accounts

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 15: Sanctions bypass

**Files:**
- Modify: `app/Domain/AgentProtocol/Workflows/Activities/PerformAmlScreeningActivity.php`
- Test: `tests/Feature/AccountProvisioning/Bypasses/SanctionsBypassTest.php`

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\AccountProvisioning\Bypasses;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Domain\AgentProtocol\Workflows\Activities\PerformAmlScreeningActivity;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

it('returns cleared when bypass flag is set', function () {
    $user = User::factory()->create();
    AccountFlag::create([
        'user_id'                    => $user->id,
        'is_review_account'          => true,
        'bypass_sanctions_screening' => true,
    ]);

    $activity = app(PerformAmlScreeningActivity::class);
    $result = $activity->screenUser($user);

    expect($result->status)->toBe('cleared');
    expect($result->source ?? null)->toBe('review_bypass');
});
```

- [ ] **Step 2: Run, fail**

- [ ] **Step 3: Patch activity**

At the entry of the screen method, before external calls:

```php
if ($this->flags->hasReviewBypass($user, 'sanctions_screening')) {
    return ScreeningResult::cleared(source: 'review_bypass');
}
```

Add `AccountFlagsService` dependency via constructor. Match the actual `ScreeningResult` value object's constructor (inspect the file for the real shape).

- [ ] **Step 4: Run, pass**

- [ ] **Step 5: Commit**

```bash
git add app/Domain/AgentProtocol/Workflows/Activities/PerformAmlScreeningActivity.php \
        tests/Feature/AccountProvisioning/Bypasses/SanctionsBypassTest.php
git commit -m "feat(provisioning): sanctions screening bypass for review accounts

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 16: SMS OTP bypass (investigation-first)

**Context:** the codebase has no obvious dedicated SMS OTP verification path (grep found only TOTP-via-Google-Authenticator in `TwoFactorAuthController`). The reviewer request says "Phone: skip / mark as verified without SMS". Two sub-tasks:

**16a — Decide the scope (5 min)**

- [ ] Grep for `phone_verified_at`, `phoneVerified`, `verify.*phone`, `SmsOtpService`, `sendOtp` across `app/`. Document findings in a 5-line comment on the PR.
- [ ] If there is no SMS OTP path (likely): the `bypass_sms_otp` flag still exists in the schema (reserved) but has no enforcement site yet. That's fine — flags without consumers are safe no-ops. The reviewer doesn't encounter an SMS OTP screen because Ondato KYC is the phone-verification gate, and KYC is handled by `kyc_override_level`. Note this in the operator runbook.

**16b — If an SMS OTP path exists**

- [ ] Add a failing feature test in the same pattern as Task 14/15.
- [ ] Short-circuit: `if ($flags->hasReviewBypass($user, 'sms_otp')) { return OtpResult::verified(source: 'review_bypass'); }`.
- [ ] Run, pass, commit.

If 16a finds no path, commit:

```bash
git commit --allow-empty -m "chore(provisioning): SMS OTP bypass reserved — no enforcement site found (noted in runbook)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 17: Notification suppression

**Files:**
- Create: `app/Domain/AccountProvisioning/Support/SuppressNotificationsListener.php` (or extend an existing dispatcher hook — investigate first)
- Test: `tests/Feature/AccountProvisioning/Bypasses/NotificationSuppressionTest.php`

**Investigation:** grep for the central notification dispatch pattern (`Notification::send`, `$user->notify(`, `NotificationSender`). Two implementations fit Laravel:

- **Option A (preferred):** use Laravel's `NotificationSending` event. Register a listener that cancels delivery by returning `false` from the handler when `AccountFlagsService::shouldSuppressNotifications($notifiable)` returns true.
- **Option B:** patch `PushNotificationService` and any email sender directly with the same check.

Option A is one file and one listener registration; Option B requires finding and patching every sender. Choose A unless something blocks it.

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\AccountProvisioning\Bypasses;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Models\User;
use App\Notifications\AlertAssigned;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

it('suppresses notifications for review accounts', function () {
    Notification::fake();
    $user = User::factory()->create();
    AccountFlag::create([
        'user_id'                 => $user->id,
        'is_review_account'       => true,
        'suppress_notifications'  => true,
    ]);

    // Use any real app notification; if AlertAssigned requires args, pick a simpler one (grep `class.*Notification` under app/Notifications) or define a minimal inline test notification class.
    $user->notify(new AlertAssigned(alertId: 1, assigneeEmail: $user->email));

    Notification::assertNothingSentTo($user);
});

it('delivers notifications to a regular user', function () {
    Notification::fake();
    $user = User::factory()->create();

    // Use any real app notification; if AlertAssigned requires args, pick a simpler one (grep `class.*Notification` under app/Notifications) or define a minimal inline test notification class.
    $user->notify(new AlertAssigned(alertId: 1, assigneeEmail: $user->email));

    Notification::assertSentTo($user, AlertAssigned::class);
});
```

- [ ] **Step 2: Run, fail**

- [ ] **Step 3: Implement listener**

```php
<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Support;

use App\Domain\AccountProvisioning\Services\AccountFlagsService;
use App\Models\User;
use Illuminate\Notifications\Events\NotificationSending;

class SuppressNotificationsListener
{
    public function __construct(private readonly AccountFlagsService $flags) {}

    public function handle(NotificationSending $event): bool
    {
        if (! $event->notifiable instanceof User) {
            return true;
        }

        return ! $this->flags->shouldSuppressNotifications($event->notifiable);
    }
}
```

Register in `app/Providers/EventServiceProvider.php::$listen`:

```php
\Illuminate\Notifications\Events\NotificationSending::class => [
    \App\Domain\AccountProvisioning\Support\SuppressNotificationsListener::class,
],
```

- [ ] **Step 4: Run, pass**

- [ ] **Step 5: Commit**

```bash
git add app/Domain/AccountProvisioning/Support \
        app/Providers/EventServiceProvider.php \
        tests/Feature/AccountProvisioning/Bypasses/NotificationSuppressionTest.php
git commit -m "feat(provisioning): suppress notifications for review accounts via NotificationSending hook

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Phase 4 — CLI surface

### Task 18: `account:provision-reviewer` command

**Files:**
- Create: `app/Console/Commands/AccountProvisionReviewerCommand.php`
- Test: `tests/Feature/AccountProvisioning/ProvisionReviewerCommandTest.php`

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\AccountProvisioning;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Models\User;
use App\Domain\User\Enums\UserRoles;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => UserRoles::ADMIN->value, 'guard_name' => 'web']);
    $this->operator = User::factory()->create(['email' => 'op@finaegis.com']);
    $this->operator->assignRole(UserRoles::ADMIN->value);
});

it('creates a reviewer user + flags + content on happy path', function () {
    $this->artisan('account:provision-reviewer', [
        '--email'          => 'appreview@finaegis.com',
        '--password'       => 'Strong-Pass-2026!',
        '--operator-email' => 'op@finaegis.com',
        '--expires-days'   => 60,
        '--note'           => 'App Store 2026-Q2',
    ])->assertExitCode(0);

    $user = User::where('email', 'appreview@finaegis.com')->first();
    expect($user)->not->toBeNull();
    expect($user->accountFlag->is_review_account)->toBeTrue();
    expect($user->accountFlag->bypass_rate_limit)->toBeTrue();
    expect($user->accountFlag->created_by)->toBe($this->operator->id);
});

it('is idempotent: re-running updates in place', function () {
    $args = [
        '--email'          => 'r@finaegis.com',
        '--password'       => 'Strong-Pass-2026!',
        '--operator-email' => 'op@finaegis.com',
    ];

    $this->artisan('account:provision-reviewer', $args);
    $this->artisan('account:provision-reviewer', array_merge($args, ['--password' => null]));

    expect(User::where('email', 'r@finaegis.com')->count())->toBe(1);
    expect(AccountFlag::where('user_id', User::where('email', 'r@finaegis.com')->first()->id)->count())->toBe(1);
});

it('aborts when operator is not an admin', function () {
    $nonAdmin = User::factory()->create(['email' => 'nobody@finaegis.com']);

    $this->artisan('account:provision-reviewer', [
        '--email'          => 'r@finaegis.com',
        '--operator-email' => 'nobody@finaegis.com',
    ])->assertExitCode(1);
});

it('aborts in production without --allow-production', function () {
    $this->app['env'] = 'production';

    $this->artisan('account:provision-reviewer', [
        '--email'          => 'r@finaegis.com',
        '--operator-email' => 'op@finaegis.com',
    ])->assertExitCode(1);
});

it('aborts when email collides with a non-review user and --force-convert is not set', function () {
    User::factory()->create(['email' => 'taken@finaegis.com']);

    $this->artisan('account:provision-reviewer', [
        '--email'          => 'taken@finaegis.com',
        '--operator-email' => 'op@finaegis.com',
    ])->assertExitCode(1);
});

it('rejects --force-convert in production entirely', function () {
    User::factory()->create(['email' => 'taken@finaegis.com']);
    $this->app['env'] = 'production';

    $this->artisan('account:provision-reviewer', [
        '--email'             => 'taken@finaegis.com',
        '--operator-email'    => 'op@finaegis.com',
        '--allow-production'  => true,
        '--force-convert'     => true,
    ])->assertExitCode(1);
});

it('enforces expiry hard cap of 90 days', function () {
    $this->artisan('account:provision-reviewer', [
        '--email'          => 'r@finaegis.com',
        '--password'       => 'Strong-Pass-2026!',
        '--operator-email' => 'op@finaegis.com',
        '--expires-days'   => 365,
    ])->assertExitCode(1);
});
```

- [ ] **Step 2: Run, fail**

- [ ] **Step 3: Implement command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\AccountProvisioning\Profiles\ReviewerAccountProfile;
use App\Domain\AccountProvisioning\Services\AccountProvisioningService;
use App\Domain\AccountProvisioning\ValueObjects\ProvisioningContext;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AccountProvisionReviewerCommand extends Command
{
    /** @var string */
    protected $signature = 'account:provision-reviewer
                            {--email= : Reviewer email}
                            {--password= : Password (generated if omitted)}
                            {--region=US : Reviewer region}
                            {--expires-days=60 : Days until auto-disable (0=none, hard cap 90)}
                            {--note= : Audit note}
                            {--operator-email= : Admin operator email (required)}
                            {--allow-production : Required when APP_ENV=production}
                            {--rotate-password : Rotate password only, skip reseed}
                            {--force-convert : Convert a non-review user (blocked in production)}
                            {--dry-run : Print intended changes, write nothing}';

    /** @var string */
    protected $description = 'Provision a pre-seeded reviewer/demo account for app-store review (operator-only)';

    public function handle(AccountProvisioningService $service, ReviewerAccountProfile $profile): int
    {
        if (app()->environment('production') && ! $this->option('allow-production')) {
            $this->error('Production guard: --allow-production is required when APP_ENV=production.');
            return 1;
        }

        if (app()->environment('production') && $this->option('force-convert')) {
            $this->error('--force-convert is blocked in production.');
            return 1;
        }

        $email = (string) $this->option('email');
        if ($email === '') {
            $this->error('--email is required');
            return 1;
        }

        $operatorEmail = (string) $this->option('operator-email');
        if ($operatorEmail === '') {
            $this->error('--operator-email is required');
            return 1;
        }

        $operator = User::where('email', $operatorEmail)->first();
        if ($operator === null || ! $operator->isAdmin()) {
            $this->error("Operator {$operatorEmail} not found or not an admin.");
            return 1;
        }

        $expiresDays = (int) $this->option('expires-days');
        if ($expiresDays < 0 || $expiresDays > 90) {
            $this->error('--expires-days must be 0..90 (hard cap).');
            return 1;
        }

        $password = $this->option('password');
        $rotate = (bool) $this->option('rotate-password');

        if (! is_string($password) || $password === '') {
            if ($rotate || User::where('email', $email)->doesntExist()) {
                $password = Str::password(20);
            } else {
                $password = null;
            }
        }

        $ctx = new ProvisioningContext(
            email: $email,
            name: 'App Reviewer',
            region: (string) $this->option('region'),
            expiresAt: $expiresDays > 0 ? CarbonImmutable::now()->addDays($expiresDays) : null,
            note: $this->option('note') ?: null,
            operatorId: (int) $operator->id,
            dryRun: (bool) $this->option('dry-run'),
        );

        try {
            $result = $service->apply(
                profile: $profile,
                ctx: $ctx,
                password: $password,
                rotatePassword: $rotate,
                forceConvert: (bool) $this->option('force-convert'),
            );
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return 1;
        }

        $this->line(json_encode([
            'email'      => $email,
            'password'   => $result['password_action'] === 'unchanged' ? 'unchanged' : $password,
            'user_id'    => $result['user']->id,
            'flags'      => $profile->flags($ctx),
            'expires_at' => $ctx->expiresAt?->toIso8601String(),
            'operator'   => $operator->email,
            'dry_run'    => $ctx->dryRun,
        ], JSON_PRETTY_PRINT));

        return 0;
    }
}
```

- [ ] **Step 4: Run test, verify pass**

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/AccountProvisionReviewerCommand.php \
        tests/Feature/AccountProvisioning/ProvisionReviewerCommandTest.php
git commit -m "feat(provisioning): account:provision-reviewer command with safety gates

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 19: `account:list-reviewers` command

**Files:**
- Create: `app/Console/Commands/AccountListReviewersCommand.php`
- Test: `tests/Feature/AccountProvisioning/ListReviewersCommandTest.php`

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\AccountProvisioning;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

it('lists review accounts with status and expiry', function () {
    $user = User::factory()->create(['email' => 'r@finaegis.com']);
    AccountFlag::create([
        'user_id'           => $user->id,
        'is_review_account' => true,
        'expires_at'        => now()->addDays(30),
        'note'              => 'Q2',
    ]);

    $this->artisan('account:list-reviewers')
         ->expectsOutputToContain('r@finaegis.com')
         ->expectsOutputToContain('Q2')
         ->assertExitCode(0);
});

it('outputs nothing when no review accounts exist', function () {
    $this->artisan('account:list-reviewers')->assertExitCode(0);
});
```

- [ ] **Step 2: Run, fail**

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use Illuminate\Console\Command;

class AccountListReviewersCommand extends Command
{
    protected $signature = 'account:list-reviewers {--json}';
    protected $description = 'List all review accounts with flag status + expiry';

    public function handle(): int
    {
        $rows = AccountFlag::where('is_review_account', true)
            ->with('user', 'createdBy')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($f) => [
                'email'       => $f->user?->email,
                'active'      => $f->isActive() ? 'yes' : 'no',
                'expires_at'  => $f->expires_at?->toDateString() ?? '—',
                'disabled_at' => $f->disabled_at?->toIso8601String() ?? '—',
                'operator'    => $f->createdBy?->email ?? '—',
                'note'        => $f->note ?? '—',
            ]);

        if ($this->option('json')) {
            $this->line($rows->toJson(JSON_PRETTY_PRINT));
            return 0;
        }

        if ($rows->isEmpty()) {
            $this->info('No review accounts found.');
            return 0;
        }

        $this->table(
            ['Email', 'Active', 'Expires', 'Disabled', 'Operator', 'Note'],
            $rows->toArray(),
        );
        return 0;
    }
}
```

- [ ] **Step 4: Run, pass**

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/AccountListReviewersCommand.php \
        tests/Feature/AccountProvisioning/ListReviewersCommandTest.php
git commit -m "feat(provisioning): account:list-reviewers command

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 20: `account:disable-reviewer` command

**Files:**
- Create: `app/Console/Commands/AccountDisableReviewerCommand.php`
- Test: `tests/Feature/AccountProvisioning/DisableReviewerCommandTest.php`

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\AccountProvisioning;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

it('disables one reviewer by email', function () {
    $user = User::factory()->create(['email' => 'r@finaegis.com']);
    AccountFlag::create([
        'user_id'                    => $user->id,
        'is_review_account'          => true,
        'bypass_rate_limit'          => true,
        'bypass_sanctions_screening' => true,
    ]);

    $this->artisan('account:disable-reviewer', ['--email' => 'r@finaegis.com'])->assertExitCode(0);

    $flag = $user->fresh()->accountFlag;
    expect($flag->bypass_rate_limit)->toBeFalse();
    expect($flag->bypass_sanctions_screening)->toBeFalse();
    expect($flag->disabled_at)->not->toBeNull();
    expect($flag->is_review_account)->toBeTrue();   // tag stays for audit
});

it('disables all expired reviewers with --all-expired', function () {
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    AccountFlag::create(['user_id' => $u1->id, 'is_review_account' => true, 'bypass_rate_limit' => true, 'expires_at' => now()->subDay()]);
    AccountFlag::create(['user_id' => $u2->id, 'is_review_account' => true, 'bypass_rate_limit' => true, 'expires_at' => now()->addDay()]);

    $this->artisan('account:disable-reviewer', ['--all-expired' => true])->assertExitCode(0);

    expect($u1->fresh()->accountFlag->bypass_rate_limit)->toBeFalse();
    expect($u2->fresh()->accountFlag->bypass_rate_limit)->toBeTrue();
});

it('re-enables with --re-enable', function () {
    $user = User::factory()->create(['email' => 'r@finaegis.com']);
    AccountFlag::create(['user_id' => $user->id, 'is_review_account' => true, 'disabled_at' => now()]);

    $this->artisan('account:disable-reviewer', ['--email' => 'r@finaegis.com', '--re-enable' => true])->assertExitCode(0);

    $flag = $user->fresh()->accountFlag;
    expect($flag->disabled_at)->toBeNull();
    expect($flag->bypass_rate_limit)->toBeTrue();
});
```

- [ ] **Step 2: Run, fail**

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Domain\AccountProvisioning\Profiles\ReviewerAccountProfile;
use App\Domain\AccountProvisioning\Services\AccountProvisioningService;
use App\Domain\AccountProvisioning\ValueObjects\ProvisioningContext;
use App\Models\User;
use Illuminate\Console\Command;

class AccountDisableReviewerCommand extends Command
{
    protected $signature = 'account:disable-reviewer
                            {--email=}
                            {--all-expired}
                            {--re-enable}';
    protected $description = 'Disable (revoke bypasses) or re-enable a reviewer account';

    public function handle(AccountProvisioningService $service, ReviewerAccountProfile $profile): int
    {
        $reEnable = (bool) $this->option('re-enable');

        if ($this->option('all-expired')) {
            $flags = AccountFlag::where('is_review_account', true)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->whereNull('disabled_at')
                ->with('user')
                ->get();

            foreach ($flags as $flag) {
                if ($flag->user) {
                    $service->disable($flag->user);
                    $this->line("disabled: {$flag->user->email}");
                }
            }
            return 0;
        }

        $email = (string) $this->option('email');
        if ($email === '') {
            $this->error('--email or --all-expired is required');
            return 1;
        }

        $user = User::where('email', $email)->first();
        if ($user === null || $user->accountFlag === null || ! $user->accountFlag->is_review_account) {
            $this->error("User {$email} is not a review account.");
            return 1;
        }

        if ($reEnable) {
            $ctx = new ProvisioningContext($email, 'App Reviewer', 'US', null, $user->accountFlag->note, (int) ($user->accountFlag->created_by ?? 0));
            $service->reEnable($user, $profile, $ctx);
            $this->info("re-enabled: {$email}");
            return 0;
        }

        $service->disable($user);
        $this->info("disabled: {$email}");
        return 0;
    }
}
```

- [ ] **Step 4: Run, pass**

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/AccountDisableReviewerCommand.php \
        tests/Feature/AccountProvisioning/DisableReviewerCommandTest.php
git commit -m "feat(provisioning): account:disable-reviewer with --all-expired + --re-enable

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 21: `account:purge-reviewer` command

**Files:**
- Create: `app/Console/Commands/AccountPurgeReviewerCommand.php`
- Test: `tests/Feature/AccountProvisioning/PurgeReviewerCommandTest.php`

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\AccountProvisioning;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

it('soft-deletes a review account on --confirm', function () {
    $user = User::factory()->create(['email' => 'r@finaegis.com']);
    AccountFlag::create(['user_id' => $user->id, 'is_review_account' => true]);

    $this->artisan('account:purge-reviewer', ['--email' => 'r@finaegis.com', '--confirm' => true])->assertExitCode(0);

    expect(User::withTrashed()->find($user->id)?->trashed())->toBeTrue();
});

it('refuses to purge a non-review user', function () {
    User::factory()->create(['email' => 'u@finaegis.com']);

    $this->artisan('account:purge-reviewer', ['--email' => 'u@finaegis.com', '--confirm' => true])->assertExitCode(1);
});

it('refuses in production without --allow-production', function () {
    $this->app['env'] = 'production';
    $user = User::factory()->create(['email' => 'r@finaegis.com']);
    AccountFlag::create(['user_id' => $user->id, 'is_review_account' => true]);

    $this->artisan('account:purge-reviewer', ['--email' => 'r@finaegis.com', '--confirm' => true])->assertExitCode(1);
});
```

- [ ] **Step 2: Run, fail**

- [ ] **Step 3: Implement**

Note: this assumes `User` uses `SoftDeletes`. If not, add the trait + migration in this task. Verify via `grep SoftDeletes app/Models/User.php`. If absent, either add (invasive) or change the purge semantics to "disable + anonymize email" instead (less invasive, retains audit). Decide based on what the existing user deletion path in the codebase does.

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;

class AccountPurgeReviewerCommand extends Command
{
    protected $signature = 'account:purge-reviewer {--email=} {--confirm} {--allow-production}';
    protected $description = 'Soft-delete a review account (blocked in production without --allow-production)';

    public function handle(): int
    {
        if (! $this->option('confirm')) {
            $this->error('--confirm is required.');
            return 1;
        }

        if (app()->environment('production') && ! $this->option('allow-production')) {
            $this->error('Production guard: --allow-production is required.');
            return 1;
        }

        $email = (string) $this->option('email');
        $user = User::where('email', $email)->first();

        if ($user === null || $user->accountFlag === null || ! $user->accountFlag->is_review_account) {
            $this->error("User {$email} is not a review account.");
            return 1;
        }

        $user->tokens()->delete();
        $user->delete(); // requires SoftDeletes

        Event::dispatch(new \App\Domain\AccountProvisioning\Events\AccountPurged(userId: $user->id, operatorId: null));

        $this->info("purged: {$email}");
        return 0;
    }
}
```

Add event class `app/Domain/AccountProvisioning/Events/AccountPurged.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Events;

final readonly class AccountPurged
{
    public function __construct(
        public int $userId,
        public ?int $operatorId,
    ) {}
}
```

- [ ] **Step 4: Run, pass**

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/AccountPurgeReviewerCommand.php \
        app/Domain/AccountProvisioning/Events/AccountPurged.php \
        tests/Feature/AccountProvisioning/PurgeReviewerCommandTest.php
git commit -m "feat(provisioning): account:purge-reviewer with production guard + audit event

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 22: Schedule daily `--all-expired` sweep

**Files:**
- Modify: `routes/console.php`
- Test: `tests/Feature/AccountProvisioning/ExpirySweepTest.php`

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\AccountProvisioning;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

uses(TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

it('registers account:disable-reviewer --all-expired on the daily schedule', function () {
    $schedule = app(Schedule::class);
    $events = collect($schedule->events());

    $match = $events->first(fn ($e) => str_contains($e->command ?? '', 'account:disable-reviewer --all-expired'));
    expect($match)->not->toBeNull();
    expect($match->expression)->toBe('10 0 * * *');    // daily at 00:10 UTC
});
```

- [ ] **Step 2: Run, fail**

- [ ] **Step 3: Register in `routes/console.php`**

Append:

```php
// Daily sweep: auto-disable expired reviewer/demo accounts.
Schedule::command('account:disable-reviewer --all-expired')
    ->dailyAt('00:10')
    ->description('Auto-disable expired review accounts')
    ->appendOutputTo(storage_path('logs/account-expiry-sweep.log'));
```

- [ ] **Step 4: Run, pass**

- [ ] **Step 5: Commit**

```bash
git add routes/console.php tests/Feature/AccountProvisioning/ExpirySweepTest.php
git commit -m "feat(provisioning): schedule daily expiry sweep

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Phase 5 — Docs, release, final checks

### Task 23: Operator runbook

**Files:**
- Create: `docs/operations/reviewer-accounts.md`

- [ ] **Step 1: Write the runbook**

Content sections:
1. **When to use** — app-store review cycles, partner demo access, internal QA personas.
2. **Pre-flight** — confirm operator is admin; verify staging rehearsal worked.
3. **Provision** — full command example, JSON output, 1Password hand-off procedure.
4. **List / audit** — `account:list-reviewers`, how to read the output.
5. **Disable / re-enable** — when the review cycle ends; when rework is needed.
6. **Purge** — production guard, when to use (reviewer account leaked), alternative (GDPR-erasure flow for full PII removal).
7. **Expiry sweep** — daily job, expected log location, how to verify it ran.
8. **Incident response** — `bypass.fired` volume spike investigation steps: who's the user, what bypass, how to force-disable in under 60s (`account:disable-reviewer --email=X`).
9. **Limitations** — SMS OTP bypass reserved; no Filament UI; CLI-only; single-tenant v1.

- [ ] **Step 2: Commit**

```bash
git add docs/operations/reviewer-accounts.md
git commit -m "docs(ops): reviewer accounts operator runbook

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 24: Security doc

**Files:**
- Create: `docs/security/account-flags.md`

- [ ] **Step 1: Write the doc**

Content:
1. **Threat model** — what this opens up, what it does not.
2. **Scope of bypasses** — the table from Section 4 of the spec.
3. **Audit guarantees** — `bypass.fired` log line schema, `account_flags` table as source of truth, `created_by` operator attribution.
4. **Expiry and reversibility** — default 60d, hard cap 90d, daily sweep, manual disable.
5. **What this is NOT** — not admin impersonation, not a permission bypass, not usable from the web UI.
6. **Defence-in-depth** — `disabled_at` short-circuits bypasses even if column values persist; per-request cache invalidation on updates.
7. **Monitoring** — metric names, Prometheus alert suggestions.
8. **Revocation runbook ref** — link to `docs/operations/reviewer-accounts.md`.

- [ ] **Step 2: Commit**

```bash
git add docs/security/account-flags.md
git commit -m "docs(security): account flags security model

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 25: `CLAUDE.md` updates

**Files:**
- Modify: `CLAUDE.md`

- [ ] **Step 1: Three edits**

a. Essential Commands — add under the existing user/admin block:

```bash
# Reviewer / demo accounts (operator-only)
php artisan account:provision-reviewer --email=X --operator-email=admin@...
php artisan account:list-reviewers
php artisan account:disable-reviewer --email=X     # or --all-expired
```

b. CI/CD table — add one row:

| Bypass flags missing test | Every new bypass needs a matching feature test in `tests/Feature/AccountProvisioning/Bypasses/` asserting both sides (flag set = allow, flag unset = enforce). |

c. Architecture section — bump "56 domains" → "57 domains" (add `AccountProvisioning` to the domain list mentally; if your CLAUDE.md lists specific domain names elsewhere, update those references too).

- [ ] **Step 2: Commit**

```bash
git add CLAUDE.md
git commit -m "docs(claude): add AccountProvisioning domain + command reference

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 26: Serena memory + auto-memory reference

**Files:**
- Modify (via Serena): `account_provisioning_domain` memory
- Create: `.claude/projects/-home-yozaz-www-finaegis-core-banking-prototype-laravel/memory/reference_reviewer_accounts.md` (or use the appropriate platform-specific memory path — check existing memory files)

- [ ] **Step 1: Write the Serena memory** (via `mcp__serena__write_memory`)

Content: flag model, profile interface, enforcement points, safety gates, command inventory. This is architectural context future sessions will need.

- [ ] **Step 2: Write the auto-memory reference**

```markdown
---
name: reviewer accounts runbook
description: Pointer to the operator runbook for reviewer/demo accounts
type: reference
---

Operator runbook: `docs/operations/reviewer-accounts.md`. Security model: `docs/security/account-flags.md`. CLI: `account:provision-reviewer`, `account:list-reviewers`, `account:disable-reviewer`, `account:purge-reviewer`. Flag table: `account_flags`. Architectural memory in Serena: `account_provisioning_domain`.
```

Add one line to `MEMORY.md`: `- [Reviewer accounts](reference_reviewer_accounts.md) — operator runbook pointer`

- [ ] **Step 3: Commit** (auto-memory file only; Serena memories persist outside git)

```bash
# nothing to commit if memory dir is gitignored; confirm
```

---

### Task 27: `VERSION_ROADMAP.md` entry

**Files:**
- Modify: `docs/VERSION_ROADMAP.md`

- [ ] **Step 1: Add patch-version entry**

Under the latest version header (currently v7.10.10 per memory), add:

```markdown
### v7.10.11 — Reviewer account provisioning (2026-04-xx)

- New `AccountProvisioning` domain with `account_flags` table, `AccountProfile` interface, and `ReviewerAccountProfile`.
- Operator-only CLI: `account:provision-reviewer`, `account:list-reviewers`, `account:disable-reviewer`, `account:purge-reviewer`.
- Scoped security bypasses (device attestation, rate limits, sanctions, notifications, KYC override) with audit logging and daily expiry sweep.
- Docs: `docs/operations/reviewer-accounts.md`, `docs/security/account-flags.md`.
```

- [ ] **Step 2: Commit**

```bash
git add docs/VERSION_ROADMAP.md
git commit -m "docs(roadmap): v7.10.11 — reviewer account provisioning

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

### Task 28: Final verification + PR

- [ ] **Step 1: Full code-quality sweep**

```bash
./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php
XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G
./vendor/bin/pest --parallel --stop-on-failure
```

All three must be green. Fix anything not.

- [ ] **Step 2: Run `post-phase-review` skill**

Per global CLAUDE.md: before opening a feature PR, run the `post-phase-review` skill. Address any critical/important issues it raises.

- [ ] **Step 3: Push branch and open PR**

```bash
git push -u origin feat/account-provisioning-reviewer
gh pr create --title "feat: reviewer/demo account provisioning (account_flags + CLI)" --body "$(cat <<'EOF'
## Summary

- New `AccountProvisioning` domain with `account_flags` table and `AccountProfile` interface.
- Four operator-only CLI commands: `provision-reviewer`, `list-reviewers`, `disable-reviewer`, `purge-reviewer`.
- Scoped, auditable security bypasses at 5 existing enforcement points (device attestation, rate limiting, sanctions, notifications, KYC override).
- Daily scheduled expiry sweep; 60-day default / 90-day hard-cap.
- Operator runbook + security doc; Serena + auto-memory references.

## Spec + plan

- Spec: `docs/superpowers/specs/2026-04-24-reviewer-account-provisioning-design.md`
- Plan: `docs/superpowers/plans/2026-04-24-reviewer-account-provisioning.md`

## Test plan

- [ ] `./vendor/bin/pest --parallel --stop-on-failure` — green
- [ ] `XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G` — green (Level 8)
- [ ] `./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --dry-run --diff` — no changes
- [ ] Staging rehearsal: run `account:provision-reviewer --email=rehearsal@...` end-to-end; verify JSON output, flag row, seeded content
- [ ] Verify `bypass.fired` log line appears under each bypass feature test

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

- [ ] **Step 4: Merge after green CI + review**

After approval and green checks, merge to `main`. Do not push force, do not rebase onto a moved `main` without reverifying tests locally.

- [ ] **Step 5: Production run (separate change — not part of this PR)**

After merge, run in staging first, then production with `--allow-production`. Hand credentials to mobile team via 1Password. This step is operational, not code.

---

## Notes for the engineer

- **File paths in the spec assume directory names may drift**: if `app/Domain/CardIssuance/Models/Card.php` doesn't exist as written, grep for `class Card` in `app/Domain/` and use the real path. Same for Rewards and TrustCert models.
- **Balance mutation is the most sensitive step**: never use `(float)` for money (CLAUDE.md), always wrap in `DB::transaction()` with `lockForUpdate()` if reading current balance. If in doubt, route through the existing ledger domain.
- **PHPStan Level 8**: the codebase is strict. Follow the patterns in CLAUDE.md (cast `(string) json_encode(...)`, `@var` PHPDoc for Mockery mocks, etc.).
- **Sanctum test scope**: in all feature tests that act as a user, pass abilities: `Sanctum::actingAs($user, ['read', 'write', 'delete'])`.
- **Parallel agents beware**: if dispatching this via subagent-driven development, do not parallelize Tasks 2, 3, 12, 18 — all touch `AppServiceProvider.php` or `User.php`. Serialize those.
- **`NOTE:` blocks in the plan marked "inspect the real model"**: the plan is TDD — the test defines the contract; when the real model has different column names, adapt implementation to pass the test and adjust test assertions if the test's expectation was wrong.
