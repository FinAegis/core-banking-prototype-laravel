<?php

use App\Domain\Payment\Projectors\PaymentDepositProjector;
use App\Domain\Payment\Events\DepositInitiated;
use App\Domain\Payment\Events\DepositCompleted;
use App\Domain\Payment\Events\DepositFailed;
use App\Models\PaymentTransaction;
use Illuminate\Support\Str;
use Carbon\Carbon;

beforeEach(function () {
    // Clear payment transactions before each test
    PaymentTransaction::truncate();
});

it('creates payment transaction on deposit initiated', function () {
    $aggregateUuid = Str::uuid()->toString();
    $accountUuid = Str::uuid()->toString();
    
    $event = new DepositInitiated(
        accountUuid: $accountUuid,
        amount: 10000,
        currency: 'USD',
        reference: 'TEST-123',
        externalReference: 'pi_test_123',
        paymentMethod: 'card',
        paymentMethodType: 'visa',
        metadata: ['test' => true]
    );
    
    $projector = new PaymentDepositProjector();
    $projector->onDepositInitiated($event, $aggregateUuid);
    
    $transaction = PaymentTransaction::where('aggregate_uuid', $aggregateUuid)->first();
    
    expect($transaction)->not->toBeNull();
    expect($transaction->account_uuid)->toBe($accountUuid);
    expect($transaction->type)->toBe('deposit');
    expect($transaction->status)->toBe('pending');
    expect($transaction->amount)->toBe(10000);
    expect($transaction->currency)->toBe('USD');
    expect($transaction->reference)->toBe('TEST-123');
    expect($transaction->external_reference)->toBe('pi_test_123');
    expect($transaction->payment_method)->toBe('card');
    expect($transaction->payment_method_type)->toBe('visa');
    expect($transaction->metadata)->toBe(['test' => true]);
});

it('updates payment transaction on deposit completed', function () {
    $aggregateUuid = Str::uuid()->toString();
    $transactionId = 'txn_123';
    $completedAt = now();
    
    // Create a pending transaction first
    PaymentTransaction::create([
        'aggregate_uuid' => $aggregateUuid,
        'account_uuid' => Str::uuid()->toString(),
        'type' => 'deposit',
        'status' => 'pending',
        'amount' => 10000,
        'currency' => 'USD',
        'reference' => 'TEST-123',
        'initiated_at' => now()->subMinutes(5),
    ]);
    
    $event = new DepositCompleted(
        transactionId: $transactionId,
        completedAt: $completedAt
    );
    
    $projector = new PaymentDepositProjector();
    $projector->onDepositCompleted($event, $aggregateUuid);
    
    $transaction = PaymentTransaction::where('aggregate_uuid', $aggregateUuid)->first();
    
    expect($transaction->status)->toBe('completed');
    expect($transaction->transaction_id)->toBe($transactionId);
    expect($transaction->completed_at->toDateTimeString())->toBe($completedAt->toDateTimeString());
});

it('updates payment transaction on deposit failed', function () {
    $aggregateUuid = Str::uuid()->toString();
    $reason = 'Card declined';
    $failedAt = now();
    
    // Create a pending transaction first
    PaymentTransaction::create([
        'aggregate_uuid' => $aggregateUuid,
        'account_uuid' => Str::uuid()->toString(),
        'type' => 'deposit',
        'status' => 'pending',
        'amount' => 10000,
        'currency' => 'USD',
        'reference' => 'TEST-123',
        'initiated_at' => now()->subMinutes(5),
    ]);
    
    $event = new DepositFailed(
        reason: $reason,
        failedAt: $failedAt
    );
    
    $projector = new PaymentDepositProjector();
    $projector->onDepositFailed($event, $aggregateUuid);
    
    $transaction = PaymentTransaction::where('aggregate_uuid', $aggregateUuid)->first();
    
    expect($transaction->status)->toBe('failed');
    expect($transaction->failed_reason)->toBe($reason);
    expect($transaction->failed_at->toDateTimeString())->toBe($failedAt->toDateTimeString());
});