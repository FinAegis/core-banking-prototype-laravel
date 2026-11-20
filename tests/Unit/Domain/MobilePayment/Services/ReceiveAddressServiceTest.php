<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

use App\Domain\MobilePayment\Enums\PaymentAsset;
use App\Domain\MobilePayment\Enums\PaymentNetwork;
use App\Domain\MobilePayment\Services\ReceiveAddressService;

describe('ReceiveAddressService', function (): void {
    it('can be instantiated', function (): void {
        $service = new ReceiveAddressService();

        expect($service)->toBeInstanceOf(ReceiveAddressService::class);
    });

    it('has getReceiveAddress method with correct parameters', function (): void {
        $service = new ReceiveAddressService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getReceiveAddress');

        expect($method->getNumberOfParameters())->toBe(3);

        $params = $method->getParameters();
        expect($params[0]->getName())->toBe('userId');
        expect($params[1]->getName())->toBe('network');
        expect($params[2]->getName())->toBe('asset');
    });
});

describe('ReceiveAddressService QR Value', function (): void {
    it('builds Solana USDC QR value with the USDC mint per Solana Pay spec', function (): void {
        $service = new ReceiveAddressService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildQrValue');

        $qr = $method->invoke($service, 'testAddr123', PaymentNetwork::SOLANA, PaymentAsset::USDC);

        expect($qr)->toBe('solana:testAddr123?spl-token=EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v');
    });

    it('builds Solana USDT QR value with the USDT mint per Solana Pay spec', function (): void {
        $service = new ReceiveAddressService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildQrValue');

        $qr = $method->invoke($service, 'testAddr123', PaymentNetwork::SOLANA, PaymentAsset::USDT);

        expect($qr)->toBe('solana:testAddr123?spl-token=Es9vMFrzaCERmJfrF4H2FYD4KCoNkY11McCe8BenwNYB');
    });

    it('builds Tron QR value as plain address', function (): void {
        $service = new ReceiveAddressService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildQrValue');

        $qr = $method->invoke($service, 'TABCDEF1234567890123456789012345', PaymentNetwork::TRON, PaymentAsset::USDC);

        expect($qr)->toBe('TABCDEF1234567890123456789012345');
    });
});

describe('ReceiveAddressService Demo Address Generation', function (): void {
    it('generates default hex address for Solana in demo mode (real address via derivePublicKey)', function (): void {
        // Note: generateDemoAddress is never called for Solana in production —
        // derivePublicKey() returns early via SolanaAddressHelper::deriveForUser().
        // This tests the fallback default branch if it were reached.
        $service = new ReceiveAddressService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('generateDemoAddress');

        $address = $method->invoke($service, 1, PaymentNetwork::SOLANA);

        expect($address)->toBeString()->toStartWith('0x');
        expect(strlen($address))->toBe(42);
    });

    it('generates T-prefixed addresses for Tron via reflection', function (): void {
        $service = new ReceiveAddressService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('generateDemoAddress');

        $address = $method->invoke($service, 1, PaymentNetwork::TRON);

        expect($address)->toStartWith('T');
        expect(strlen($address))->toBe(34);
    });

    it('generates deterministic addresses for same user and network', function (): void {
        $service = new ReceiveAddressService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('generateDemoAddress');

        $addr1 = $method->invoke($service, 42, PaymentNetwork::SOLANA);
        $addr2 = $method->invoke($service, 42, PaymentNetwork::SOLANA);

        expect($addr1)->toBe($addr2);
    });

    it('generates different addresses for different users', function (): void {
        $service = new ReceiveAddressService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('generateDemoAddress');

        $addr1 = $method->invoke($service, 1, PaymentNetwork::SOLANA);
        $addr2 = $method->invoke($service, 2, PaymentNetwork::SOLANA);

        expect($addr1)->not->toBe($addr2);
    });

    it('generates different addresses for different networks', function (): void {
        $service = new ReceiveAddressService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('generateDemoAddress');

        $addr1 = $method->invoke($service, 1, PaymentNetwork::SOLANA);
        $addr2 = $method->invoke($service, 1, PaymentNetwork::TRON);

        expect($addr1)->not->toBe($addr2);
    });
});
