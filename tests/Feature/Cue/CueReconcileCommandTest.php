<?php

/**
 * CueReconcileCommandTest — tests for cue:reconcile recovery command.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §15
 */

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses(Tests\TestCase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
});

it('reports no stuck jobs when jobs table is empty', function () {
    $this->artisan('cue:reconcile')->assertSuccessful();
});

it('dry-run reports without resetting', function () {
    // Insert a fake "stuck" cue job row.
    Illuminate\Support\Facades\DB::table('jobs')->insert([
        'queue'   => 'default',
        'payload' => json_encode([
            'displayName' => 'App\\Domain\\Subscription\\Jobs\\Cue\\EnqueueTrialEnding2d',
            'job'         => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data'        => ['command' => ''],
        ]),
        'attempts'     => 1,
        'reserved_at'  => now()->subMinutes(15)->timestamp,
        'available_at' => now()->subHour()->timestamp,
        'created_at'   => now()->subHour()->timestamp,
    ]);

    $this->artisan('cue:reconcile', ['--dry-run' => true])->assertSuccessful();

    // The stuck row should still be reserved (not reset).
    $row = Illuminate\Support\Facades\DB::table('jobs')->first();
    assert($row !== null);
    expect($row->reserved_at)->not()->toBeNull();
});
