<?php

declare(strict_types=1);

use App\Domain\MobilePayment\Enums\PaymentAsset;
use App\Domain\MobilePayment\Enums\PaymentIntentStatus;
use App\Domain\MobilePayment\Enums\PaymentNetwork;
use App\Domain\MobilePayment\Exceptions\InvalidStateTransitionException;
use App\Domain\MobilePayment\Models\PaymentIntent;
use Tests\UnitTestCase;

uses(UnitTestCase::class);

beforeEach(function (): void {
    // Prevent Spatie EventSourcing EventSubscriber from being resolved
    Illuminate\Support\Facades\Event::fake();
});

describe('PaymentIntent Model', function (): void {
    it('casts status to enum', function (): void {
        $intent = new PaymentIntent();
        $intent->status = PaymentIntentStatus::CREATED;

        expect($intent->status)->toBe(PaymentIntentStatus::CREATED);
        expect($intent->status)->toBeInstanceOf(PaymentIntentStatus::class);
    });

    it('casts shield_enabled to boolean', function (): void {
        $intent = new PaymentIntent();
        $intent->shield_enabled = true;

        expect($intent->shield_enabled)->toBeTrue();
    });

    it('casts fees_estimate to array', function (): void {
        $intent = new PaymentIntent();
        $intent->fees_estimate = ['nativeAsset' => 'SOL', 'amount' => '0.00004'];

        expect($intent->fees_estimate)->toBeArray();
        expect($intent->fees_estimate['nativeAsset'])->toBe('SOL');
    });

    it('transitions from CREATED to AWAITING_AUTH', function (): void {
        $mock = Mockery::mock(PaymentIntent::class)->makePartial();
        $mock->status = PaymentIntentStatus::CREATED;
        $mock->shouldReceive('save')->andReturnTrue();

        $mock->transitionTo(PaymentIntentStatus::AWAITING_AUTH);
        expect($mock->status)->toBe(PaymentIntentStatus::AWAITING_AUTH);
    });

    it('transitions from SUBMITTING to PENDING', function (): void {
        $mock = Mockery::mock(PaymentIntent::class)->makePartial();
        $mock->status = PaymentIntentStatus::SUBMITTING;
        $mock->shouldReceive('save')->andReturnTrue();

        $mock->transitionTo(PaymentIntentStatus::PENDING);
        expect($mock->status)->toBe(PaymentIntentStatus::PENDING);
    });

    it('throws on invalid state transition from CONFIRMED', function (): void {
        $mock = Mockery::mock(PaymentIntent::class)->makePartial();
        $mock->status = PaymentIntentStatus::CONFIRMED;

        $mock->transitionTo(PaymentIntentStatus::PENDING);
    })->throws(InvalidStateTransitionException::class);

    it('throws on invalid state transition - cannot cancel SUBMITTING', function (): void {
        $mock = Mockery::mock(PaymentIntent::class)->makePartial();
        $mock->status = PaymentIntentStatus::SUBMITTING;

        $mock->transitionTo(PaymentIntentStatus::CANCELLED);
    })->throws(InvalidStateTransitionException::class);

    it('throws on invalid state transition - cannot cancel PENDING', function (): void {
        $mock = Mockery::mock(PaymentIntent::class)->makePartial();
        $mock->status = PaymentIntentStatus::PENDING;

        $mock->transitionTo(PaymentIntentStatus::CANCELLED);
    })->throws(InvalidStateTransitionException::class);

    it('throws on invalid transition - cannot skip to CONFIRMED from CREATED', function (): void {
        $mock = Mockery::mock(PaymentIntent::class)->makePartial();
        $mock->status = PaymentIntentStatus::CREATED;

        $mock->transitionTo(PaymentIntentStatus::CONFIRMED);
    })->throws(InvalidStateTransitionException::class);

    it('resolves network enum', function (): void {
        $intent = new PaymentIntent();
        $intent->network = 'SOLANA';

        expect($intent->getNetworkEnum())->toBe(PaymentNetwork::SOLANA);
    });

    it('resolves asset enum', function (): void {
        $intent = new PaymentIntent();
        $intent->asset = 'USDC';

        expect($intent->getAssetEnum())->toBe(PaymentAsset::USDC);
    });

    it('has correct fillable attributes', function (): void {
        $intent = new PaymentIntent();

        expect($intent->getFillable())->toContain('public_id');
        expect($intent->getFillable())->toContain('user_id');
        expect($intent->getFillable())->toContain('merchant_id');
        expect($intent->getFillable())->toContain('asset');
        expect($intent->getFillable())->toContain('network');
        expect($intent->getFillable())->toContain('amount');
        expect($intent->getFillable())->toContain('status');
        expect($intent->getFillable())->toContain('shield_enabled');
        expect($intent->getFillable())->toContain('idempotency_key');
        // Sensitive fields should NOT be mass-assignable
        expect($intent->getFillable())->not->toContain('tx_hash');
        expect($intent->getFillable())->not->toContain('error_code');
        expect($intent->getFillable())->not->toContain('confirmed_at');
    });

    it('uses UUID primary key', function (): void {
        $intent = new PaymentIntent();

        expect($intent->getKeyType())->toBe('string');
        expect($intent->getIncrementing())->toBeFalse();
    });

    it('has correct table name', function (): void {
        $intent = new PaymentIntent();

        expect($intent->getTable())->toBe('payment_intents');
    });

    it('has correct casts configured', function (): void {
        $intent = new PaymentIntent();
        $casts = $intent->getCasts();

        expect($casts['status'])->toBe(PaymentIntentStatus::class);
        expect($casts['shield_enabled'])->toBe('boolean');
        expect($casts['fees_estimate'])->toBe('array');
        expect($casts['metadata'])->toBe('array');
        expect($casts['confirmations'])->toBe('integer');
        expect($casts['required_confirmations'])->toBe('integer');
    });
});
