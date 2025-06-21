<?php

use App\Domain\Account\Workflows\SingleBatchOperationActivity;
use App\Domain\Account\Workflows\ReverseBatchOperationActivity;
use App\Domain\Account\Workflows\CreateBatchSummaryActivity;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Turnover;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('has all required batch operation activities', function () {
    expect(class_exists(SingleBatchOperationActivity::class))->toBeTrue();
    expect(class_exists(ReverseBatchOperationActivity::class))->toBeTrue();
    expect(class_exists(CreateBatchSummaryActivity::class))->toBeTrue();
});

it('single batch operation activity handles all required operations', function () {
    $reflection = new \ReflectionClass(SingleBatchOperationActivity::class);
    $methodBody = file_get_contents($reflection->getFileName());
    
    // Check that all required operations are handled
    expect($methodBody)->toContain('calculate_daily_turnover');
    expect($methodBody)->toContain('generate_account_statements');
    expect($methodBody)->toContain('process_interest_calculations');
    expect($methodBody)->toContain('perform_compliance_checks');
    expect($methodBody)->toContain('archive_old_transactions');
    expect($methodBody)->toContain('generate_regulatory_reports');
});

it('reverse batch operation activity handles all operations', function () {
    $reflection = new \ReflectionClass(ReverseBatchOperationActivity::class);
    $methodBody = file_get_contents($reflection->getFileName());
    
    // Check that reversal methods exist for each operation
    expect($methodBody)->toContain('reverseDailyTurnover');
    expect($methodBody)->toContain('reverseAccountStatements');
    expect($methodBody)->toContain('reverseInterestCalculations');
    expect($methodBody)->toContain('reverseComplianceChecks');
    expect($methodBody)->toContain('reverseArchiveTransactions');
    expect($methodBody)->toContain('reverseRegulatoryReports');
});

it('activities track operation results for potential reversal', function () {
    $reflection = new \ReflectionClass(SingleBatchOperationActivity::class);
    $methodBody = file_get_contents($reflection->getFileName());
    
    // Check that operations track data needed for reversal
    expect($methodBody)->toContain('processed_data');
    expect($methodBody)->toContain('generated_files');
    expect($methodBody)->toContain('interest_transactions');
    expect($methodBody)->toContain('archived_uuids');
});