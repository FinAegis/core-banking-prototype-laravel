<?php

declare(strict_types=1);

use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Account\Workflows\SingleBatchOperationActivity;

it('archives transactions older than seven years and leaves newer ones', function (): void {
    $old = TransactionProjection::factory()->create(['created_at' => now()->subYears(8)]);
    $recent = TransactionProjection::factory()->create(['created_at' => now()->subYears(2)]);

    $activity = (new ReflectionClass(SingleBatchOperationActivity::class))->newInstanceWithoutConstructor();
    $result = $activity->execute('archive_old_transactions', 'batch-archive-test');

    expect($result['result']['transactions_archived'] ?? $result['transactions_archived'] ?? null)->toBe(1)
        ->and((bool) $old->fresh()?->getAttribute('archived'))->toBeTrue()
        ->and((bool) $recent->fresh()?->getAttribute('archived'))->toBeFalse();
});
