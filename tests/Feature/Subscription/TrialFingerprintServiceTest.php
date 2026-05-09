<?php

declare(strict_types=1);

use App\Domain\Subscription\Models\TrialCardFingerprint;
use App\Domain\Subscription\Services\TrialFingerprintService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    config(['services.stripe.trial_fingerprint_pepper' => 'test_pepper_32_bytes_long_!!!!!!!!!']);
});

it('hashes a fingerprint deterministically with the configured pepper', function () {
    $service = app(TrialFingerprintService::class);

    $hash1 = $service->hash('fp_card_abc123');
    $hash2 = $service->hash('fp_card_abc123');

    expect($hash1)->toBe($hash2);
    expect(strlen($hash1))->toBe(64); // sha256 hex
});

it('returns null for a fingerprint not seen before (eligible)', function () {
    $service = app(TrialFingerprintService::class);

    expect($service->eligibleAfter(str_repeat('a', 64)))->toBeNull();
});

it('blocks a fingerprint within the 12-month retry window', function () {
    $service = app(TrialFingerprintService::class);
    $user = User::factory()->create();
    $hash = $service->hash('fp_recent_user');

    $service->recordClaim($hash, $user, 'pm_test_001');

    $eligibleAfter = $service->eligibleAfter($hash);

    expect($eligibleAfter)->not()->toBeNull();
    if ($eligibleAfter !== null) {
        expect($eligibleAfter->isFuture())->toBeTrue();
        // Approximately 12 months from now.
        expect((int) $eligibleAfter->diffInDays(now(), absolute: true))->toBeGreaterThanOrEqual(364);
    }

    expect($service->isEligible($hash, $user->id))->toBeFalse();
});

it('returns null again once the 12-month window has passed', function () {
    $service = app(TrialFingerprintService::class);
    $user = User::factory()->create();
    $hash = $service->hash('fp_old_user');

    $service->recordClaim($hash, $user, 'pm_test_002');

    // Push last_used_at into the past beyond 12 months.
    TrialCardFingerprint::query()->where('fingerprint_hash', $hash)->update([
        'first_used_at' => now()->subMonths(15),
        'last_used_at'  => now()->subMonths(15),
    ]);

    expect($service->eligibleAfter($hash))->toBeNull();
    expect($service->isEligible($hash, $user->id))->toBeTrue();
});

it('throws when TRIAL_FINGERPRINT_PEPPER is not configured', function () {
    config(['services.stripe.trial_fingerprint_pepper' => '']);
    $service = app(TrialFingerprintService::class);

    $service->hash('fp_test');
})->throws(RuntimeException::class);
