<?php

declare(strict_types=1);

namespace Tests\Unit\AccountProvisioning;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\AccountProvisioning\Profiles\ReviewerAccountProfile;
use App\Domain\AccountProvisioning\ValueObjects\ProvisioningContext;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\Privacy\Models\ShieldedBalance;
use App\Domain\Rewards\Models\RewardProfile;
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
    expect($flags['created_by'])->toBe(1);
});

it('provisions all content sub-seeders in one transaction', function () {
    $user = User::factory()->create();
    $ctx = new ProvisioningContext('e@e.com', 'n', 'US', null, null, 1);

    app(ReviewerAccountProfile::class)->provision($user, $ctx);

    // Verify sub-seeders ran: wallets, balances (shielded), card, trust cert, rewards.
    expect(BlockchainAddress::where('user_uuid', $user->uuid)->count())->toBe(2);
    expect(ShieldedBalance::where('user_id', $user->id)->count())->toBe(1);
    expect(Card::where('user_id', $user->id)->where('issuer', 'review_bypass')->count())->toBe(1);
    expect(Certificate::where('user_id', $user->id)->where('credential_type', 'review_bypass')->count())->toBe(1);
    expect(RewardProfile::where('user_id', $user->id)->count())->toBe(1);

    // Profile does NOT write the flag row — that's the orchestrator's job.
    $fresh = User::findOrFail($user->id);
    expect($fresh->accountFlag)->toBeNull();
});

it('is idempotent end-to-end', function () {
    $user = User::factory()->create();
    $ctx = new ProvisioningContext('e@e.com', 'n', 'US', null, null, 1);

    $profile = app(ReviewerAccountProfile::class);
    $profile->provision($user, $ctx);
    $profile->provision($user, $ctx); // second call must not throw or duplicate

    expect(BlockchainAddress::where('user_uuid', $user->uuid)->count())->toBe(2);
    expect(Card::where('user_id', $user->id)->where('issuer', 'review_bypass')->count())->toBe(1);
    expect(Certificate::where('user_id', $user->id)->where('credential_type', 'review_bypass')->count())->toBe(1);
    expect(RewardProfile::where('user_id', $user->id)->count())->toBe(1);
});

it('exposes the correct profile slug', function () {
    $profile = app(ReviewerAccountProfile::class);

    expect($profile->name())->toBe('reviewer');
});
