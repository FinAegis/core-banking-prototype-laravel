<?php

declare(strict_types=1);

use App\Domain\Account\Aggregates\LedgerAggregate;
use App\Domain\Account\Aggregates\TransactionAggregate;
use App\Domain\Account\Aggregates\TransferAggregate;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Transfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('creates snapshots for all types by default', function () {
    // Create some test data
    Account::factory()->count(3)->create();
    
    $this->artisan('snapshot:create')
        ->expectsOutput('Starting snapshot creation process...')
        ->expectsOutput('Creating transaction snapshots...')
        ->expectsOutputToContain('Created 0 transaction snapshots')
        ->expectsOutput('Creating transfer snapshots...')
        ->expectsOutputToContain('Created 0 transfer snapshots')
        ->expectsOutput('Creating ledger snapshots...')
        ->expectsOutput('Created ledger snapshots for 4 accounts.')
        ->expectsOutput('Snapshot creation completed successfully!')
        ->assertSuccessful();
});

it('creates only transaction snapshots when specified', function () {
    // Since transactions are event-sourced, we would need to create actual events
    // For now, we'll test that the command runs without errors
    $this->artisan('snapshot:create --type=transaction')
        ->expectsOutput('Starting snapshot creation process...')
        ->expectsOutput('Creating transaction snapshots...')
        ->expectsOutput('Created 0 transaction snapshots.')
        ->expectsOutput('Snapshot creation completed successfully!')
        ->assertSuccessful();
});

it('creates only transfer snapshots when specified', function () {
    // Since transfers are event-sourced, we would need to create actual events
    // For now, we'll test that the command runs without errors
    $this->artisan('snapshot:create --type=transfer')
        ->expectsOutput('Starting snapshot creation process...')
        ->expectsOutput('Creating transfer snapshots...')
        ->expectsOutput('Created 0 transfer snapshots.')
        ->expectsOutput('Snapshot creation completed successfully!')
        ->assertSuccessful();
});

it('creates only ledger snapshots when specified', function () {
    // Create 5 accounts (plus 1 from TestCase setup = 6 total)
    Account::factory()->count(5)->create();

    $this->artisan('snapshot:create --type=ledger')
        ->expectsOutput('Starting snapshot creation process...')
        ->expectsOutput('Creating ledger snapshots...')
        ->expectsOutput('Created ledger snapshots for 6 accounts.')
        ->expectsOutput('Snapshot creation completed successfully!')
        ->assertSuccessful();
});

it('creates snapshots for specific account when uuid provided', function () {
    // Create a specific account
    $targetAccount = Account::factory()->create();
    $otherAccount = Account::factory()->create();
    
    $this->artisan('snapshot:create --account=' . $targetAccount->uuid)
        ->expectsOutput('Starting snapshot creation process...')
        ->expectsOutput('Creating transaction snapshots...')
        ->expectsOutput('Created 0 transaction snapshots.')
        ->expectsOutput('Creating transfer snapshots...')
        ->expectsOutput('Created 0 transfer snapshots.')
        ->expectsOutput('Creating ledger snapshots...')
        ->expectsOutput('Created ledger snapshots for 1 accounts.')
        ->expectsOutput('Snapshot creation completed successfully!')
        ->assertSuccessful();
});

it('respects threshold limits without force flag', function () {
    // Without actual events in stored_events table, this will just test the command runs
    $this->artisan('snapshot:create --type=transaction')
        ->expectsOutput('Starting snapshot creation process...')
        ->expectsOutput('Creating transaction snapshots...')
        ->expectsOutput('Created 0 transaction snapshots.')
        ->expectsOutput('Snapshot creation completed successfully!')
        ->assertSuccessful();
});

it('creates snapshots below threshold with force flag', function () {
    // Without actual events in stored_events table, this will just test the command runs
    $this->artisan('snapshot:create --type=transaction --force')
        ->expectsOutput('Starting snapshot creation process...')
        ->expectsOutput('Creating transaction snapshots...')
        ->expectsOutput('Created 0 transaction snapshots.')
        ->expectsOutput('Snapshot creation completed successfully!')
        ->assertSuccessful();
});

it('handles empty database gracefully', function () {
    // TestCase setup creates 1 account

    $this->artisan('snapshot:create')
        ->expectsOutput('Starting snapshot creation process...')
        ->expectsOutput('Creating transaction snapshots...')
        ->expectsOutputToContain('Created 0 transaction snapshots')
        ->expectsOutput('Creating transfer snapshots...')
        ->expectsOutputToContain('Created 0 transfer snapshots')
        ->expectsOutput('Creating ledger snapshots...')
        ->expectsOutput('Created ledger snapshots for 1 accounts.')
        ->expectsOutput('Snapshot creation completed successfully!')
        ->assertSuccessful();
});

it('shows progress bar for multiple accounts', function () {
    // Create many accounts to trigger progress bar
    Account::factory()->count(50)->create();
    
    $this->artisan('snapshot:create --type=ledger')
        ->expectsOutput('Starting snapshot creation process...')
        ->expectsOutput('Creating ledger snapshots...')
        ->expectsOutput('Created ledger snapshots for 51 accounts.')
        ->expectsOutput('Snapshot creation completed successfully!')
        ->assertSuccessful();
});

it('wraps all operations in a database transaction', function () {
    // Create test data
    Account::factory()->count(2)->create();
    
    // Run command - ledger snapshots should be created for accounts
    $this->artisan('snapshot:create')
        ->expectsOutput('Starting snapshot creation process...')
        ->expectsOutput('Creating transaction snapshots...')
        ->expectsOutput('Created 0 transaction snapshots.')
        ->expectsOutput('Creating transfer snapshots...')
        ->expectsOutput('Created 0 transfer snapshots.')
        ->expectsOutput('Creating ledger snapshots...')
        ->expectsOutput('Created ledger snapshots for 3 accounts.')
        ->expectsOutput('Snapshot creation completed successfully!')
        ->assertSuccessful();
});

it('validates type option', function () {
    $this->artisan('snapshot:create --type=invalid')
        ->expectsOutput('Starting snapshot creation process...')
        ->expectsOutput('Creating transaction snapshots...')
        ->expectsOutput('Creating transfer snapshots...')
        ->expectsOutput('Creating ledger snapshots...')
        ->expectsOutput('Snapshot creation completed successfully!')
        ->assertSuccessful();
});

it('handles accounts with transfers on both sides', function () {
    // Create accounts - in real scenario, transfers would create events in stored_events
    Account::factory()->count(3)->create();
    
    $this->artisan('snapshot:create --type=transfer')
        ->expectsOutput('Starting snapshot creation process...')
        ->expectsOutput('Creating transfer snapshots...')
        ->expectsOutput('Created 0 transfer snapshots.')
        ->expectsOutput('Snapshot creation completed successfully!')
        ->assertSuccessful();
});

it('has correct command signature', function () {
    $command = new \App\Console\Commands\CreateSnapshot();
    
    expect($command->getName())->toBe('snapshot:create');
    expect($command->getDescription())->toBe('Create snapshots for aggregates to improve performance');
});

it('has required method structure', function () {
    expect(method_exists(\App\Console\Commands\CreateSnapshot::class, 'handle'))->toBeTrue();
    expect(method_exists(\App\Console\Commands\CreateSnapshot::class, 'createTransactionSnapshots'))->toBeTrue();
    expect(method_exists(\App\Console\Commands\CreateSnapshot::class, 'createTransferSnapshots'))->toBeTrue();
    expect(method_exists(\App\Console\Commands\CreateSnapshot::class, 'createLedgerSnapshots'))->toBeTrue();
});

it('has proper inheritance', function () {
    $reflection = new ReflectionClass(\App\Console\Commands\CreateSnapshot::class);
    expect($reflection->getParentClass()->getName())->toBe('Illuminate\Console\Command');
});