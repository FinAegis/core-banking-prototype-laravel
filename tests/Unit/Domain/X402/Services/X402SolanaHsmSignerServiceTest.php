<?php

declare(strict_types=1);

use App\Domain\X402\Services\X402SolanaHsmSignerService;

uses(Tests\TestCase::class);

describe('X402SolanaHsmSignerService', function (): void {
    it('throws for unknown HSM provider', function (): void {
        $signer = new X402SolanaHsmSignerService(
            keyId: 'test',
            provider: 'unknown',
        );

        $signer->signTransferAuthorization(
            network: 'solana:mainnet',
            to: 'RecipientBase58Address1111111111111111111111',
            amount: '100000',
            asset: 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
            maxTimeoutSeconds: 60,
        );
    })->throws(RuntimeException::class, 'Unknown HSM provider: unknown');

    it('throws when sodium provider has no key file configured', function (): void {
        config(['x402.client.solana_key_path' => '']);

        $signer = new X402SolanaHsmSignerService(
            keyId: 'test',
            provider: 'sodium',
        );

        $signer->signTransferAuthorization(
            network: 'solana:mainnet',
            to: 'RecipientBase58Address1111111111111111111111',
            amount: '100000',
            asset: 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
            maxTimeoutSeconds: 60,
        );
    })->throws(RuntimeException::class, 'Solana keypair file not found');

    it('throws when sodium provider key file does not exist', function (): void {
        config(['x402.client.solana_key_path' => '/tmp/nonexistent-solana-keypair-test']);

        $signer = new X402SolanaHsmSignerService(
            keyId: 'test',
            provider: 'sodium',
        );

        $signer->signTransferAuthorization(
            network: 'solana:mainnet',
            to: 'RecipientBase58Address1111111111111111111111',
            amount: '100000',
            asset: 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
            maxTimeoutSeconds: 60,
        );
    })->throws(RuntimeException::class, 'Solana keypair file not found');

    it('throws for AWS provider (not yet implemented)', function (): void {
        $signer = new X402SolanaHsmSignerService(
            keyId: 'test',
            provider: 'aws',
        );

        $signer->signTransferAuthorization(
            network: 'solana:mainnet',
            to: 'RecipientBase58Address1111111111111111111111',
            amount: '100000',
            asset: 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
            maxTimeoutSeconds: 60,
        );
    })->throws(RuntimeException::class, 'AWS CloudHSM Solana signing not yet implemented');

    it('throws for Azure provider (not yet implemented)', function (): void {
        $signer = new X402SolanaHsmSignerService(
            keyId: 'test',
            provider: 'azure',
        );

        $signer->signTransferAuthorization(
            network: 'solana:mainnet',
            to: 'RecipientBase58Address1111111111111111111111',
            amount: '100000',
            asset: 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
            maxTimeoutSeconds: 60,
        );
    })->throws(RuntimeException::class, 'Azure Key Vault Solana signing not yet implemented');

    it('signs with sodium provider when valid keypair file exists', function (): void {
        // Generate an Ed25519 keypair
        $keypair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keypair);

        $tempFile = tempnam(sys_get_temp_dir(), 'solana-test-keypair');
        file_put_contents($tempFile, $secretKey);

        config(['x402.client.solana_key_path' => $tempFile]);

        $signer = new X402SolanaHsmSignerService(
            keyId: 'test',
            provider: 'sodium',
        );

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
        expect($result['signature'])->toBeString();
        // Ed25519 signature is 64 bytes = 128 hex chars
        expect(strlen($result['signature']))->toBe(128);
        expect(ctype_xdigit($result['signature']))->toBeTrue();
        expect($result['transaction']['amount'])->toBe('100000');
        expect($result['transaction']['to'])->toBe('RecipientBase58Address1111111111111111111111');

        @unlink($tempFile);
    });

    it('returns config address for non-sodium providers', function (): void {
        config(['x402.client.solana_signer_address' => 'ConfiguredAddress111111111111111']);

        $signer = new X402SolanaHsmSignerService(
            keyId: 'test',
            provider: 'aws',
        );

        expect($signer->getAddress())->toBe('ConfiguredAddress111111111111111');
    });

    it('returns config address for sodium provider when key file missing', function (): void {
        config([
            'x402.client.solana_key_path'       => '',
            'x402.client.solana_signer_address' => 'FallbackAddress111111111111111111',
        ]);

        $signer = new X402SolanaHsmSignerService(
            keyId: 'test',
            provider: 'sodium',
        );

        expect($signer->getAddress())->toBe('FallbackAddress111111111111111111');
    });

    it('derives public key from keypair file for sodium provider', function (): void {
        $keypair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $publicKey = sodium_crypto_sign_publickey($keypair);

        $tempFile = tempnam(sys_get_temp_dir(), 'solana-test-keypair');
        file_put_contents($tempFile, $secretKey);

        config(['x402.client.solana_key_path' => $tempFile]);

        $signer = new X402SolanaHsmSignerService(
            keyId: 'test',
            provider: 'sodium',
        );

        $address = $signer->getAddress();
        // The public key is extracted from bytes 32-64 and Base58-encoded
        expect($address)->toBeString();
        expect(strlen($address))->toBeGreaterThan(30)
            ->and(strlen($address))->toBeLessThan(50);
        // Base58 alphabet: no 0, O, I, l characters
        expect($address)->not->toMatch('/[0OIl]/');

        @unlink($tempFile);
    });

    it('throws when sodium keypair file is too short', function (): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'solana-test-short');
        file_put_contents($tempFile, 'short');

        config(['x402.client.solana_key_path' => $tempFile]);

        $signer = new X402SolanaHsmSignerService(
            keyId: 'test',
            provider: 'sodium',
        );

        try {
            $signer->signTransferAuthorization(
                network: 'solana:mainnet',
                to: 'RecipientBase58Address1111111111111111111111',
                amount: '100000',
                asset: 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
                maxTimeoutSeconds: 60,
            );
        } finally {
            @unlink($tempFile);
        }
    })->throws(RuntimeException::class, 'Invalid Solana keypair file format');
});
