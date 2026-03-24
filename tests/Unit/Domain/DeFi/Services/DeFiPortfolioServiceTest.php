<?php

declare(strict_types=1);

use App\Domain\CrossChain\Enums\CrossChainNetwork;
use App\Domain\DeFi\Enums\DeFiPositionType;
use App\Domain\DeFi\Enums\DeFiProtocol;
use App\Domain\DeFi\Services\DeFiPortfolioService;
use App\Domain\DeFi\Services\DeFiPositionTrackerService;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::flush();
    $this->tracker = new DeFiPositionTrackerService();
    $this->portfolio = new DeFiPortfolioService($this->tracker);

    // Set up common test positions
    $this->tracker->openPosition(DeFiProtocol::AAVE_V3, DeFiPositionType::SUPPLY, CrossChainNetwork::ETHEREUM, 'USDC', '5000.00', '5000.00', '3.50', '0xTestWallet');
    $this->tracker->openPosition(DeFiProtocol::LIDO, DeFiPositionType::STAKE, CrossChainNetwork::ETHEREUM, 'ETH', '4.00', '10000.00', '3.80', '0xTestWallet');
    $this->tracker->openPosition(DeFiProtocol::UNISWAP_V3, DeFiPositionType::LP, CrossChainNetwork::POLYGON, 'USDC/WETH', '2000.00', '2000.00', '12.50', '0xTestWallet');
});

describe('DeFiPortfolioService', function () {
    it('returns portfolio summary with total value', function () {
        $summary = $this->portfolio->getPortfolioSummary('0xTestWallet');

        expect($summary['total_value_usd'])->toBe('17000.00');
        expect($summary['positions_count'])->toBe(3);
    });

    it('includes protocol breakdown', function () {
        $summary = $this->portfolio->getPortfolioSummary('0xTestWallet');

        expect($summary['protocol_breakdown'])->toHaveKey('aave_v3');
        expect($summary['protocol_breakdown'])->toHaveKey('lido');
        expect($summary['protocol_breakdown'])->toHaveKey('uniswap_v3');
    });

    it('includes chain breakdown', function () {
        $summary = $this->portfolio->getPortfolioSummary('0xTestWallet');

        expect($summary['chain_breakdown'])->toHaveKey('ethereum');
        expect($summary['chain_breakdown'])->toHaveKey('polygon');
        expect($summary['chain_breakdown']['ethereum']['positions'])->toBe(2);
    });

    it('calculates weighted average APY', function () {
        $summary = $this->portfolio->getPortfolioSummary('0xTestWallet');

        expect((float) $summary['weighted_avg_apy'])->toBeGreaterThan(0);
    });

    it('gets portfolio grouped by chain', function () {
        $byChain = $this->portfolio->getByChain('0xTestWallet');

        expect($byChain)->toHaveKey('ethereum');
        expect($byChain['ethereum']['value_usd'])->toBe('15000.00');
    });

    it('returns empty summary for wallet with no positions', function () {
        $summary = $this->portfolio->getPortfolioSummary('0xEmptyWallet');

        expect($summary['total_value_usd'])->toBe('0');
        expect($summary['positions_count'])->toBe(0);
    });
});
