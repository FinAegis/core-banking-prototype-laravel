<?php

use App\Domain\Payment\Aggregates\PaymentWithdrawalAggregate;
use App\Domain\Payment\DataObjects\BankWithdrawal;
use App\Domain\Payment\Events\WithdrawalInitiated;
use App\Domain\Payment\Events\WithdrawalCompleted;
use App\Domain\Payment\Events\WithdrawalFailed;
use Illuminate\Support\Str;

it('can initiate a withdrawal', function () {
    $withdrawalUuid = Str::uuid()->toString();
    $accountUuid = Str::uuid()->toString();
    
    $withdrawal = new BankWithdrawal(
        accountUuid: $accountUuid,
        amount: 5000,
        currency: 'USD',
        reference: 'WD-' . uniqid(),
        bankName: 'Test Bank',
        accountNumber: '****1234',
        accountHolderName: 'John Doe',
        routingNumber: '123456789',
        metadata: ['test' => true]
    );
    
    $aggregate = PaymentWithdrawalAggregate::fake($withdrawalUuid)
        ->given([])
        ->when(function (PaymentWithdrawalAggregate $aggregate) use ($withdrawal) {
            $aggregate->initiateWithdrawal($withdrawal);
        })
        ->assertRecorded([
            new WithdrawalInitiated(
                accountUuid: $accountUuid,
                amount: 5000,
                currency: 'USD',
                reference: $withdrawal->getReference(),
                bankAccountNumber: '****1234',
                bankRoutingNumber: '123456789',
                bankAccountName: 'John Doe',
                metadata: ['test' => true]
            )
        ]);
});

it('can complete a withdrawal', function () {
    $withdrawalUuid = Str::uuid()->toString();
    $accountUuid = Str::uuid()->toString();
    $transactionId = 'wtxn_' . uniqid();
    
    $aggregate = PaymentWithdrawalAggregate::fake($withdrawalUuid)
        ->given([
            new WithdrawalInitiated(
                accountUuid: $accountUuid,
                amount: 5000,
                currency: 'USD',
                reference: 'WD-123',
                bankAccountNumber: '****1234',
                bankRoutingNumber: '123456789',
                bankAccountName: 'John Doe',
                metadata: []
            )
        ])
        ->when(function (PaymentWithdrawalAggregate $aggregate) use ($transactionId) {
            $aggregate->completeWithdrawal($transactionId);
        })
        ->assertRecorded(function (WithdrawalCompleted $event) use ($transactionId) {
            expect($event->transactionId)->toBe($transactionId);
            expect($event->completedAt)->toBeInstanceOf(Carbon\Carbon::class);
        });
});

it('can fail a withdrawal', function () {
    $withdrawalUuid = Str::uuid()->toString();
    $accountUuid = Str::uuid()->toString();
    $reason = 'Bank account verification failed';
    
    $aggregate = PaymentWithdrawalAggregate::fake($withdrawalUuid)
        ->given([
            new WithdrawalInitiated(
                accountUuid: $accountUuid,
                amount: 5000,
                currency: 'USD',
                reference: 'WD-123',
                bankAccountNumber: '****1234',
                bankRoutingNumber: '123456789',
                bankAccountName: 'John Doe',
                metadata: []
            )
        ])
        ->when(function (PaymentWithdrawalAggregate $aggregate) use ($reason) {
            $aggregate->failWithdrawal($reason);
        })
        ->assertRecorded(function (WithdrawalFailed $event) use ($reason) {
            expect($event->reason)->toBe($reason);
            expect($event->failedAt)->toBeInstanceOf(Carbon\Carbon::class);
        });
});

it('cannot complete a withdrawal that is not pending', function () {
    $withdrawalUuid = Str::uuid()->toString();
    $accountUuid = Str::uuid()->toString();
    
    expect(function () use ($withdrawalUuid, $accountUuid) {
        PaymentWithdrawalAggregate::fake($withdrawalUuid)
            ->given([
                new WithdrawalInitiated(
                    accountUuid: $accountUuid,
                    amount: 5000,
                    currency: 'USD',
                    reference: 'WD-123',
                    bankAccountNumber: '****1234',
                    bankRoutingNumber: '123456789',
                    bankAccountName: 'John Doe',
                    metadata: []
                ),
                new WithdrawalCompleted(
                    transactionId: 'wtxn_123',
                    completedAt: now()
                )
            ])
            ->when(function (PaymentWithdrawalAggregate $aggregate) {
                $aggregate->completeWithdrawal('wtxn_456');
            });
    })->toThrow(Exception::class, 'Cannot complete withdrawal that is not pending');
});

it('cannot fail a withdrawal that is not pending', function () {
    $withdrawalUuid = Str::uuid()->toString();
    $accountUuid = Str::uuid()->toString();
    
    expect(function () use ($withdrawalUuid, $accountUuid) {
        PaymentWithdrawalAggregate::fake($withdrawalUuid)
            ->given([
                new WithdrawalInitiated(
                    accountUuid: $accountUuid,
                    amount: 5000,
                    currency: 'USD',
                    reference: 'WD-123',
                    bankAccountNumber: '****1234',
                    bankRoutingNumber: '123456789',
                    bankAccountName: 'John Doe',
                    metadata: []
                ),
                new WithdrawalFailed(
                    reason: 'Invalid bank account',
                    failedAt: now()
                )
            ])
            ->when(function (PaymentWithdrawalAggregate $aggregate) {
                $aggregate->failWithdrawal('Another reason');
            });
    })->toThrow(Exception::class, 'Cannot fail withdrawal that is not pending');
});