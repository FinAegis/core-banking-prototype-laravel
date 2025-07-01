<?php

use App\Filament\Exports\UserExporter;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Tests\UnitTestCase;

uses(UnitTestCase::class);

it('can define export columns for users', function () {
    $columns = UserExporter::getColumns();
    
    expect($columns)->toHaveCount(7)
        ->and($columns[0]->getName())->toBe('uuid')
        ->and($columns[0]->getLabel())->toBe('User ID')
        ->and($columns[1]->getName())->toBe('name')
        ->and($columns[1]->getLabel())->toBe('Full Name')
        ->and($columns[2]->getName())->toBe('email')
        ->and($columns[2]->getLabel())->toBe('Email Address')
        ->and($columns[3]->getName())->toBe('email_verified_at')
        ->and($columns[3]->getLabel())->toBe('Email Verified')
        ->and($columns[4]->getName())->toBe('accounts_count')
        ->and($columns[4]->getLabel())->toBe('Number of Accounts')
        ->and($columns[5]->getName())->toBe('created_at')
        ->and($columns[5]->getLabel())->toBe('Registration Date')
        ->and($columns[6]->getName())->toBe('updated_at')
        ->and($columns[6]->getLabel())->toBe('Last Updated');
});

it('includes accounts count column', function () {
    $columns = UserExporter::getColumns();
    $accountsColumn = $columns[4];
    
    // Check that it includes accounts count column
    expect($accountsColumn->getName())->toBe('accounts_count')
        ->and($accountsColumn->getLabel())->toBe('Number of Accounts');
});

it('generates correct completion notification body', function () {
    $export = new Export();
    $export->successful_rows = 50;
    $export->total_rows = 50;
    
    $body = UserExporter::getCompletedNotificationBody($export);
    
    expect($body)->toBe('Your user export has completed and 50 rows exported.');
});

it('generates correct completion notification body with failed rows', function () {
    $export = new Export();
    $export->successful_rows = 45;
    $export->total_rows = 50;
    
    $body = UserExporter::getCompletedNotificationBody($export);
    
    expect($body)->toBe('Your user export has completed and 45 rows exported. 5 rows failed to export.');
});

it('has correct model association', function () {
    expect(UserExporter::getModel())->toBe(User::class);
});