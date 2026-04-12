<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Providers;

use App\Domain\Ramp\Contracts\RampProviderInterface;
use Illuminate\Support\Str;
use RuntimeException;

class MockRampProvider implements RampProviderInterface
{
    public function __construct()
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Mock ramp provider must not be used in production.');
        }
    }

    public function createSession(array $params): array
    {
        $sessionId = 'mock_' . Str::uuid()->toString();

        return [
            'session_id'   => $sessionId,
            'checkout_url' => null,
            'metadata'     => [
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
            'fiatCurrencies'   => ['USD', 'EUR', 'GBP'],
            'cryptoCurrencies' => ['USDC', 'USDT', 'ETH', 'BTC'],
            'modes'            => ['on', 'off'],
            'limits'           => [
                'minAmount'  => (int) config('ramp.limits.min_fiat_amount', 10),
                'maxAmount'  => (int) config('ramp.limits.max_fiat_amount', 10000),
                'dailyLimit' => (int) config('ramp.limits.daily_limit', 50000),
            ],
        ];
    }

    public function getQuotes(string $type, string $fiatCurrency, string $fiatAmount, string $cryptoCurrency): array
    {
        $rates = [
            'USDC' => 1.0,
            'USDT' => 1.0,
            'ETH'  => 0.00028,
            'BTC'  => 0.000011,
        ];

        $rate = $rates[$cryptoCurrency] ?? 1.0;

        // Mock provider uses float arithmetic for demo calculations only
        $amount = (float) $fiatAmount;

        return [
            [
                'provider_name'   => 'MockProvider A',
                'quote_id'        => 'mock_quote_a_' . Str::random(8),
                'fiat_amount'     => $amount,
                'crypto_amount'   => round(($amount - $amount * 0.015) * $rate, 8),
                'exchange_rate'   => $rate,
                'fee'             => round($amount * 0.015, 2),
                'network_fee'     => 0.0,
                'fee_currency'    => $fiatCurrency,
                'payment_methods' => ['credit_card', 'bank_transfer'],
            ],
            [
                'provider_name'   => 'MockProvider B',
                'quote_id'        => 'mock_quote_b_' . Str::random(8),
                'fiat_amount'     => $amount,
                'crypto_amount'   => round(($amount - $amount * 0.025) * $rate, 8),
                'exchange_rate'   => $rate,
                'fee'             => round($amount * 0.020, 2),
                'network_fee'     => round($amount * 0.005, 2),
                'fee_currency'    => $fiatCurrency,
                'payment_methods' => ['credit_card'],
            ],
        ];
    }

    public function getWebhookValidator(): callable
    {
        return fn (string $rawBody, string $signatureHeader): bool => $signatureHeader !== '';
    }

    public function getWebhookSignatureHeader(): string
    {
        return 'X-Mock-Signature';
    }

    public function normalizeWebhookPayload(array $payload): ?array
    {
        $sessionId = (string) ($payload['session_id'] ?? '');
        if ($sessionId === '') {
            return null;
        }

        return [
            'session_id'    => $sessionId,
            'status'        => \App\Models\RampSession::STATUS_COMPLETED,
            'crypto_amount' => isset($payload['crypto_amount']) ? (string) $payload['crypto_amount'] : null,
            'raw'           => $payload,
        ];
    }

    public function getName(): string
    {
        return 'mock';
    }
}
