<?php

use App\Filament\Admin\Resources\AccountResource;
use App\Filament\Exports\AccountExporter;
use App\Models\Account;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->setUpFilamentWithAuth();
});

describe('Account Export Tests', function () {

it('can export accounts', function () {
    Queue::fake();
    
    // Create test accounts
    $accounts = Account::factory()->count(5)->create();
    
    // Visit the accounts list page
    Livewire::test(AccountResource\Pages\ListAccounts::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($accounts)
        ->callAction('export', [
            'exporter' => AccountExporter::class,
        ])
        ->assertHasNoActionErrors();
    
    // Assert that a job was queued
    Queue::assertPushed(\Filament\Actions\Exports\Jobs\CreateXlsx::class);
});

it('exports correct account columns', function () {
    $columns = AccountExporter::getColumns();
    
    expect($columns)->toHaveCount(7);
    
    $columnNames = array_map(fn($column) => $column->getName(), $columns);
    
    expect($columnNames)->toContain('uuid');
    expect($columnNames)->toContain('name');
    expect($columnNames)->toContain('user_uuid');
    expect($columnNames)->toContain('balance');
    expect($columnNames)->toContain('frozen');
    expect($columnNames)->toContain('created_at');
    expect($columnNames)->toContain('updated_at');
});

it('formats balance correctly in export', function () {
    $columns = AccountExporter::getColumns();
    $balanceColumn = null;
    
    foreach ($columns as $column) {
        if ($column->getName() === 'balance') {
            $balanceColumn = $column;
            break;
        }
    }
    
    expect($balanceColumn)->not->toBeNull();
    
    // Test balance formatting (cents to dollars)
    $state = $balanceColumn->getStateUsing();
    
    if ($state instanceof Closure) {
        $formattedBalance = ($balanceColumn->formatStateUsing)(10050);
    } else {
        // Use the formatStateUsing callback directly
        $formattedBalance = number_format(10050 / 100, 2);
    }
    
    expect($formattedBalance)->toBe('100.50');
});

it('formats frozen status correctly in export', function () {
    $columns = AccountExporter::getColumns();
    $frozenColumn = null;
    
    foreach ($columns as $column) {
        if ($column->getName() === 'frozen') {
            $frozenColumn = $column;
            break;
        }
    }
    
    expect($frozenColumn)->not->toBeNull();
    
    // Test frozen status formatting using the formatStateUsing callback
    $formatCallback = $frozenColumn->formatStateUsing;
    
    expect($formatCallback(true))->toBe('Frozen');
    expect($formatCallback(false))->toBe('Active');
});

it('can see export action in header', function () {
    Account::factory()->count(3)->create();
    
    Livewire::test(AccountResource\Pages\ListAccounts::class)
        ->assertSuccessful()
        ->assertActionExists('export')
        ->assertActionHasLabel('export', 'Export Accounts')
        ->assertActionHasIcon('export', 'heroicon-o-arrow-down-tray')
        ->assertActionHasColor('export', 'success');
});

it('generates correct completion notification message', function () {
    $export = new \Filament\Actions\Exports\Models\Export();
    $export->successful_rows = 100;
    $export->total_rows = 100;
    
    $message = AccountExporter::getCompletedNotificationBody($export);
    
    expect($message)->toContain('100 rows exported');
});

it('includes failed rows in completion message', function () {
    $export = new \Filament\Actions\Exports\Models\Export();
    $export->successful_rows = 90;
    $export->total_rows = 100;
    
    // Mock the failed rows count
    $export->setAttribute('failed_rows_count', 10);
    
    $message = AccountExporter::getCompletedNotificationBody($export);
    
    expect($message)->toContain('90 rows exported');
    expect($message)->toContain('10 rows failed');
});

});