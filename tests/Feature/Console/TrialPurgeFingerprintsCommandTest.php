<?php

declare(strict_types=1);

use App\Domain\Subscription\Models\TrialCardFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * @param array<string, mixed> $overrides
 */
function makeTrialFingerprintRow(Carbon $lastUsedAt, array $overrides = []): TrialCardFingerprint
{
    /** @var TrialCardFingerprint $row */
    $row = TrialCardFingerprint::query()->create(array_replace([
        'fingerprint_hash'         => str_repeat('a', 60) . substr(uniqid('', true), -4),
        'first_user_id'            => 1,
        'last_user_id'             => 1,
        'first_used_at'            => $lastUsedAt,
        'last_used_at'             => $lastUsedAt,
        'trial_user_count'         => 1,
        'stripe_payment_method_id' => 'pm_test',
    ], $overrides));

    return $row;
}

it('purges rows older than 12 months', function (): void {
    makeTrialFingerprintRow(Carbon::now()->subMonths(13));
    makeTrialFingerprintRow(Carbon::now()->subMonths(15));

    expect(TrialCardFingerprint::query()->count())->toBe(2);

    $exit = Artisan::call('trial:purge-fingerprints');

    expect($exit)->toBe(0)
        ->and(TrialCardFingerprint::query()->count())->toBe(0);
});

it('preserves rows within the 12-month window', function (): void {
    makeTrialFingerprintRow(Carbon::now()->subMonths(6));
    makeTrialFingerprintRow(Carbon::now()->subMonths(11));

    $exit = Artisan::call('trial:purge-fingerprints');

    expect($exit)->toBe(0)
        ->and(TrialCardFingerprint::query()->count())->toBe(2);
});

it('--dry-run prints the count without deleting', function (): void {
    makeTrialFingerprintRow(Carbon::now()->subMonths(13));

    $exit = Artisan::call('trial:purge-fingerprints', ['--dry-run' => true]);

    expect($exit)->toBe(0)
        ->and(TrialCardFingerprint::query()->count())->toBe(1);
});
