<?php

declare(strict_types=1);

use App\Domain\MachinePay\Enums\ChallengeStatus;
use App\Domain\MachinePay\Enums\MppSettlementStatus;
use App\Domain\MachinePay\Enums\PaymentRail;
use App\Domain\MachinePay\Enums\TransportBinding;

describe('MachinePay Enums', function (): void {
    it('has all payment rail cases', function (): void {
        expect(PaymentRail::cases())->toHaveCount(5);
        expect(PaymentRail::STRIPE_SPT->value)->toBe('stripe');
        expect(PaymentRail::TEMPO->value)->toBe('tempo');
        expect(PaymentRail::LIGHTNING->value)->toBe('lightning');
        expect(PaymentRail::CARD->value)->toBe('card');
        expect(PaymentRail::X402_USDC->value)->toBe('x402');
    });

    it('returns correct labels', function (): void {
        expect(PaymentRail::STRIPE_SPT->label())->toBe('Stripe Payment Token');
        expect(PaymentRail::TEMPO->label())->toBe('Tempo Stablecoin');
        expect(PaymentRail::LIGHTNING->label())->toBe('Lightning Network');
        expect(PaymentRail::CARD->label())->toBe('Card Network');
        expect(PaymentRail::X402_USDC->label())->toBe('x402 USDC (Coinbase)');
    });

    it('correctly identifies fiat and crypto support', function (): void {
        expect(PaymentRail::STRIPE_SPT->supportsFiat())->toBeTrue();
        expect(PaymentRail::STRIPE_SPT->supportsCrypto())->toBeFalse();
        expect(PaymentRail::TEMPO->supportsFiat())->toBeFalse();
        expect(PaymentRail::TEMPO->supportsCrypto())->toBeTrue();
        expect(PaymentRail::LIGHTNING->supportsCrypto())->toBeTrue();
        expect(PaymentRail::CARD->supportsFiat())->toBeTrue();
        expect(PaymentRail::X402_USDC->supportsCrypto())->toBeTrue();
        expect(PaymentRail::X402_USDC->supportsFiat())->toBeFalse();
    });

    it('returns default currencies per rail', function (): void {
        expect(PaymentRail::STRIPE_SPT->defaultCurrencies())->toContain('USD');
        expect(PaymentRail::TEMPO->defaultCurrencies())->toContain('USDC');
        expect(PaymentRail::LIGHTNING->defaultCurrencies())->toBe(['BTC']);
    });

    it('has correct challenge statuses', function (): void {
        expect(ChallengeStatus::cases())->toHaveCount(6);
        expect(ChallengeStatus::SETTLED->isTerminal())->toBeTrue();
        expect(ChallengeStatus::EXPIRED->isTerminal())->toBeTrue();
        expect(ChallengeStatus::ISSUED->isTerminal())->toBeFalse();
    });

    it('has correct settlement statuses', function (): void {
        expect(MppSettlementStatus::cases())->toHaveCount(4);
        expect(MppSettlementStatus::SETTLED->isTerminal())->toBeTrue();
        expect(MppSettlementStatus::FAILED->isTerminal())->toBeTrue();
        expect(MppSettlementStatus::PENDING->isTerminal())->toBeFalse();
    });

    it('has correct transport bindings', function (): void {
        expect(TransportBinding::HTTP->paymentRequiredCode())->toBe(402);
        expect(TransportBinding::MCP->paymentRequiredCode())->toBe(-32042);
    });
});
