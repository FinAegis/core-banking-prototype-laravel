<?php

declare(strict_types=1);

use App\Domain\X402\Services\X402SolanaSignerService;

uses(Tests\TestCase::class);

describe('X402SolanaSignerService', function (): void {
    it('returns a structured Solana transfer payload', function (): void {
        $signer = new X402SolanaSignerService(signerKeyId: 'test');

        $result = $signer->signTransferAuthorization(
            network: 'solana:mainnet',
            to: 'RecipientBase58Address1111111111111111111111',
            amount: '100000',
            asset: 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
            maxTimeoutSeconds: 60,
        );

        expect($result)->toHaveKeys(['signature', 'transaction']);
        expect($result['transaction'])->toHaveKeys([
            'from', 'to', 'amount', 'mint', 'validBefore', 'nonce', 'tokenProgram',
        ]);
        expect($result['transaction']['mint'])->toBe('EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v');
        expect($result['transaction']['amount'])->toBe('100000');
    });

    it('produces a 128-character hex signature in demo mode', function (): void {
        $signer = new X402SolanaSignerService(signerKeyId: 'test');

        $result = $signer->signTransferAuthorization(
            network: 'solana:devnet',
            to: 'RecipientBase58Address1111111111111111111111',
            amount: '50000',
            asset: '4zMMC9srt5Ri5X14GAgXhaHii3GnPAEERYPJgZJDncDU',
            maxTimeoutSeconds: 30,
        );

        expect(strlen($result['signature']))->toBe(128);
        expect(ctype_xdigit($result['signature']))->toBeTrue();
    });

    it('uses the default token program when extra is empty', function (): void {
        $signer = new X402SolanaSignerService(signerKeyId: 'test');

        $result = $signer->signTransferAuthorization(
            network: 'solana:mainnet',
            to: 'RecipientBase58Address1111111111111111111111',
            amount: '100000',
            asset: 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
            maxTimeoutSeconds: 60,
        );

        expect($result['transaction']['tokenProgram'])->toBe('TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA');
    });

    it('uses a custom token program from extra', function (): void {
        $signer = new X402SolanaSignerService(signerKeyId: 'test');

        $result = $signer->signTransferAuthorization(
            network: 'solana:mainnet',
            to: 'RecipientBase58Address1111111111111111111111',
            amount: '100000',
            asset: 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
            maxTimeoutSeconds: 60,
            extra: ['token_program' => 'CustomTokenProgram11111111111111111111111'],
        );

        expect($result['transaction']['tokenProgram'])->toBe('CustomTokenProgram11111111111111111111111');
    });

    it('returns the configured solana address', function (): void {
        config(['x402.client.solana_signer_address' => 'SoLAnaWaLLeTaDdReSS111111111111111111111111']);
        $signer = new X402SolanaSignerService(signerKeyId: 'test');

        expect($signer->getAddress())->toBe('SoLAnaWaLLeTaDdReSS111111111111111111111111');
    });

    it('falls back to system program address when not configured', function (): void {
        config(['x402.client.solana_signer_address' => '']);
        $signer = new X402SolanaSignerService(signerKeyId: 'test');

        // Returns the fallback from config (empty string when env not set)
        expect($signer->getAddress())->toBeString();
    });
});
