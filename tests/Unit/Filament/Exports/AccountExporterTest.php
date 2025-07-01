<?php

use App\Filament\Exports\AccountExporter;
use App\Models\Account;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Tests\UnitTestCase;

uses(UnitTestCase::class);

it('can define export columns for accounts', function () {
    $columns = AccountExporter::getColumns();
    
    expect($columns)->toHaveCount(7)
        ->and($columns[0]->getName())->toBe('uuid')
        ->and($columns[0]->getLabel())->toBe('Account ID')
        ->and($columns[1]->getName())->toBe('name')
        ->and($columns[1]->getLabel())->toBe('Account Name')
        ->and($columns[2]->getName())->toBe('user_uuid')
        ->and($columns[2]->getLabel())->toBe('User ID')
        ->and($columns[3]->getName())->toBe('balance')
        ->and($columns[3]->getLabel())->toBe('Balance (USD)')
        ->and($columns[4]->getName())->toBe('frozen')
        ->and($columns[4]->getLabel())->toBe('Status')
        ->and($columns[5]->getName())->toBe('created_at')
        ->and($columns[5]->getLabel())->toBe('Created Date')
        ->and($columns[6]->getName())->toBe('updated_at')
        ->and($columns[6]->getLabel())->toBe('Last Updated');
});

it('formats balance correctly from cents to dollars', function () {
    $columns = AccountExporter::getColumns();
    $balanceColumn = $columns[3];
    
    // Test the format state using method
    $reflection = new ReflectionClass($balanceColumn);
    $property = $reflection->getProperty('formatStateUsing');
    $property->setAccessible(true);
    $formatState = $property->getValue($balanceColumn);
    
    expect($formatState(10050))->toBe('100.50')
        ->and($formatState(0))->toBe('0.00')
        ->and($formatState(999))->toBe('9.99')
        ->and($formatState(100000))->toBe('1,000.00');
});

it('formats frozen status to human readable text', function () {
    $columns = AccountExporter::getColumns();
    $statusColumn = $columns[4];
    
    // Test the format state using method
    $reflection = new ReflectionClass($statusColumn);
    $property = $reflection->getProperty('formatStateUsing');
    $property->setAccessible(true);
    $formatState = $property->getValue($statusColumn);
    
    expect($formatState(true))->toBe('Frozen')
        ->and($formatState(false))->toBe('Active');
});

it('generates correct completion notification body', function () {
    $export = new Export();
    $export->successful_rows = 100;
    $export->total_rows = 100;
    
    $body = AccountExporter::getCompletedNotificationBody($export);
    
    expect($body)->toBe('Your account export has completed and 100 rows exported.');
});

it('generates correct completion notification body with failed rows', function () {
    $export = new Export();
    $export->successful_rows = 90;
    $export->total_rows = 100;
    
    $body = AccountExporter::getCompletedNotificationBody($export);
    
    expect($body)->toBe('Your account export has completed and 90 rows exported. 10 rows failed to export.');
});

it('has correct model association', function () {
    expect(AccountExporter::getModel())->toBe(Account::class);
});