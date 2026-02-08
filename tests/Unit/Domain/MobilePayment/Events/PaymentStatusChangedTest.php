<?php

declare(strict_types=1);

use App\Domain\MobilePayment\Enums\PaymentIntentStatus;
use App\Domain\MobilePayment\Events\Broadcast\PaymentStatusChanged;
use App\Domain\MobilePayment\Models\PaymentIntent;
use Tests\UnitTestCase;

uses(UnitTestCase::class);

beforeEach(function (): void {
    // Prevent Spatie EventSourcing EventSubscriber from being resolved
    Illuminate\Support\Facades\Event::fake();
});

describe('PaymentStatusChanged Broadcast Event', function (): void {
    it('broadcasts on private payments channel', function (): void {
        $intent = new PaymentIntent();
        $intent->user_id = 42;
        $intent->public_id = 'pi_test123';
        $intent->status = PaymentIntentStatus::PENDING;

        $event = new PaymentStatusChanged($intent);

        $channels = $event->broadcastOn();
        expect($channels)->toHaveCount(1);
        expect($channels[0]->name)->toBe('private-payments.42');
    });

    it('broadcasts as payment.status_changed', function (): void {
        $intent = new PaymentIntent();
        $intent->public_id = 'pi_test123';
        $intent->status = PaymentIntentStatus::PENDING;

        $event = new PaymentStatusChanged($intent);

        expect($event->broadcastAs())->toBe('payment.status_changed');
    });

    it('includes intent data in broadcast payload', function (): void {
        $intent = new PaymentIntent();
        $intent->public_id = 'pi_test456';
        $intent->status = PaymentIntentStatus::PENDING;
        $intent->tx_hash = 'abc123';
        $intent->tx_explorer_url = 'https://solscan.io/tx/abc123';
        $intent->confirmations = 5;
        $intent->required_confirmations = 32;

        $event = new PaymentStatusChanged($intent);
        $data = $event->broadcastWith();

        expect($data['intentId'])->toBe('pi_test456');
        expect($data['status'])->toBe('PENDING');
        expect($data['tx']['hash'])->toBe('abc123');
        expect($data['confirmations'])->toBe(5);
        expect($data['requiredConfirmations'])->toBe(32);
        expect($data['error'])->toBeNull();
    });

    it('includes error data when present', function (): void {
        $intent = new PaymentIntent();
        $intent->public_id = 'pi_test789';
        $intent->status = PaymentIntentStatus::FAILED;
        $intent->error_code = 'INSUFFICIENT_FUNDS';
        $intent->error_message = 'Not enough USDC.';

        $event = new PaymentStatusChanged($intent);
        $data = $event->broadcastWith();

        expect($data['status'])->toBe('FAILED');
        expect($data['error']['code'])->toBe('INSUFFICIENT_FUNDS');
        expect($data['error']['message'])->toBe('Not enough USDC.');
    });

    it('omits tx data when no hash present', function (): void {
        $intent = new PaymentIntent();
        $intent->public_id = 'pi_test';
        $intent->status = PaymentIntentStatus::AWAITING_AUTH;

        $event = new PaymentStatusChanged($intent);
        $data = $event->broadcastWith();

        expect($data)->not->toHaveKey('tx');
        expect($data)->not->toHaveKey('confirmations');
    });
});
