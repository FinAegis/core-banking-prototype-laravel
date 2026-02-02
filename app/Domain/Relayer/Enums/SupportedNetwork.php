<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Enums;

/**
 * Supported blockchain networks for gas relaying.
 */
enum SupportedNetwork: string
{
    case POLYGON = 'polygon';
    case ARBITRUM = 'arbitrum';
    case OPTIMISM = 'optimism';
    case BASE = 'base';
    case ETHEREUM = 'ethereum';

    public function getChainId(): int
    {
        return match ($this) {
            self::POLYGON  => 137,
            self::ARBITRUM => 42161,
            self::OPTIMISM => 10,
            self::BASE     => 8453,
            self::ETHEREUM => 1,
        };
    }

    public function getNativeCurrency(): string
    {
        return match ($this) {
            self::POLYGON => 'MATIC',
            self::ARBITRUM, self::OPTIMISM, self::BASE, self::ETHEREUM => 'ETH',
        };
    }

    public function getAverageGasCostUsd(): float
    {
        return match ($this) {
            self::POLYGON  => 0.02,
            self::ARBITRUM => 0.15,
            self::OPTIMISM => 0.10,
            self::BASE     => 0.05,
            self::ETHEREUM => 5.00,
        };
    }

    public function getRpcUrl(): string
    {
        return match ($this) {
            self::POLYGON  => config('relayer.networks.polygon.rpc_url', ''),
            self::ARBITRUM => config('relayer.networks.arbitrum.rpc_url', ''),
            self::OPTIMISM => config('relayer.networks.optimism.rpc_url', ''),
            self::BASE     => config('relayer.networks.base.rpc_url', ''),
            self::ETHEREUM => config('relayer.networks.ethereum.rpc_url', ''),
        };
    }

    /**
     * Get the ERC-4337 EntryPoint contract address for this network.
     *
     * EntryPoint v0.6: 0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789
     */
    public function getEntryPointAddress(): string
    {
        // ERC-4337 EntryPoint v0.6 is the same on all chains
        $defaultEntryPoint = '0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789';

        return (string) config("relayer.networks.{$this->value}.entrypoint_address", $defaultEntryPoint);
    }

    /**
     * Get the Smart Account factory address for this network.
     */
    public function getFactoryAddress(): string
    {
        return (string) config("relayer.smart_accounts.factory_addresses.{$this->value}", '');
    }

    /**
     * Get the Paymaster contract address for this network.
     */
    public function getPaymasterAddress(): string
    {
        return (string) config("relayer.networks.{$this->value}.paymaster_address", '');
    }

    /**
     * Get the current gas price in gwei (demo implementation).
     */
    public function getCurrentGasPrice(): string
    {
        // In production, this would query the network
        return match ($this) {
            self::POLYGON  => '30',
            self::ARBITRUM => '0.1',
            self::OPTIMISM => '0.001',
            self::BASE     => '0.001',
            self::ETHEREUM => '20',
        };
    }

    /**
     * Get current network congestion level.
     */
    public function getCongestionLevel(): string
    {
        // In production, calculate based on gas price vs historical average
        return 'low';
    }
}
