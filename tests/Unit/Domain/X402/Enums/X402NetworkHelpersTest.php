<?php

declare(strict_types=1);

use App\Domain\X402\Enums\X402Network;

describe('X402Network helper methods', function (): void {
    it('identifies Solana networks', function (): void {
        expect(X402Network::SOLANA_MAINNET->isSolana())->toBeTrue();
        expect(X402Network::SOLANA_DEVNET->isSolana())->toBeTrue();
        expect(X402Network::BASE_MAINNET->isSolana())->toBeFalse();
        expect(X402Network::ETHEREUM_MAINNET->isSolana())->toBeFalse();
    });

    it('identifies EVM networks', function (): void {
        expect(X402Network::BASE_MAINNET->isEvm())->toBeTrue();
        expect(X402Network::ETHEREUM_MAINNET->isEvm())->toBeTrue();
        expect(X402Network::AVALANCHE->isEvm())->toBeTrue();
        expect(X402Network::SOLANA_MAINNET->isEvm())->toBeFalse();
        expect(X402Network::SOLANA_DEVNET->isEvm())->toBeFalse();
    });

    it('returns correct signing scheme for EVM networks', function (): void {
        expect(X402Network::BASE_MAINNET->signingScheme())->toBe('eip712');
        expect(X402Network::ETHEREUM_MAINNET->signingScheme())->toBe('eip712');
        expect(X402Network::BASE_SEPOLIA->signingScheme())->toBe('eip712');
    });

    it('returns correct signing scheme for Solana networks', function (): void {
        expect(X402Network::SOLANA_MAINNET->signingScheme())->toBe('ed25519');
        expect(X402Network::SOLANA_DEVNET->signingScheme())->toBe('ed25519');
    });
});
