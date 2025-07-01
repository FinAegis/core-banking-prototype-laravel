<?php

use App\Filament\Exports\AccountExporter;
use App\Models\Account;
use Filament\Actions\Exports\Models\Export;
use Tests\UnitTestCase;

uses(UnitTestCase::class);

it('has correct model', function () {
    $reflection = new ReflectionClass(AccountExporter::class);
    $property = $reflection->getProperty('model');
    $property->setAccessible(true);
    
    expect($property->getValue())->toBe(Account::class);
});

it('defines correct export columns', function () {
    $columns = AccountExporter::getColumns();
    
    expect($columns)->toHaveCount(7);
    
    $columnNames = array_map(fn($column) => $column->getName(), $columns);
    
    expect($columnNames)->toEqual([
        'uuid',
        'name',
        'user_uuid',
        'balance',
        'frozen',
        'created_at',
        'updated_at',
    ]);
});

it('has correct column labels', function () {
    $columns = AccountExporter::getColumns();
    $labels = [];
    
    foreach ($columns as $column) {
        $labels[$column->getName()] = $column->getLabel();
    }
    
    expect($labels)->toEqual([
        'uuid' => 'Account ID',
        'name' => 'Account Name',
        'user_uuid' => 'User ID',
        'balance' => 'Balance (USD)',
        'frozen' => 'Status',
        'created_at' => 'Created Date',
        'updated_at' => 'Last Updated',
    ]);
});

it('formats balance from cents to dollars', function () {
    $columns = AccountExporter::getColumns();
    $balanceColumn = null;
    
    foreach ($columns as $column) {
        if ($column->getName() === 'balance') {
            $balanceColumn = $column;
            break;
        }
    }
    
    expect($balanceColumn)->not->toBeNull();
    
    // Get the format callback
    $reflection = new ReflectionObject($balanceColumn);
    $property = $reflection->getProperty('formatStateUsing');
    $property->setAccessible(true);
    $formatCallback = $property->getValue($balanceColumn);
    
    // Test formatting
    expect($formatCallback(10050))->toBe('100.50');
    expect($formatCallback(0))->toBe('0.00');
    expect($formatCallback(999))->toBe('9.99');
});

it('formats frozen status to text', function () {
    $columns = AccountExporter::getColumns();
    $frozenColumn = null;
    
    foreach ($columns as $column) {
        if ($column->getName() === 'frozen') {
            $frozenColumn = $column;
            break;
        }
    }
    
    expect($frozenColumn)->not->toBeNull();
    
    // Get the format callback
    $reflection = new ReflectionObject($frozenColumn);
    $property = $reflection->getProperty('formatStateUsing');
    $property->setAccessible(true);
    $formatCallback = $property->getValue($frozenColumn);
    
    // Test formatting
    expect($formatCallback(true))->toBe('Frozen');
    expect($formatCallback(false))->toBe('Active');
});

it('generates correct completion notification for successful export', function () {
    $export = new Export();
    $export->successful_rows = 100;
    $export->total_rows = 100;
    
    $message = AccountExporter::getCompletedNotificationBody($export);
    
    expect($message)->toBe('Your account export has completed and 100 rows exported.');
});

it('generates correct completion notification with failed rows', function () {
    // Create a mock export with proper setup
    $export = Mockery::mock(Export::class)->makePartial();
    $export->successful_rows = 90;
    $export->shouldReceive('getFailedRowsCount')->andReturn(10);
    
    $message = AccountExporter::getCompletedNotificationBody($export);
    
    expect($message)->toBe('Your account export has completed and 90 rows exported. 10 rows failed to export.');
});

it('handles singular row correctly in notification', function () {
    $export = new Export();
    $export->successful_rows = 1;
    $export->total_rows = 1;
    
    $message = AccountExporter::getCompletedNotificationBody($export);
    
    expect($message)->toBe('Your account export has completed and 1 row exported.');
});