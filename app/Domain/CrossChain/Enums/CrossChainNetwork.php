<?php

declare(strict_types=1);

namespace App\Domain\CrossChain\Enums;

use App\Domain\Relayer\Enums\SupportedNetwork;

/**
 * Unified cross-chain network enum covering all supported blockchains.
 */
enum CrossChainNetwork: string
{
    case ETHEREUM = 'ethereum';
    case POLYGON = 'polygon';
    case BSC = 'bsc';
    case BITCOIN = 'bitcoin';
    case SOLANA = 'solana';
    case TRON = 'tron';
    case ARBITRUM = 'arbitrum';
    case OPTIMISM = 'optimism';
    case BASE = 'base';

    public function getChainId(): ?int
    {
        return match ($this) {
            self::ETHEREUM => 1,
            self::POLYGON  => 137,
            self::BSC      => 56,
            self::ARBITRUM => 42161,
            self::OPTIMISM => 10,
            self::BASE     => 8453,
            self::BITCOIN  => null,
            self::SOLANA   => null,
            self::TRON     => null,
        };
    }

    public function isEvm(): bool
    {
        return ! in_array($this, [self::BITCOIN, self::SOLANA, self::TRON]);
    }

    public function getNativeCurrency(): string
    {
        return match ($this) {
            self::ETHEREUM, self::ARBITRUM, self::OPTIMISM, self::BASE => 'ETH',
            self::POLYGON => 'MATIC',
            self::BSC     => 'BNB',
            self::BITCOIN => 'BTC',
            self::SOLANA  => 'SOL',
            self::TRON    => 'TRX',
        };
    }

    /**
     * Convert to Relayer SupportedNetwork if applicable.
     */
    public function toRelayerNetwork(): ?SupportedNetwork
    {
        return match ($this) {
            self::ETHEREUM => SupportedNetwork::ETHEREUM,
            self::POLYGON  => SupportedNetwork::POLYGON,
            self::ARBITRUM => SupportedNetwork::ARBITRUM,
            self::OPTIMISM => SupportedNetwork::OPTIMISM,
            self::BASE     => SupportedNetwork::BASE,
            default        => null,
        };
    }

    /**
     * Create from Relayer SupportedNetwork.
     */
    public static function fromRelayerNetwork(SupportedNetwork $network): self
    {
        return match ($network) {
            SupportedNetwork::ETHEREUM => self::ETHEREUM,
            SupportedNetwork::POLYGON  => self::POLYGON,
            SupportedNetwork::ARBITRUM => self::ARBITRUM,
            SupportedNetwork::OPTIMISM => self::OPTIMISM,
            SupportedNetwork::BASE     => self::BASE,
        };
    }

    /**
     * Get supported bridge providers for this network.
     *
     * @return array<BridgeProvider>
     */
    public function getSupportedBridgeProviders(): array
    {
        if (! $this->isEvm()) {
            return match ($this) {
                self::SOLANA => [BridgeProvider::WORMHOLE, BridgeProvider::DEMO],
                default      => [BridgeProvider::DEMO],
            };
        }

        return [
            BridgeProvider::WORMHOLE,
            BridgeProvider::LAYERZERO,
            BridgeProvider::AXELAR,
            BridgeProvider::CIRCLE_CCTP,
            BridgeProvider::DEMO,
        ];
    }
}
