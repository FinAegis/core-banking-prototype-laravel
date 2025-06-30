<?php

use App\Domain\Payment\Events\DepositInitiated;
use App\Domain\Payment\Events\DepositCompleted;
use App\Domain\Payment\Events\DepositFailed;
use App\Domain\Payment\Events\WithdrawalInitiated;
use App\Domain\Payment\Events\WithdrawalCompleted;
use App\Domain\Payment\Events\WithdrawalFailed;
use App\Values\EventQueues;
use Carbon\Carbon;
use Illuminate\Support\Str;

describe('Deposit Events', function () {
    it('creates DepositInitiated event correctly', function () {
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
        
        expect($event->accountUuid)->toBe($accountUuid);
        expect($event->amount)->toBe(10000);
        expect($event->currency)->toBe('USD');
        expect($event->reference)->toBe('TEST-123');
        expect($event->externalReference)->toBe('pi_test_123');
        expect($event->paymentMethod)->toBe('card');
        expect($event->paymentMethodType)->toBe('visa');
        expect($event->metadata)->toBe(['test' => true]);
        expect($event->queue)->toBe(EventQueues::TRANSACTIONS->value);
    });
    
    it('creates DepositCompleted event correctly', function () {
        $transactionId = 'txn_123';
        $completedAt = now();
        
        $event = new DepositCompleted(
            transactionId: $transactionId,
            completedAt: $completedAt
        );
        
        expect($event->transactionId)->toBe($transactionId);
        expect($event->completedAt)->toBeInstanceOf(Carbon::class);
        expect($event->completedAt->toDateTimeString())->toBe($completedAt->toDateTimeString());
        expect($event->queue)->toBe(EventQueues::TRANSACTIONS->value);
    });
    
    it('creates DepositFailed event correctly', function () {
        $reason = 'Card declined';
        $failedAt = now();
        
        $event = new DepositFailed(
            reason: $reason,
            failedAt: $failedAt
        );
        
        expect($event->reason)->toBe($reason);
        expect($event->failedAt)->toBeInstanceOf(Carbon::class);
        expect($event->failedAt->toDateTimeString())->toBe($failedAt->toDateTimeString());
        expect($event->queue)->toBe(EventQueues::TRANSACTIONS->value);
    });
});

describe('Withdrawal Events', function () {
    it('creates WithdrawalInitiated event correctly', function () {
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
        
        expect($event->accountUuid)->toBe($accountUuid);
        expect($event->amount)->toBe(5000);
        expect($event->currency)->toBe('USD');
        expect($event->reference)->toBe('WD-123');
        expect($event->bankAccountNumber)->toBe('****1234');
        expect($event->bankRoutingNumber)->toBe('123456789');
        expect($event->bankAccountName)->toBe('John Doe');
        expect($event->metadata)->toBe(['test' => true]);
        expect($event->queue)->toBe(EventQueues::TRANSACTIONS->value);
    });
    
    it('creates WithdrawalCompleted event correctly', function () {
        $transactionId = 'wtxn_123';
        $completedAt = now();
        
        $event = new WithdrawalCompleted(
            transactionId: $transactionId,
            completedAt: $completedAt
        );
        
        expect($event->transactionId)->toBe($transactionId);
        expect($event->completedAt)->toBeInstanceOf(Carbon::class);
        expect($event->completedAt->toDateTimeString())->toBe($completedAt->toDateTimeString());
        expect($event->queue)->toBe(EventQueues::TRANSACTIONS->value);
    });
    
    it('creates WithdrawalFailed event correctly', function () {
        $reason = 'Invalid bank account';
        $failedAt = now();
        
        $event = new WithdrawalFailed(
            reason: $reason,
            failedAt: $failedAt
        );
        
        expect($event->reason)->toBe($reason);
        expect($event->failedAt)->toBeInstanceOf(Carbon::class);
        expect($event->failedAt->toDateTimeString())->toBe($failedAt->toDateTimeString());
        expect($event->queue)->toBe(EventQueues::TRANSACTIONS->value);
    });
});

describe('Event Registration', function () {
    it('events are registered in event class map', function () {
        $config = config('event-sourcing.event_class_map');
        
        expect($config)->toHaveKey('deposit_initiated', DepositInitiated::class);
        expect($config)->toHaveKey('deposit_completed', DepositCompleted::class);
        expect($config)->toHaveKey('deposit_failed', DepositFailed::class);
        expect($config)->toHaveKey('withdrawal_initiated', WithdrawalInitiated::class);
        expect($config)->toHaveKey('withdrawal_completed', WithdrawalCompleted::class);
        expect($config)->toHaveKey('withdrawal_failed', WithdrawalFailed::class);
    });
});