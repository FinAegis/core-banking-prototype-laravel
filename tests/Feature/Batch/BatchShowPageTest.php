<?php

/**
 * The batch detail page worked out the average item time with MySQL's
 * TIMESTAMPDIFF, so it failed on SQLite (the test database). It now works
 * the average out from the loaded items, on every database.
 */

declare(strict_types=1);

use App\Domain\Batch\Models\BatchJob;
use App\Domain\Batch\Models\BatchJobItem;
use App\Models\User;
use Illuminate\Support\Str;

it('shows the average time of the processed items', function (): void {
    config(['ledger.batch_processing_enabled' => true]);
    $owner = User::factory()->create();
    $batch = BatchJob::query()->create([
        'uuid'            => (string) Str::uuid(),
        'user_uuid'       => $owner->uuid,
        'name'            => 'Payroll',
        'type'            => 'transfer',
        'status'          => 'completed',
        'total_items'     => 3,
        'processed_items' => 2,
        'failed_items'    => 0,
        'scheduled_at'    => now()->subMinutes(10),
    ]);
    $created = now()->subMinutes(10);
    foreach ([[1, 4], [2, 8], [3, null]] as [$sequence, $seconds]) {
        $item = BatchJobItem::query()->create([
            'batch_job_uuid' => $batch->uuid,
            'sequence'       => $sequence,
            'status'         => $seconds === null ? 'pending' : 'completed',
            'data'           => [],
            'processed_at'   => $seconds === null ? null : $created->copy()->addSeconds($seconds),
        ]);
        $item->forceFill(['created_at' => $created])->save();
    }

    $this->actingAs($owner)
        ->get(route('batch-processing.show', $batch))
        ->assertOk()
        ->assertViewHas('stats', fn (array $stats): bool => (float) $stats['avg_processing_time'] === 6.0)
        ->assertSee('6.0s');
});
