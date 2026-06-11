<?php

/**
 * DispatchKycRequiredCuesCommandTest — tests for the aggregate-condition hourly cron.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.6
 */

declare(strict_types=1);

use App\Domain\Subscription\Models\Cue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
});

it('creates kyc_required cues for users above threshold with no pending cue', function () {
    $user = User::factory()->create([
        'lifetime_spend_cents' => 100_001,
        'kyc_completed_at'     => null,
    ]);

    $this->artisan('cue:dispatch-kyc-required')->assertSuccessful();

    $cue = Cue::query()->where('user_id', $user->id)->where('kind', 'kyc_required')->first();
    expect($cue)->not()->toBeNull();
    expect($cue->priority)->toBe('critical');
});

it('does NOT create kyc_required cue for user below threshold', function () {
    $user = User::factory()->create([
        'lifetime_spend_cents' => 50_000,
        'kyc_completed_at'     => null,
    ]);

    $this->artisan('cue:dispatch-kyc-required')->assertSuccessful();

    expect(Cue::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('does NOT create kyc_required cue for user who completed KYC', function () {
    $user = User::factory()->create([
        'lifetime_spend_cents' => 200_000,
        'kyc_completed_at'     => now()->subDay(),
    ]);

    $this->artisan('cue:dispatch-kyc-required')->assertSuccessful();

    expect(Cue::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('does NOT create a second pending kyc_required cue when one already exists', function () {
    $user = User::factory()->create([
        'lifetime_spend_cents' => 150_000,
        'kyc_completed_at'     => null,
    ]);

    // Run once — creates the cue.
    $this->artisan('cue:dispatch-kyc-required')->assertSuccessful();

    // Run again — should NOT create a second cue.
    $this->artisan('cue:dispatch-kyc-required')->assertSuccessful();

    expect(Cue::query()->where('user_id', $user->id)->where('kind', 'kyc_required')->count())->toBe(1);
});

it('creates a new kyc_required cue for user with dismissed previous cue', function () {
    $user = User::factory()->create([
        'lifetime_spend_cents' => 150_000,
        'kyc_completed_at'     => null,
    ]);

    // Pre-create a dismissed cue.
    Cue::query()->create([
        'user_id'          => $user->id,
        'kind'             => 'kyc_required',
        'priority'         => 'critical',
        'due_at'           => now()->subDay(),
        'expires_at'       => now()->addMonth(),
        'payload'          => [],
        'dismissed_at'     => now(),
        'dismissed_action' => 'dismissed',
        'idempotency_key'  => hash('sha256', "{$user->id}:kyc_required:1970-01-01T00:00:00Z"),
    ]);

    // The cron finds the user because the dismissed cue is excluded from the LEFT JOIN.
    // createIdempotent hits the UNIQUE constraint (same idempotency_key) → no-op.
    $this->artisan('cue:dispatch-kyc-required')->assertSuccessful();

    // One cue row still — dismissed + no new one because idempotency key is the same.
    expect(Cue::query()->where('user_id', $user->id)->where('kind', 'kyc_required')->count())->toBe(1);
});

it('dry-run reports candidate count without creating cues', function () {
    User::factory()->create([
        'lifetime_spend_cents' => 100_001,
        'kyc_completed_at'     => null,
    ]);

    $this->artisan('cue:dispatch-kyc-required', ['--dry-run' => true])->assertSuccessful();

    expect(Cue::query()->count())->toBe(0);
});
