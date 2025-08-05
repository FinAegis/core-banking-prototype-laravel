<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Factories;

use App\Domain\Wallet\Connectors\EthereumConnector;
use App\Domain\Wallet\Connectors\SimpleBitcoinConnector;
use App\Domain\Wallet\Contracts\BlockchainConnector;
use App\Domain\Wallet\Services\DemoBlockchainService;
use InvalidArgumentException;

/**
 * Factory for creating blockchain connectors based on environment and chain.
 */
class BlockchainConnectorFactory
{
    /**
     * Create a blockchain connector for the specified chain.
     */
    public static function create(string $chain): BlockchainConnector
    {
        // Use demo service in demo mode
        if (config('demo.mode') || config('demo.sandbox.enabled')) {
            return new DemoBlockchainService($chain, self::getChainId($chain));
        }

        // Production connectors
        return match (strtolower($chain)) {
            'ethereum' => new EthereumConnector(
                config('blockchain.ethereum.rpc_url'),
                config('blockchain.ethereum.chain_id', '1')
            ),
            'polygon' => new EthereumConnector(
                config('blockchain.polygon.rpc_url'),
                config('blockchain.polygon.chain_id', '137')
            ),
            'bitcoin' => new SimpleBitcoinConnector([
                'api_url' => config('blockchain.bitcoin.rpc_url'),
                'network' => config('blockchain.bitcoin.network', 'mainnet'),
                'api_key' => config('blockchain.bitcoin.api_key'),
            ]),
            default => throw new InvalidArgumentException("Unsupported blockchain: {$chain}"),
        };
    }

    /**
     * Get the chain ID for a given chain.
     */
    private static function getChainId(string $chain): string
    {
        return match (strtolower($chain)) {
            'ethereum' => config('demo.sandbox.blockchain_testnets.ethereum') === 'sepolia' ? '11155111' : '1',
            'polygon'  => config('demo.sandbox.blockchain_testnets.polygon') === 'mumbai' ? '80001' : '137',
            'bitcoin'  => config('demo.sandbox.blockchain_testnets.bitcoin') === 'testnet' ? 'testnet' : 'mainnet',
            default    => '1',
        };
    }
}
