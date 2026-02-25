<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

use App\Domain\MobilePayment\Enums\PaymentNetwork;
use App\Domain\MobilePayment\Services\NetworkAvailabilityService;

describe('NetworkAvailabilityService', function (): void {
    it('can be instantiated', function (): void {
        $service = new NetworkAvailabilityService();

        expect($service)->toBeInstanceOf(NetworkAvailabilityService::class);
    });

    it('returns statuses for all supported networks', function (): void {
        $service = new NetworkAvailabilityService();
        $statuses = $service->getNetworkStatuses();

        expect($statuses)->toHaveCount(count(PaymentNetwork::cases()));

        $networkIds = array_column($statuses, 'id');
        foreach (PaymentNetwork::cases() as $network) {
            expect($networkIds)->toContain($network->value);
        }
    });

    it('returns correct structure for each network status', function (): void {
        $service = new NetworkAvailabilityService();
        $statuses = $service->getNetworkStatuses();

        foreach ($statuses as $status) {
            expect($status)->toHaveKeys([
                'id', 'name', 'native_asset', 'status',
                'avg_fee_usd', 'avg_confirmation_seconds',
                'congestion', 'supported_assets',
            ]);
            expect($status['avg_fee_usd'])->toBeString();
            expect($status['avg_confirmation_seconds'])->toBeInt()->toBeGreaterThan(0);
            expect($status['congestion'])->toBeString();
            expect($status['supported_assets'])->toBeArray()->toContain('USDC');
        }
    });

    it('returns correct status for a single network', function (): void {
        $service = new NetworkAvailabilityService();
        $status = $service->getNetworkStatus(PaymentNetwork::SOLANA);
        assert($status !== null);

        expect($status['id'])->toBe('SOLANA');
        expect($status['name'])->toBe('Solana');
        expect($status['native_asset'])->toBe('SOL');
        expect($status['avg_fee_usd'])->toBe('0.001');
        expect($status['avg_confirmation_seconds'])->toBe(5);
    });

    it('returns correct fee and confirmation time for Tron', function (): void {
        $service = new NetworkAvailabilityService();
        $status = $service->getNetworkStatus(PaymentNetwork::TRON);
        assert($status !== null);

        expect($status['avg_fee_usd'])->toBe('0.500');
        expect($status['avg_confirmation_seconds'])->toBe(30);
    });

    it('returns correct data for EVM chains', function (): void {
        $service = new NetworkAvailabilityService();

        $polygon = $service->getNetworkStatus(PaymentNetwork::POLYGON);
        assert($polygon !== null);
        expect($polygon['native_asset'])->toBe('MATIC');
        expect($polygon['avg_fee_usd'])->toBe('0.010');

        $base = $service->getNetworkStatus(PaymentNetwork::BASE);
        assert($base !== null);
        expect($base['native_asset'])->toBe('ETH');
        expect($base['avg_fee_usd'])->toBe('0.005');

        $ethereum = $service->getNetworkStatus(PaymentNetwork::ETHEREUM);
        assert($ethereum !== null);
        expect($ethereum['native_asset'])->toBe('ETH');
        expect($ethereum['avg_fee_usd'])->toBe('2.000');
    });

    it('returns low congestion level by default', function (): void {
        $service = new NetworkAvailabilityService();

        foreach (PaymentNetwork::cases() as $network) {
            $status = $service->getNetworkStatus($network);
            assert($status !== null);
            expect($status['congestion'])->toBe('low');
        }
    });
});
