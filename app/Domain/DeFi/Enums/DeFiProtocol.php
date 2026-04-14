<?php

declare(strict_types=1);

namespace App\Domain\DeFi\Enums;

enum DeFiProtocol: string
{
    case UNISWAP_V3 = 'uniswap_v3';
    case AAVE_V3 = 'aave_v3';
    case CURVE = 'curve';
    case LIDO = 'lido';
    case DEMO = 'demo';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::UNISWAP_V3 => 'Uniswap V3',
            self::AAVE_V3    => 'Aave V3',
            self::CURVE      => 'Curve Finance',
            self::LIDO       => 'Lido',
            self::DEMO       => 'Demo Protocol',
        };
    }

    public function getCategory(): string
    {
        return match ($this) {
            self::UNISWAP_V3, self::CURVE => 'dex',
            self::AAVE_V3                 => 'lending',
            self::LIDO                    => 'liquid_staking',
            self::DEMO                    => 'demo',
        };
    }

    public function isProduction(): bool
    {
        return $this !== self::DEMO;
    }
}
