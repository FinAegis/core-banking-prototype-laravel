<?php

declare(strict_types=1);

namespace Tests\Unit\AccountProvisioning;

use App\Domain\AccountProvisioning\Contracts\AccountProfile;
use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Domain\AccountProvisioning\Services\AccountProvisioningService;
use App\Domain\AccountProvisioning\ValueObjects\ProvisioningContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('does not add a wrapping DB::transaction around apply()', function () {
    // Regression guard for the multi-connection self-deadlock fixed on
    // 2026-04-26: when AccountProvisioningService wrapped the whole flow in
    // DB::transaction(), the just-created users.id row stayed exclusively
    // locked on the default connection while CardSeeder's tenant-connection
    // INSERT into cardholders waited for an FK shared lock on the same row.
    // That deadlocks with itself and only resolves at innodb_lock_wait_timeout.
    //
    // Atomicity for this flow is provided by per-seeder idempotency, not a
    // wrapping transaction — see AccountProvisioningService::apply() docblock.
    //
    // Note: RefreshDatabase wraps each test in a transaction (level 1), so we
    // assert delta from baseline rather than absolute zero. If apply() ever
    // re-introduces a wrapping transaction, the observed level inside the
    // profile's provision() callback will be (baseline + 1), not baseline.
    $baseline = DB::transactionLevel();

    $profile = new class () implements AccountProfile {
        public ?int $observedTransactionLevel = null;

        public function name(): string
        {
            return 'test-profile';
        }

        /** @return array<string, bool|int|string|\Carbon\CarbonImmutable|null> */
        public function flags(ProvisioningContext $ctx): array
        {
            return ['is_review_account' => true];
        }

        public function provision(User $user, ProvisioningContext $ctx): void
        {
            $this->observedTransactionLevel = DB::transactionLevel();
        }
    };

    $ctx = new ProvisioningContext(
        email: 'reg-deadlock@example.invalid',
        name: 'Regression Test',
        region: 'US',
        expiresAt: null,
        note: null,
        operatorId: 1,
    );

    app(AccountProvisioningService::class)->apply(
        profile: $profile,
        ctx: $ctx,
        password: 'Strong-Pass-2026!',
        rotatePassword: false,
        forceConvert: false,
    );

    expect($profile->observedTransactionLevel)->toBe($baseline);
});

it('still upserts the AccountFlag row even without a wrapping transaction', function () {
    // Smoke test: the no-transaction refactor must not break the basic
    // contract that apply() persists the AccountFlag with the profile's flag
    // payload before returning.
    $profile = new class () implements AccountProfile {
        public function name(): string
        {
            return 'test-profile';
        }

        /** @return array<string, bool|int|string|\Carbon\CarbonImmutable|null> */
        public function flags(ProvisioningContext $ctx): array
        {
            return [
                'is_review_account' => true,
                'bypass_rate_limit' => true,
                'note'              => 'regression test',
            ];
        }

        public function provision(User $user, ProvisioningContext $ctx): void
        {
            // no-op
        }
    };

    $ctx = new ProvisioningContext(
        email: 'reg-flag@example.invalid',
        name: 'Flag Test',
        region: 'US',
        expiresAt: null,
        note: null,
        operatorId: 1,
    );

    $result = app(AccountProvisioningService::class)->apply(
        profile: $profile,
        ctx: $ctx,
        password: 'Strong-Pass-2026!',
        rotatePassword: false,
        forceConvert: false,
    );

    expect($result['password_action'])->toBe('created');
    $flag = AccountFlag::where('user_id', $result['user']->id)->first();
    expect($flag)->not->toBeNull();
    expect($flag->is_review_account)->toBeTrue();
    expect($flag->bypass_rate_limit)->toBeTrue();
    expect($flag->note)->toBe('regression test');
});
