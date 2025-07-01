<?php

use App\Filament\Exports\TransactionExporter;
use App\Models\Transaction;
use Filament\Actions\Exports\Models\Export;
use Tests\UnitTestCase;

uses(UnitTestCase::class);

it('can define export columns for transactions', function () {
    $columns = TransactionExporter::getColumns();
    
    expect($columns)->toHaveCount(10)
        ->and($columns[0]->getName())->toBe('processed_at')
        ->and($columns[0]->getLabel())->toBe('Date')
        ->and($columns[1]->getName())->toBe('account.name')
        ->and($columns[1]->getLabel())->toBe('Account')
        ->and($columns[2]->getName())->toBe('type')
        ->and($columns[2]->getLabel())->toBe('Type')
        ->and($columns[3]->getName())->toBe('amount')
        ->and($columns[3]->getLabel())->toBe('Amount')
        ->and($columns[4]->getName())->toBe('asset_code')
        ->and($columns[4]->getLabel())->toBe('Currency')
        ->and($columns[5]->getName())->toBe('description')
        ->and($columns[5]->getLabel())->toBe('Description')
        ->and($columns[6]->getName())->toBe('status')
        ->and($columns[6]->getLabel())->toBe('Status')
        ->and($columns[7]->getName())->toBe('initiator.name')
        ->and($columns[7]->getLabel())->toBe('Initiated By')
        ->and($columns[8]->getName())->toBe('uuid')
        ->and($columns[8]->getLabel())->toBe('Transaction ID')
        ->and($columns[9]->getName())->toBe('hash')
        ->and($columns[9]->getLabel())->toBe('Hash');
});

it('formats transaction type correctly', function () {
    $columns = TransactionExporter::getColumns();
    $typeColumn = $columns[2];
    
    // Test the format state using method
    $reflection = new ReflectionClass($typeColumn);
    $property = $reflection->getProperty('formatStateUsing');
    $property->setAccessible(true);
    $formatState = $property->getValue($typeColumn);
    
    expect($formatState('deposit'))->toBe('Deposit')
        ->and($formatState('withdrawal'))->toBe('Withdrawal')  
        ->and($formatState('transfer_in'))->toBe('Transfer In')
        ->and($formatState('transfer_out'))->toBe('Transfer Out');
});

it('formats amount correctly with sign', function () {
    $columns = TransactionExporter::getColumns();
    $amountColumn = $columns[3];
    
    // Test the format state using method
    $reflection = new ReflectionClass($amountColumn);
    $property = $reflection->getProperty('formatStateUsing');
    $property->setAccessible(true);
    $formatState = $property->getValue($amountColumn);
    
    // Create mock records for testing
    $creditRecord = new class {
        public function getDirection() { return 'credit'; }
    };
    $debitRecord = new class {
        public function getDirection() { return 'debit'; }
    };
    
    expect($formatState(10050, $creditRecord))->toBe('+100.50')
        ->and($formatState(10050, $debitRecord))->toBe('-100.50')
        ->and($formatState(0, $creditRecord))->toBe('+0.00');
});

it('formats status correctly', function () {
    $columns = TransactionExporter::getColumns();
    $statusColumn = $columns[6];
    
    // Test the format state using method
    $reflection = new ReflectionClass($statusColumn);
    $property = $reflection->getProperty('formatStateUsing');
    $property->setAccessible(true);
    $formatState = $property->getValue($statusColumn);
    
    expect($formatState('completed'))->toBe('Completed')
        ->and($formatState('pending'))->toBe('Pending')
        ->and($formatState('failed'))->toBe('Failed');
});

it('generates correct completion notification body', function () {
    $export = new Export();
    $export->successful_rows = 500;
    $export->total_rows = 500;
    
    $body = TransactionExporter::getCompletedNotificationBody($export);
    
    expect($body)->toBe('Your transaction export has completed and 500 rows exported.');
});

it('has correct model association', function () {
    expect(TransactionExporter::getModel())->toBe(Transaction::class);
});