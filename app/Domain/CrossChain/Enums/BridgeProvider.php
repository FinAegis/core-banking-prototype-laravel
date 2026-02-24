<?php

declare(strict_types=1);

namespace App\Domain\CrossChain\Enums;

enum BridgeProvider: string
{
    case WORMHOLE = 'wormhole';
    case LAYERZERO = 'layerzero';
    case AXELAR = 'axelar';
    case CIRCLE_CCTP = 'circle_cctp';
    case DEMO = 'demo';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::WORMHOLE    => 'Wormhole (Portal)',
            self::LAYERZERO   => 'LayerZero (OFT)',
            self::AXELAR      => 'Axelar (GMP)',
            self::CIRCLE_CCTP => 'Circle CCTP (Native USDC)',
            self::DEMO        => 'Demo Bridge',
        };
    }

    public function getAverageTransferTime(): int
    {
        return match ($this) {
            self::WORMHOLE    => 900,
            self::LAYERZERO   => 120,
            self::AXELAR      => 180,
            self::CIRCLE_CCTP => 780,
            self::DEMO        => 5,
        };
    }

    public function isProduction(): bool
    {
        return $this !== self::DEMO;
    }
}
