<?php

use App\Domain\Payment\Aggregates\PaymentDepositAggregate;
use App\Domain\Payment\DataObjects\StripeDeposit;
use App\Domain\Payment\Events\DepositInitiated;
use App\Domain\Payment\Events\DepositCompleted;
use App\Domain\Payment\Events\DepositFailed;
use Illuminate\Support\Str;

it('can initiate a deposit', function () {
    $depositUuid = Str::uuid()->toString();
    $accountUuid = Str::uuid()->toString();
    
    $deposit = new StripeDeposit(
        accountUuid: $accountUuid,
        amount: 10000,
        currency: 'USD',
        reference: 'TEST-' . uniqid(),
        externalReference: 'pi_test_' . uniqid(),
        paymentMethod: 'card',
        paymentMethodType: 'visa',
        metadata: ['test' => true]
    );
    
    $aggregate = PaymentDepositAggregate::fake($depositUuid)
        ->given([])
        ->when(function (PaymentDepositAggregate $aggregate) use ($deposit) {
            $aggregate->initiateDeposit($deposit);
        })
        ->assertRecorded([
            new DepositInitiated(
                accountUuid: $accountUuid,
                amount: 10000,
                currency: 'USD',
                reference: $deposit->getReference(),
                externalReference: $deposit->getExternalReference(),
                paymentMethod: 'card',
                paymentMethodType: 'visa',
                metadata: ['test' => true]
            )
        ]);
});

it('can complete a deposit', function () {
    $depositUuid = Str::uuid()->toString();
    $accountUuid = Str::uuid()->toString();
    $transactionId = 'txn_' . uniqid();
    
    $aggregate = PaymentDepositAggregate::fake($depositUuid)
        ->given([
            new DepositInitiated(
                accountUuid: $accountUuid,
                amount: 10000,
                currency: 'USD',
                reference: 'TEST-123',
                externalReference: 'pi_test_123',
                paymentMethod: 'card',
                paymentMethodType: 'visa',
                metadata: []
            )
        ])
        ->when(function (PaymentDepositAggregate $aggregate) use ($transactionId) {
            $aggregate->completeDeposit($transactionId);
        })
        ->assertRecorded(function (DepositCompleted $event) use ($transactionId) {
            expect($event->transactionId)->toBe($transactionId);
            expect($event->completedAt)->toBeInstanceOf(Carbon\Carbon::class);
        });
});

it('can fail a deposit', function () {
    $depositUuid = Str::uuid()->toString();
    $accountUuid = Str::uuid()->toString();
    $reason = 'Insufficient funds';
    
    $aggregate = PaymentDepositAggregate::fake($depositUuid)
        ->given([
            new DepositInitiated(
                accountUuid: $accountUuid,
                amount: 10000,
                currency: 'USD',
                reference: 'TEST-123',
                externalReference: 'pi_test_123',
                paymentMethod: 'card',
                paymentMethodType: 'visa',
                metadata: []
            )
        ])
        ->when(function (PaymentDepositAggregate $aggregate) use ($reason) {
            $aggregate->failDeposit($reason);
        })
        ->assertRecorded(function (DepositFailed $event) use ($reason) {
            expect($event->reason)->toBe($reason);
            expect($event->failedAt)->toBeInstanceOf(Carbon\Carbon::class);
        });
});

it('cannot complete a deposit that is not pending', function () {
    $depositUuid = Str::uuid()->toString();
    $accountUuid = Str::uuid()->toString();
    
    expect(function () use ($depositUuid, $accountUuid) {
        PaymentDepositAggregate::fake($depositUuid)
            ->given([
                new DepositInitiated(
                    accountUuid: $accountUuid,
                    amount: 10000,
                    currency: 'USD',
                    reference: 'TEST-123',
                    externalReference: 'pi_test_123',
                    paymentMethod: 'card',
                    paymentMethodType: 'visa',
                    metadata: []
                ),
                new DepositCompleted(
                    transactionId: 'txn_123',
                    completedAt: now()
                )
            ])
            ->when(function (PaymentDepositAggregate $aggregate) {
                $aggregate->completeDeposit('txn_456');
            });
    })->toThrow(Exception::class, 'Cannot complete deposit that is not pending');
});

it('cannot fail a deposit that is not pending', function () {
    $depositUuid = Str::uuid()->toString();
    $accountUuid = Str::uuid()->toString();
    
    expect(function () use ($depositUuid, $accountUuid) {
        PaymentDepositAggregate::fake($depositUuid)
            ->given([
                new DepositInitiated(
                    accountUuid: $accountUuid,
                    amount: 10000,
                    currency: 'USD',
                    reference: 'TEST-123',
                    externalReference: 'pi_test_123',
                    paymentMethod: 'card',
                    paymentMethodType: 'visa',
                    metadata: []
                ),
                new DepositFailed(
                    reason: 'Card declined',
                    failedAt: now()
                )
            ])
            ->when(function (PaymentDepositAggregate $aggregate) {
                $aggregate->failDeposit('Another reason');
            });
    })->toThrow(Exception::class, 'Cannot fail deposit that is not pending');
});