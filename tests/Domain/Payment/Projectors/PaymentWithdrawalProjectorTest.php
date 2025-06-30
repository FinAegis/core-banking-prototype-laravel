<?php

use App\Domain\Payment\Projectors\PaymentWithdrawalProjector;
use App\Domain\Payment\Events\WithdrawalInitiated;
use App\Domain\Payment\Events\WithdrawalCompleted;
use App\Domain\Payment\Events\WithdrawalFailed;
use App\Models\PaymentTransaction;
use Illuminate\Support\Str;
use Carbon\Carbon;

beforeEach(function () {
    // Clear payment transactions before each test
    PaymentTransaction::truncate();
});

it('creates payment transaction on withdrawal initiated', function () {
    $aggregateUuid = Str::uuid()->toString();
    $accountUuid = Str::uuid()->toString();
    
    $event = new WithdrawalInitiated(
        accountUuid: $accountUuid,
        amount: 5000,
        currency: 'USD',
        reference: 'WD-123',
        bankAccountNumber: '****1234',
        bankRoutingNumber: '123456789',
        bankAccountName: 'John Doe',
        metadata: ['test' => true]
    );
    
    $projector = new PaymentWithdrawalProjector();
    $projector->onWithdrawalInitiated($event, $aggregateUuid);
    
    $transaction = PaymentTransaction::where('aggregate_uuid', $aggregateUuid)->first();
    
    expect($transaction)->not->toBeNull();
    expect($transaction->account_uuid)->toBe($accountUuid);
    expect($transaction->type)->toBe('withdrawal');
    expect($transaction->status)->toBe('pending');
    expect($transaction->amount)->toBe(5000);
    expect($transaction->currency)->toBe('USD');
    expect($transaction->reference)->toBe('WD-123');
    expect($transaction->bank_account_number)->toBe('****1234');
    expect($transaction->bank_routing_number)->toBe('123456789');
    expect($transaction->bank_account_name)->toBe('John Doe');
    expect($transaction->metadata)->toBe(['test' => true]);
});

it('updates payment transaction on withdrawal completed', function () {
    $aggregateUuid = Str::uuid()->toString();
    $transactionId = 'wtxn_123';
    $completedAt = now();
    
    // Create a pending transaction first
    PaymentTransaction::create([
        'aggregate_uuid' => $aggregateUuid,
        'account_uuid' => Str::uuid()->toString(),
        'type' => 'withdrawal',
        'status' => 'pending',
        'amount' => 5000,
        'currency' => 'USD',
        'reference' => 'WD-123',
        'bank_account_number' => '****1234',
        'bank_routing_number' => '123456789',
        'bank_account_name' => 'John Doe',
        'initiated_at' => now()->subMinutes(5),
    ]);
    
    $event = new WithdrawalCompleted(
        transactionId: $transactionId,
        completedAt: $completedAt
    );
    
    $projector = new PaymentWithdrawalProjector();
    $projector->onWithdrawalCompleted($event, $aggregateUuid);
    
    $transaction = PaymentTransaction::where('aggregate_uuid', $aggregateUuid)->first();
    
    expect($transaction->status)->toBe('completed');
    expect($transaction->transaction_id)->toBe($transactionId);
    expect($transaction->completed_at->toDateTimeString())->toBe($completedAt->toDateTimeString());
});

it('updates payment transaction on withdrawal failed', function () {
    $aggregateUuid = Str::uuid()->toString();
    $reason = 'Invalid bank account';
    $failedAt = now();
    
    // Create a pending transaction first
    PaymentTransaction::create([
        'aggregate_uuid' => $aggregateUuid,
        'account_uuid' => Str::uuid()->toString(),
        'type' => 'withdrawal',
        'status' => 'pending',
        'amount' => 5000,
        'currency' => 'USD',
        'reference' => 'WD-123',
        'initiated_at' => now()->subMinutes(5),
    ]);
    
    $event = new WithdrawalFailed(
        reason: $reason,
        failedAt: $failedAt
    );
    
    $projector = new PaymentWithdrawalProjector();
    $projector->onWithdrawalFailed($event, $aggregateUuid);
    
    $transaction = PaymentTransaction::where('aggregate_uuid', $aggregateUuid)->first();
    
    expect($transaction->status)->toBe('failed');
    expect($transaction->failed_reason)->toBe($reason);
    expect($transaction->failed_at->toDateTimeString())->toBe($failedAt->toDateTimeString());
});

it('handles concurrent withdrawals correctly', function () {
    $accountUuid = Str::uuid()->toString();
    
    // Create multiple withdrawals
    $withdrawals = [];
    for ($i = 0; $i < 5; $i++) {
        $aggregateUuid = Str::uuid()->toString();
        $event = new WithdrawalInitiated(
            accountUuid: $accountUuid,
            amount: 1000 + ($i * 100),
            currency: 'USD',
            reference: 'WD-' . ($i + 1),
            bankAccountNumber: '****1234',
            bankRoutingNumber: '123456789',
            bankAccountName: 'John Doe',
            metadata: []
        );
        
        $projector = new PaymentWithdrawalProjector();
        $projector->onWithdrawalInitiated($event, $aggregateUuid);
        
        $withdrawals[] = $aggregateUuid;
    }
    
    // Verify all withdrawals were created
    $transactions = PaymentTransaction::where('account_uuid', $accountUuid)
        ->where('type', 'withdrawal')
        ->get();
    
    expect($transactions)->toHaveCount(5);
    
    // Complete some withdrawals
    foreach (array_slice($withdrawals, 0, 3) as $aggregateUuid) {
        $event = new WithdrawalCompleted(
            transactionId: 'wtxn_' . uniqid(),
            completedAt: now()
        );
        
        $projector = new PaymentWithdrawalProjector();
        $projector->onWithdrawalCompleted($event, $aggregateUuid);
    }
    
    // Fail one withdrawal
    $event = new WithdrawalFailed(
        reason: 'Test failure',
        failedAt: now()
    );
    
    $projector = new PaymentWithdrawalProjector();
    $projector->onWithdrawalFailed($event, $withdrawals[3]);
    
    // Verify statuses
    $completed = PaymentTransaction::where('account_uuid', $accountUuid)
        ->where('status', 'completed')
        ->count();
    
    $failed = PaymentTransaction::where('account_uuid', $accountUuid)
        ->where('status', 'failed')
        ->count();
    
    $pending = PaymentTransaction::where('account_uuid', $accountUuid)
        ->where('status', 'pending')
        ->count();
    
    expect($completed)->toBe(3);
    expect($failed)->toBe(1);
    expect($pending)->toBe(1);
});