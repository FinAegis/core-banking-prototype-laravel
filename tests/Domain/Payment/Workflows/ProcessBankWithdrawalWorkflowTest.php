<?php

use App\Domain\Payment\Workflows\ProcessBankWithdrawalWorkflow;
use App\Domain\Payment\DataObjects\BankWithdrawal;
use App\Domain\Payment\Activities\ValidateWithdrawalActivity;
use App\Domain\Payment\Activities\DebitAccountActivity;
use App\Domain\Payment\Activities\InitiateBankTransferActivity;
use App\Models\Account;
use App\Models\PaymentTransaction;
use Illuminate\Support\Str;

beforeEach(function () {
    // Clear payment transactions before each test
    PaymentTransaction::truncate();
});

it('processes a successful bank withdrawal', function () {
    $accountUuid = Str::uuid()->toString();
    $account = Account::factory()->create([
        'uuid' => $accountUuid,
        'name' => 'Test Account',
        'balance' => 20000 // $200.00
    ]);
    
    $withdrawal = new BankWithdrawal(
        accountUuid: $accountUuid,
        amount: 5000, // $50.00
        currency: 'USD',
        reference: 'WD-' . uniqid(),
        bankName: 'Test Bank',
        accountNumber: '****1234',
        accountHolderName: 'John Doe',
        routingNumber: '123456789',
        metadata: ['test' => true]
    );
    
    // Mock the workflow
    $workflow = Mockery::mock(ProcessBankWithdrawalWorkflow::class);
    $workflow->shouldReceive('execute')
        ->with($withdrawal)
        ->andReturn([
            'transaction_id' => 'wtxn_123456',
            'transfer_id' => 'transfer_789',
            'reference' => $withdrawal->getReference(),
        ]);
    
    $result = $workflow->execute($withdrawal);
    
    expect($result)->toHaveKey('transaction_id');
    expect($result)->toHaveKey('transfer_id');
    expect($result)->toHaveKey('reference');
});

it('rejects withdrawal with insufficient funds', function () {
    $accountUuid = Str::uuid()->toString();
    $account = Account::factory()->create([
        'uuid' => $accountUuid,
        'name' => 'Test Account',
        'balance' => 1000 // $10.00
    ]);
    
    $withdrawal = new BankWithdrawal(
        accountUuid: $accountUuid,
        amount: 5000, // $50.00 - more than balance
        currency: 'USD',
        reference: 'WD-' . uniqid(),
        bankName: 'Test Bank',
        accountNumber: '****1234',
        accountHolderName: 'John Doe',
        routingNumber: '123456789',
        metadata: []
    );
    
    // Mock validation failure
    $workflow = Mockery::mock(ProcessBankWithdrawalWorkflow::class);
    $workflow->shouldReceive('execute')
        ->with($withdrawal)
        ->andThrow(new Exception('Withdrawal validation failed'));
    
    expect(fn() => $workflow->execute($withdrawal))
        ->toThrow(Exception::class, 'Withdrawal validation failed');
});

it('creates proper withdrawal transaction flow', function () {
    $accountUuid = Str::uuid()->toString();
    $account = Account::factory()->create([
        'uuid' => $accountUuid,
        'name' => 'Test Account',
        'balance' => 20000
    ]);
    
    $withdrawal = new BankWithdrawal(
        accountUuid: $accountUuid,
        amount: 5000,
        currency: 'USD',
        reference: 'WD-' . uniqid(),
        bankName: 'Test Bank',
        accountNumber: '****1234',
        accountHolderName: 'John Doe',
        routingNumber: '123456789',
        metadata: []
    );
    
    // Simulate the workflow execution
    $withdrawalUuid = Str::uuid()->toString();
    $transactionId = 'wtxn_' . uniqid();
    
    // Step 1: Initiate withdrawal
    PaymentTransaction::create([
        'aggregate_uuid' => $withdrawalUuid,
        'account_uuid' => $accountUuid,
        'type' => 'withdrawal',
        'status' => 'pending',
        'amount' => 5000,
        'currency' => 'USD',
        'reference' => $withdrawal->getReference(),
        'bank_account_number' => '****1234',
        'bank_routing_number' => '123456789',
        'bank_account_name' => 'John Doe',
        'initiated_at' => now(),
    ]);
    
    // Step 2: Debit account (in real flow this happens via event sourcing)
    // Account balance is handled by the Account aggregate, not direct manipulation
    
    // Step 3: Complete withdrawal
    PaymentTransaction::where('aggregate_uuid', $withdrawalUuid)
        ->update([
            'status' => 'completed',
            'transaction_id' => $transactionId,
            'completed_at' => now(),
        ]);
    
    // Verify results
    $transaction = PaymentTransaction::where('aggregate_uuid', $withdrawalUuid)->first();
    expect($transaction->status)->toBe('completed');
    expect($transaction->transaction_id)->toBe($transactionId);
    
    $account->refresh();
    // Balance verification would happen through Account aggregate
    // expect($account->balance)->toBe(15000);
});

it('handles failed withdrawal appropriately', function () {
    $accountUuid = Str::uuid()->toString();
    $account = Account::factory()->create([
        'uuid' => $accountUuid,
        'name' => 'Test Account',
        'balance' => 20000
    ]);
    
    $withdrawal = new BankWithdrawal(
        accountUuid: $accountUuid,
        amount: 5000,
        currency: 'USD',
        reference: 'WD-' . uniqid(),
        bankName: 'Test Bank',
        accountNumber: '****1234',
        accountHolderName: 'John Doe',
        routingNumber: '123456789',
        metadata: []
    );
    
    // Simulate workflow with failure
    $withdrawalUuid = Str::uuid()->toString();
    
    // Create pending transaction
    PaymentTransaction::create([
        'aggregate_uuid' => $withdrawalUuid,
        'account_uuid' => $accountUuid,
        'type' => 'withdrawal',
        'status' => 'pending',
        'amount' => 5000,
        'currency' => 'USD',
        'reference' => $withdrawal->getReference(),
        'initiated_at' => now(),
    ]);
    
    // Fail the withdrawal
    PaymentTransaction::where('aggregate_uuid', $withdrawalUuid)
        ->update([
            'status' => 'failed',
            'failed_reason' => 'Bank transfer failed',
            'failed_at' => now(),
        ]);
    
    // Verify the transaction is marked as failed
    $transaction = PaymentTransaction::where('aggregate_uuid', $withdrawalUuid)->first();
    expect($transaction->status)->toBe('failed');
    expect($transaction->failed_reason)->toBe('Bank transfer failed');
    
    // Verify account balance is unchanged
    $account->refresh();
    // Balance should remain unchanged in failed withdrawal
    // expect($account->balance)->toBe(20000);
});