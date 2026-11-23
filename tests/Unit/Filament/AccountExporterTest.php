<?php

use App\Domain\Account\Models\Account;
use App\Filament\Exports\AccountExporter;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Tests\UnitTestCase;

uses(UnitTestCase::class);

it('has correct model', function () {
    $reflection = new ReflectionClass(AccountExporter::class);
    $property = $reflection->getProperty('model');

    expect($property->getValue())->toBe(Account::class);
});

it('defines correct export columns', function () {
    $columns = AccountExporter::getColumns();

    expect($columns)->toHaveCount(7);

    $columnNames = array_map(fn ($column) => $column->getName(), $columns);

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
        'uuid'       => 'Account ID',
        'name'       => 'Account Name',
        'user_uuid'  => 'User ID',
        'balance'    => 'Balance (USD)',
        'frozen'     => 'Status',
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
    assert($balanceColumn instanceof ExportColumn);

    // Get the format callback
    $reflection = new ReflectionObject($balanceColumn);
    $property = $reflection->getProperty('formatStateUsing');
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
    assert($frozenColumn instanceof ExportColumn);

    // Get the format callback
    $reflection = new ReflectionObject($frozenColumn);
    $property = $reflection->getProperty('formatStateUsing');
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

    expect($message)->toBe('Your account export is complete. 100 rows were exported.');
});

it('generates correct completion notification with failed rows', function () {
    // Create a mock export with proper setup
    $export = Mockery::mock(Export::class)->makePartial();
    $export->successful_rows = 90;
    $export->shouldReceive('getFailedRowsCount')->andReturn(10);

    $message = AccountExporter::getCompletedNotificationBody($export);

    expect($message)->toBe('Your account export is complete. 90 rows were exported. 10 rows could not be exported.');
});

it('handles singular row correctly in notification', function () {
    $export = new Export();
    $export->successful_rows = 1;
    $export->total_rows = 1;

    $message = AccountExporter::getCompletedNotificationBody($export);

    expect($message)->toBe('Your account export is complete. 1 row was exported.');
});
