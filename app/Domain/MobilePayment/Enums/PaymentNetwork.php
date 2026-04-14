<?php

declare(strict_types=1);

namespace App\Domain\MobilePayment\Enums;

/**
 * Supported blockchain networks for mobile payments.
 *
 * v1: Solana + Tron
 * v2: Added EVM chains (Polygon, Base, Arbitrum, Ethereum)
 */
enum PaymentNetwork: string
{
    case SOLANA = 'SOLANA';
    case TRON = 'TRON';
    case POLYGON = 'polygon';
    case BASE = 'base';
    case ARBITRUM = 'arbitrum';
    case ETHEREUM = 'ethereum';

    public function label(): string
    {
        return match ($this) {
            self::SOLANA   => 'Solana',
            self::TRON     => 'Tron',
            self::POLYGON  => 'Polygon',
            self::BASE     => 'Base',
            self::ARBITRUM => 'Arbitrum',
            self::ETHEREUM => 'Ethereum',
        };
    }

    public function nativeAsset(): string
    {
        return match ($this) {
            self::SOLANA                               => 'SOL',
            self::TRON                                 => 'TRX',
            self::POLYGON                              => 'MATIC',
            self::BASE, self::ARBITRUM, self::ETHEREUM => 'ETH',
        };
    }

    public function averageGasCostUsd(): float
    {
        return match ($this) {
            self::SOLANA   => 0.001,
            self::TRON     => 0.50,
            self::POLYGON  => 0.01,
            self::BASE     => 0.005,
            self::ARBITRUM => 0.01,
            self::ETHEREUM => 2.00,
        };
    }

    public function explorerBaseUrl(): string
    {
        return match ($this) {
            self::SOLANA   => 'https://solscan.io/tx/',
            self::TRON     => 'https://tronscan.org/#/transaction/',
            self::POLYGON  => 'https://polygonscan.com/tx/',
            self::BASE     => 'https://basescan.org/tx/',
            self::ARBITRUM => 'https://arbiscan.io/tx/',
            self::ETHEREUM => 'https://etherscan.io/tx/',
        };
    }

    public function explorerUrl(string $txHash): string
    {
        return $this->explorerBaseUrl() . $txHash;
    }

    public function requiredConfirmations(): int
    {
        return match ($this) {
            self::SOLANA   => 32,
            self::TRON     => 20,
            self::POLYGON  => 128,
            self::BASE     => 12,
            self::ARBITRUM => 12,
            self::ETHEREUM => 12,
        };
    }

    public function addressPattern(): string
    {
        return match ($this) {
            self::SOLANA                                              => '/^[1-9A-HJ-NP-Za-km-z]{32,44}$/',
            self::TRON                                                => '/^T[1-9A-HJ-NP-Za-km-z]{33}$/',
            self::POLYGON, self::BASE, self::ARBITRUM, self::ETHEREUM => '/^0x[0-9a-fA-F]{40}$/',
        };
    }

    public function isEvm(): bool
    {
        return in_array($this, [self::POLYGON, self::BASE, self::ARBITRUM, self::ETHEREUM], true);
    }

    /**
     * Average time in seconds for a transfer to reach required confirmations.
     */
    public function avgConfirmationSeconds(): int
    {
        return match ($this) {
            self::SOLANA   => 5,
            self::TRON     => 30,
            self::POLYGON  => 6,
            self::BASE     => 2,
            self::ARBITRUM => 3,
            self::ETHEREUM => 15,
        };
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
