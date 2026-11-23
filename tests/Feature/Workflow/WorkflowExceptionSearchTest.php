<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

// Workflow exceptions are stored in workflow_exceptions (the "exceptions"
// relation); the workflows table has no exception column. Searching by
// exception filtered on that missing column: MySQL rejected the query and
// SQLite compared the literal string "exception", so nothing was found.

it('finds a workflow by the text of its stored exception', function (): void {
    $workflowId = DB::table('workflows')->insertGetId([
        'class'      => 'App\\Domain\\Payment\\Workflows\\TransferWorkflow',
        'arguments'  => 'args',
        'status'     => 'failed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('workflow_exceptions')->insert([
        'stored_workflow_id' => $workflowId,
        'class'              => 'App\\Domain\\Payment\\Activities\\DebitActivity',
        'exception'          => 'RuntimeException: wfx-insufficient-liquidity',
        'created_at'         => now(),
    ]);

    Role::firstOrCreate(['name' => 'bank_admin', 'guard_name' => 'web']);
    $operator = User::factory()->create();
    $operator->assignRole('bank_admin');
    Sanctum::actingAs($operator, ['read', 'write', 'delete']);

    $this->getJson('/api/workflows/search?query=wfx-insufficient&type=exception')
        ->assertOk()
        ->assertJsonPath('meta.total_found', 1)
        ->assertJsonPath('results.0.id', $workflowId);
});
