<?php

use App\Models\PaymentTransaction;
use App\Models\Account;
use Illuminate\Support\Str;

it('can create a payment transaction', function () {
    $transaction = PaymentTransaction::create([
        'aggregate_uuid' => Str::uuid()->toString(),
        'account_uuid' => Str::uuid()->toString(),
        'type' => 'deposit',
        'status' => 'pending',
        'amount' => 10000,
        'currency' => 'USD',
        'reference' => 'TEST-123',
        'external_reference' => 'pi_test_123',
        'payment_method' => 'card',
        'payment_method_type' => 'visa',
        'metadata' => ['test' => true],
        'initiated_at' => now(),
    ]);
    
    expect($transaction)->toBeInstanceOf(PaymentTransaction::class);
    expect($transaction->type)->toBe('deposit');
    expect($transaction->status)->toBe('pending');
    expect($transaction->amount)->toBe(10000);
});

it('casts metadata to array', function () {
    $metadata = ['key' => 'value', 'nested' => ['data' => true]];
    
    $transaction = PaymentTransaction::create([
        'aggregate_uuid' => Str::uuid()->toString(),
        'account_uuid' => Str::uuid()->toString(),
        'type' => 'deposit',
        'status' => 'pending',
        'amount' => 10000,
        'currency' => 'USD',
        'reference' => 'TEST-123',
        'metadata' => $metadata,
        'initiated_at' => now(),
    ]);
    
    expect($transaction->metadata)->toBe($metadata);
    expect($transaction->metadata)->toBeArray();
});

it('casts date fields to carbon instances', function () {
    $transaction = PaymentTransaction::create([
        'aggregate_uuid' => Str::uuid()->toString(),
        'account_uuid' => Str::uuid()->toString(),
        'type' => 'deposit',
        'status' => 'completed',
        'amount' => 10000,
        'currency' => 'USD',
        'reference' => 'TEST-123',
        'initiated_at' => now(),
        'completed_at' => now(),
    ]);
    
    expect($transaction->initiated_at)->toBeInstanceOf(Carbon\Carbon::class);
    expect($transaction->completed_at)->toBeInstanceOf(Carbon\Carbon::class);
});

it('has account relationship', function () {
    $accountUuid = Str::uuid()->toString();
    $account = Account::factory()->create(['uuid' => $accountUuid]);
    
    $transaction = PaymentTransaction::create([
        'aggregate_uuid' => Str::uuid()->toString(),
        'account_uuid' => $accountUuid,
        'type' => 'deposit',
        'status' => 'pending',
        'amount' => 10000,
        'currency' => 'USD',
        'reference' => 'TEST-123',
        'initiated_at' => now(),
    ]);
    
    expect($transaction->account)->toBeInstanceOf(Account::class);
    expect($transaction->account->uuid)->toBe($accountUuid);
});

it('can check if transaction is pending', function () {
    $transaction = PaymentTransaction::create([
        'aggregate_uuid' => Str::uuid()->toString(),
        'account_uuid' => Str::uuid()->toString(),
        'type' => 'deposit',
        'status' => 'pending',
        'amount' => 10000,
        'currency' => 'USD',
        'reference' => 'TEST-123',
        'initiated_at' => now(),
    ]);
    
    expect($transaction->isPending())->toBeTrue();
    expect($transaction->isCompleted())->toBeFalse();
    expect($transaction->isFailed())->toBeFalse();
});

it('can check if transaction is completed', function () {
    $transaction = PaymentTransaction::create([
        'aggregate_uuid' => Str::uuid()->toString(),
        'account_uuid' => Str::uuid()->toString(),
        'type' => 'deposit',
        'status' => 'completed',
        'amount' => 10000,
        'currency' => 'USD',
        'reference' => 'TEST-123',
        'transaction_id' => 'txn_123',
        'initiated_at' => now(),
        'completed_at' => now(),
    ]);
    
    expect($transaction->isCompleted())->toBeTrue();
    expect($transaction->isPending())->toBeFalse();
    expect($transaction->isFailed())->toBeFalse();
});

it('can check if transaction is failed', function () {
    $transaction = PaymentTransaction::create([
        'aggregate_uuid' => Str::uuid()->toString(),
        'account_uuid' => Str::uuid()->toString(),
        'type' => 'deposit',
        'status' => 'failed',
        'amount' => 10000,
        'currency' => 'USD',
        'reference' => 'TEST-123',
        'failed_reason' => 'Card declined',
        'initiated_at' => now(),
        'failed_at' => now(),
    ]);
    
    expect($transaction->isFailed())->toBeTrue();
    expect($transaction->isPending())->toBeFalse();
    expect($transaction->isCompleted())->toBeFalse();
});

it('formats amount correctly', function () {
    $transaction = PaymentTransaction::create([
        'aggregate_uuid' => Str::uuid()->toString(),
        'account_uuid' => Str::uuid()->toString(),
        'type' => 'deposit',
        'status' => 'pending',
        'amount' => 12345, // $123.45
        'currency' => 'USD',
        'reference' => 'TEST-123',
        'initiated_at' => now(),
    ]);
    
    expect($transaction->formatted_amount)->toBe('123.45 USD');
    
    $transaction2 = PaymentTransaction::create([
        'aggregate_uuid' => Str::uuid()->toString(),
        'account_uuid' => Str::uuid()->toString(),
        'type' => 'withdrawal',
        'status' => 'pending',
        'amount' => 5000, // $50.00
        'currency' => 'EUR',
        'reference' => 'TEST-456',
        'initiated_at' => now(),
    ]);
    
    expect($transaction2->formatted_amount)->toBe('50.00 EUR');
});

it('can filter by type and status', function () {
    // Create various transactions
    PaymentTransaction::create([
        'aggregate_uuid' => Str::uuid()->toString(),
        'account_uuid' => Str::uuid()->toString(),
        'type' => 'deposit',
        'status' => 'completed',
        'amount' => 10000,
        'currency' => 'USD',
        'reference' => 'DEP-1',
        'initiated_at' => now(),
    ]);
    
    PaymentTransaction::create([
        'aggregate_uuid' => Str::uuid()->toString(),
        'account_uuid' => Str::uuid()->toString(),
        'type' => 'deposit',
        'status' => 'pending',
        'amount' => 5000,
        'currency' => 'USD',
        'reference' => 'DEP-2',
        'initiated_at' => now(),
    ]);
    
    PaymentTransaction::create([
        'aggregate_uuid' => Str::uuid()->toString(),
        'account_uuid' => Str::uuid()->toString(),
        'type' => 'withdrawal',
        'status' => 'completed',
        'amount' => 3000,
        'currency' => 'USD',
        'reference' => 'WD-1',
        'initiated_at' => now(),
    ]);
    
    // Test filtering
    $completedDeposits = PaymentTransaction::where('type', 'deposit')
        ->where('status', 'completed')
        ->get();
    
    expect($completedDeposits)->toHaveCount(1);
    expect($completedDeposits->first()->reference)->toBe('DEP-1');
    
    $pendingTransactions = PaymentTransaction::where('status', 'pending')->get();
    expect($pendingTransactions)->toHaveCount(1);
    
    $withdrawals = PaymentTransaction::where('type', 'withdrawal')->get();
    expect($withdrawals)->toHaveCount(1);
});