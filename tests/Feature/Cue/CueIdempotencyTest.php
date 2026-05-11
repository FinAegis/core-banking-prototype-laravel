<?php

/**
 * CueIdempotencyTest — same trigger fired twice produces one cue (uniq_cues_idempotency).
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.11
 */

declare(strict_types=1);

use App\Domain\Subscription\Models\Cue;
use App\Domain\Subscription\Services\CueRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses(Tests\TestCase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
});

it('createIdempotent creates only one cue for identical inputs', function () {
    /** @var CueRepository $repo */
    $repo = app(CueRepository::class);
    $user = User::factory()->create();

    $window = '2026-01-01T00:00:00Z';

    $cue1 = $repo->createIdempotent($user, 'payment_failed', ['invoiceId' => 'in_1'], $window);
    $cue2 = $repo->createIdempotent($user, 'payment_failed', ['invoiceId' => 'in_1'], $window);

    // Same id — second call returned the existing row.
    expect($cue1->id)->toBe($cue2->id);

    // Only one row in the DB.
    expect(Cue::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('createIdempotent creates separate cues for different occurrence windows', function () {
    /** @var CueRepository $repo */
    $repo = app(CueRepository::class);
    $user = User::factory()->create();

    $cue1 = $repo->createIdempotent($user, 'payment_failed', [], '2026-01-01T00:00:00Z');
    $cue2 = $repo->createIdempotent($user, 'payment_failed', [], '2026-02-01T00:00:00Z');

    expect($cue1->id)->not()->toBe($cue2->id);
    expect(Cue::query()->where('user_id', $user->id)->count())->toBe(2);
});

it('createIdempotent creates separate cues for different kinds', function () {
    /** @var CueRepository $repo */
    $repo = app(CueRepository::class);
    $user = User::factory()->create();

    $window = '2026-01-01T00:00:00Z';

    $cue1 = $repo->createIdempotent($user, 'payment_failed', [], $window);
    $cue2 = $repo->createIdempotent($user, 'refund_processed', [], $window);

    expect($cue1->id)->not()->toBe($cue2->id);
    expect(Cue::query()->where('user_id', $user->id)->count())->toBe(2);
});

it('createIdempotent creates separate cues for different users with same window', function () {
    /** @var CueRepository $repo */
    $repo = app(CueRepository::class);
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $window = '2026-01-01T00:00:00Z';

    $cue1 = $repo->createIdempotent($user1, 'kyc_required', [], $window);
    $cue2 = $repo->createIdempotent($user2, 'kyc_required', [], $window);

    expect($cue1->id)->not()->toBe($cue2->id);
});
