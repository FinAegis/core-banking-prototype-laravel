<?php

declare(strict_types=1);

use App\Domain\X402\Services\X402EIP712SignerService;
use App\Domain\X402\Services\X402SignerFactory;
use App\Domain\X402\Services\X402SolanaSignerService;

uses(Tests\TestCase::class);

describe('X402SignerFactory', function (): void {
    it('returns EVM signer for EVM networks', function (): void {
        $factory = new X402SignerFactory(
            evmSigner: new X402EIP712SignerService(signerKeyId: 'test'),
            solanaSigner: new X402SolanaSignerService(signerKeyId: 'test'),
        );

        expect($factory->forNetwork('eip155:8453'))->toBeInstanceOf(X402EIP712SignerService::class);
        expect($factory->forNetwork('eip155:1'))->toBeInstanceOf(X402EIP712SignerService::class);
        expect($factory->forNetwork('eip155:84532'))->toBeInstanceOf(X402EIP712SignerService::class);
    });

    it('returns Solana signer for Solana networks', function (): void {
        $factory = new X402SignerFactory(
            evmSigner: new X402EIP712SignerService(signerKeyId: 'test'),
            solanaSigner: new X402SolanaSignerService(signerKeyId: 'test'),
        );

        expect($factory->forNetwork('solana:mainnet'))->toBeInstanceOf(X402SolanaSignerService::class);
        expect($factory->forNetwork('solana:devnet'))->toBeInstanceOf(X402SolanaSignerService::class);
    });

    it('returns default signer based on config', function (): void {
        config(['x402.server.default_network' => 'eip155:8453']);

        $factory = new X402SignerFactory(
            evmSigner: new X402EIP712SignerService(signerKeyId: 'test'),
            solanaSigner: new X402SolanaSignerService(signerKeyId: 'test'),
        );

        expect($factory->default())->toBeInstanceOf(X402EIP712SignerService::class);
    });

    it('returns Solana signer when default is Solana', function (): void {
        config(['x402.server.default_network' => 'solana:mainnet']);

        $factory = new X402SignerFactory(
            evmSigner: new X402EIP712SignerService(signerKeyId: 'test'),
            solanaSigner: new X402SolanaSignerService(signerKeyId: 'test'),
        );

        expect($factory->default())->toBeInstanceOf(X402SolanaSignerService::class);
    });

    it('identifies Solana networks correctly', function (): void {
        expect(X402SignerFactory::isSolanaNetwork('solana:mainnet'))->toBeTrue();
        expect(X402SignerFactory::isSolanaNetwork('solana:devnet'))->toBeTrue();
        expect(X402SignerFactory::isSolanaNetwork('eip155:8453'))->toBeFalse();
    });

    it('identifies EVM networks correctly', function (): void {
        expect(X402SignerFactory::isEvmNetwork('eip155:8453'))->toBeTrue();
        expect(X402SignerFactory::isEvmNetwork('eip155:1'))->toBeTrue();
        expect(X402SignerFactory::isEvmNetwork('solana:mainnet'))->toBeFalse();
    });
});
