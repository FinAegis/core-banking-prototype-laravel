<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Providers;

use App\Domain\Ramp\Contracts\RampProviderInterface;
use Illuminate\Support\Str;
use RuntimeException;

class MockRampProvider implements RampProviderInterface
{
    public function createSession(array $params): array
    {
        $sessionId = 'mock_' . Str::uuid()->toString();

        return [
            'session_id'    => $sessionId,
            'redirect_url'  => null,
            'widget_config' => [
                'provider'   => 'mock',
                'session_id' => $sessionId,
                'type'       => $params['type'],
                'fiat'       => $params['fiat_currency'],
                'crypto'     => $params['crypto_currency'],
                'amount'     => $params['fiat_amount'],
                'sandbox'    => true,
            ],
        ];
    }

    public function getSessionStatus(string $sessionId): array
    {
        return [
            'status'        => 'completed',
            'fiat_amount'   => 100.00,
            'crypto_amount' => 99.50,
            'metadata'      => ['provider' => 'mock', 'sandbox' => true],
        ];
    }

    public function getSupportedCurrencies(): array
    {
        return [
            ['fiat' => 'USD', 'crypto' => 'USDC'],
            ['fiat' => 'USD', 'crypto' => 'USDT'],
            ['fiat' => 'USD', 'crypto' => 'ETH'],
            ['fiat' => 'EUR', 'crypto' => 'USDC'],
            ['fiat' => 'EUR', 'crypto' => 'ETH'],
            ['fiat' => 'GBP', 'crypto' => 'USDC'],
        ];
    }

    public function getQuote(string $type, string $fiatCurrency, float $fiatAmount, string $cryptoCurrency): array
    {
        // Mock exchange rates
        $rates = [
            'USDC' => 1.0,
            'USDT' => 1.0,
            'ETH'  => 0.00028,
            'BTC'  => 0.000011,
        ];

        $rate = $rates[$cryptoCurrency] ?? 1.0;
        $fee = $fiatAmount * 0.015; // 1.5% fee
        $netAmount = $fiatAmount - $fee;
        $cryptoAmount = $netAmount * $rate;

        return [
            'fiat_amount'   => $fiatAmount,
            'crypto_amount' => round($cryptoAmount, 8),
            'exchange_rate' => $rate,
            'fee'           => round($fee, 2),
            'fee_currency'  => $fiatCurrency,
        ];
    }

    public function getWebhookValidator(): callable
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Mock ramp provider must not be used in production.');
        }

        return fn (string $payload, string $signature): bool => $signature !== '';
    }

    public function getName(): string
    {
        return 'mock';
    }
}
