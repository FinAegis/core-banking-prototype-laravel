<?php

declare(strict_types=1);

use App\Domain\CrossChain\Enums\BridgeProvider;
use App\Domain\CrossChain\Enums\BridgeStatus;
use App\Domain\CrossChain\Enums\CrossChainNetwork;
use App\Domain\CrossChain\Services\Adapters\CircleCctpBridgeAdapter;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->adapter = new CircleCctpBridgeAdapter();
});

describe('CircleCctpBridgeAdapter', function () {
    it('returns circle cctp as provider', function () {
        expect($this->adapter->getProvider())->toBe(BridgeProvider::CIRCLE_CCTP);
    });

    it('estimates fees with near-zero gas cost', function () {
        $fee = $this->adapter->estimateFee(
            CrossChainNetwork::POLYGON,
            CrossChainNetwork::BASE,
            'USDC',
            '1000.00',
        );

        expect($fee['fee'])->not->toBe('0');
        expect($fee['fee_currency'])->toBe('USD');
        expect($fee['estimated_time'])->toBe(780);
    });

    it('generates valid bridge quote', function () {
        $quote = $this->adapter->getQuote(
            CrossChainNetwork::ETHEREUM,
            CrossChainNetwork::BASE,
            'USDC',
            '1000.00',
        );

        expect($quote->getProvider())->toBe(BridgeProvider::CIRCLE_CCTP);
        expect($quote->inputAmount)->toBe('1000.00');
        expect(bccomp($quote->outputAmount, '0', 8))->toBe(1);
        expect($quote->isExpired())->toBeFalse();
        expect($quote->quoteId)->toStartWith('cctp-');
    });

    it('initiates bridge and returns transaction id', function () {
        $quote = $this->adapter->getQuote(
            CrossChainNetwork::POLYGON,
            CrossChainNetwork::ARBITRUM,
            'USDC',
            '500.00',
        );

        $result = $this->adapter->initiateBridge($quote, '0xSender', '0xRecipient');

        expect($result['transaction_id'])->toStartWith('cctp-tx-');
        expect($result['status'])->toBe(BridgeStatus::INITIATED);
        expect($result['source_tx_hash'])->toStartWith('0x');
    });

    it('returns completed status for bridge check', function () {
        $status = $this->adapter->getBridgeStatus('cctp-tx-test-123');

        expect($status['status'])->toBe(BridgeStatus::COMPLETED);
        expect($status['source_tx_hash'])->toStartWith('0x');
        expect($status['dest_tx_hash'])->toStartWith('0x');
        expect($status['confirmations'])->toBe(65);
    });

    it('supports USDC only', function () {
        expect($this->adapter->supportsRoute(CrossChainNetwork::ETHEREUM, CrossChainNetwork::POLYGON, 'USDC'))->toBeTrue();
        expect($this->adapter->supportsRoute(CrossChainNetwork::ETHEREUM, CrossChainNetwork::POLYGON, 'USDT'))->toBeFalse();
        expect($this->adapter->supportsRoute(CrossChainNetwork::ETHEREUM, CrossChainNetwork::POLYGON, 'WETH'))->toBeFalse();
    });

    it('supports routes between supported EVM chains', function () {
        // Polygon <-> Base
        expect($this->adapter->supportsRoute(CrossChainNetwork::POLYGON, CrossChainNetwork::BASE, 'USDC'))->toBeTrue();
        expect($this->adapter->supportsRoute(CrossChainNetwork::BASE, CrossChainNetwork::POLYGON, 'USDC'))->toBeTrue();

        // Arbitrum <-> Polygon
        expect($this->adapter->supportsRoute(CrossChainNetwork::ARBITRUM, CrossChainNetwork::POLYGON, 'USDC'))->toBeTrue();

        // Ethereum <-> Base
        expect($this->adapter->supportsRoute(CrossChainNetwork::ETHEREUM, CrossChainNetwork::BASE, 'USDC'))->toBeTrue();
    });

    it('rejects unsupported chains', function () {
        expect($this->adapter->supportsRoute(CrossChainNetwork::BSC, CrossChainNetwork::ETHEREUM, 'USDC'))->toBeFalse();
        expect($this->adapter->supportsRoute(CrossChainNetwork::SOLANA, CrossChainNetwork::POLYGON, 'USDC'))->toBeFalse();
        expect($this->adapter->supportsRoute(CrossChainNetwork::ETHEREUM, CrossChainNetwork::TRON, 'USDC'))->toBeFalse();
    });

    it('rejects same-chain route', function () {
        expect($this->adapter->supportsRoute(CrossChainNetwork::POLYGON, CrossChainNetwork::POLYGON, 'USDC'))->toBeFalse();
    });

    it('returns supported routes for USDC across all chain pairs', function () {
        $routes = $this->adapter->getSupportedRoutes();

        // 4 chains, each pair = 4 * 3 = 12 USDC routes
        expect($routes)->toHaveCount(12);

        foreach ($routes as $route) {
            expect($route->provider)->toBe(BridgeProvider::CIRCLE_CCTP);
            expect($route->token)->toBe('USDC');
        }
    });

    it('has lower fees on L2 chains', function () {
        $ethFee = $this->adapter->estimateFee(CrossChainNetwork::ETHEREUM, CrossChainNetwork::POLYGON, 'USDC', '1000.00');
        $baseFee = $this->adapter->estimateFee(CrossChainNetwork::BASE, CrossChainNetwork::POLYGON, 'USDC', '1000.00');

        expect(bccomp($ethFee['fee'], $baseFee['fee'], 8))->toBe(1); // ETH fee > Base fee
    });
});
