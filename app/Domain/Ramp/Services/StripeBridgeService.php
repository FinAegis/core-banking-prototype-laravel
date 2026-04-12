<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Stripe Bridge (Crypto Onramp) API client.
 *
 * Wraps Stripe's crypto onramp endpoints for creating fiat-to-crypto
 * and crypto-to-fiat sessions. Uses HTTP facade (no SDK required).
 *
 * @see https://docs.stripe.com/crypto/onramp
 */
class StripeBridgeService
{
    private readonly string $apiKey;

    private readonly string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('services.stripe.secret', '');
        $this->baseUrl = 'https://api.stripe.com/v1';
    }

    /**
     * Create a Stripe crypto onramp session.
     *
     * @return array{session_id: string, client_secret: string, checkout_url: string|null, status: string}
     */
    public function createSession(
        string $type,
        string $fiatCurrency,
        string $fiatAmount,
        string $cryptoCurrency,
        string $walletAddress,
    ): array {
        $this->ensureConfigured();

        $params = [
            'wallet_addresses'     => [$this->mapNetwork($cryptoCurrency) => $walletAddress],
            'source_currency'      => strtolower($fiatCurrency),
            'source_amount'        => $fiatAmount,
            'destination_currency' => $this->mapCryptoSymbol($cryptoCurrency),
            'destination_network'  => $this->mapNetwork($cryptoCurrency),
            'transaction_details'  => ['destination_currency' => $this->mapCryptoSymbol($cryptoCurrency)],
        ];

        if ($type === 'off') {
            $params['mode'] = 'off_ramp';
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->asForm()
            ->post("{$this->baseUrl}/crypto/onramp_sessions", $params);

        if (! $response->successful()) {
            Log::error('Stripe Bridge: Session creation failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new RuntimeException(
                'Failed to create Stripe Bridge session: ' . ($response->json('error.message') ?? $response->body())
            );
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        $sessionId = (string) ($data['id'] ?? '');
        $clientSecret = (string) ($data['client_secret'] ?? '');
        $status = (string) ($data['status'] ?? 'initialized');

        return [
            'session_id'    => $sessionId,
            'client_secret' => $clientSecret,
            'checkout_url'  => $this->buildCheckoutUrl($clientSecret),
            'status'        => $status,
        ];
    }

    /**
     * Fetch a Stripe crypto onramp session by ID.
     *
     * @return array{status: string, destination_amount: string|null, raw: array<string, mixed>}
     */
    public function getSession(string $sessionId): array
    {
        $this->ensureConfigured();

        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->get("{$this->baseUrl}/crypto/onramp_sessions/{$sessionId}");

        if (! $response->successful()) {
            Log::error('Stripe Bridge: Session fetch failed', [
                'status'     => $response->status(),
                'body'       => $response->body(),
                'session_id' => $sessionId,
            ]);
            throw new RuntimeException(
                'Failed to fetch Stripe Bridge session: ' . ($response->json('error.message') ?? $response->body())
            );
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        $destinationAmount = null;
        if (isset($data['destination_amount']) && is_numeric($data['destination_amount'])) {
            $destinationAmount = bcadd((string) $data['destination_amount'], '0', 8);
        }

        return [
            'status'             => (string) ($data['status'] ?? 'initialized'),
            'destination_amount' => $destinationAmount,
            'raw'                => $data,
        ];
    }

    /**
     * Get a quote for a fiat-crypto conversion.
     *
     * @param  numeric-string  $amount
     * @return array{providerName: string, quoteId: string, fiatAmount: string, cryptoAmount: string, exchangeRate: string, fee: string, networkFee: string, feeCurrency: string, paymentMethods: list<string>}
     */
    public function getQuote(
        string $type,
        string $fiatCurrency,
        string $amount,
        string $cryptoCurrency,
    ): array {
        $this->ensureConfigured();

        $params = [
            'source_currency'      => strtolower($fiatCurrency),
            'source_amount'        => $amount,
            'destination_currency' => $this->mapCryptoSymbol($cryptoCurrency),
            'destination_network'  => $this->mapNetwork($cryptoCurrency),
        ];

        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->get("{$this->baseUrl}/crypto/onramp_sessions/quotes", $params);

        if ($response->successful()) {
            /** @var array<string, mixed> $data */
            $data = $response->json() ?? [];

            $rawDest = (string) ($data['destination_amount'] ?? '0');
            $rawSource = (string) ($data['source_amount'] ?? $amount);
            $rawTotalFee = (string) (is_array($data['fees'] ?? null) ? ($data['fees']['total_fee'] ?? '0') : '0');
            $rawNetworkFee = (string) (is_array($data['fees'] ?? null) ? ($data['fees']['network_fee'] ?? '0') : '0');

            $destinationAmount = bcadd(is_numeric($rawDest) ? $rawDest : '0', '0', 8);
            $sourceAmount = bcadd(is_numeric($rawSource) ? $rawSource : '0', '0', 2);
            $totalFee = bcadd(is_numeric($rawTotalFee) ? $rawTotalFee : '0', '0', 2);
            $networkFee = bcadd(is_numeric($rawNetworkFee) ? $rawNetworkFee : '0', '0', 2);
            $stripeFee = bcsub($totalFee, $networkFee, 2);

            // Calculate exchange rate: crypto per unit of net fiat
            $netFiat = bcsub($sourceAmount, $totalFee, 2);
            $exchangeRate = bccomp($netFiat, '0', 2) > 0
                ? bcdiv($destinationAmount, $netFiat, 8)
                : '0';

            return [
                'providerName'   => 'Stripe',
                'quoteId'        => 'stripe_quote_' . bin2hex(random_bytes(8)),
                'fiatAmount'     => $sourceAmount,
                'cryptoAmount'   => $destinationAmount,
                'exchangeRate'   => $exchangeRate,
                'fee'            => $stripeFee,
                'networkFee'     => $networkFee,
                'feeCurrency'    => strtoupper($fiatCurrency),
                'paymentMethods' => ['card', 'bank_transfer'],
            ];
        }

        // If quotes endpoint not available, return an estimated quote
        Log::info('Stripe Bridge: Quote endpoint unavailable, returning estimate', [
            'status' => $response->status(),
        ]);

        return $this->buildEstimatedQuote($fiatCurrency, $amount, $cryptoCurrency);
    }

    /**
     * Get supported currencies for Stripe Bridge.
     *
     * @return array{fiatCurrencies: list<string>, cryptoCurrencies: list<string>, modes: list<string>, limits: array{minAmount: int, maxAmount: int, dailyLimit: int}}
     */
    public function getSupportedCurrencies(): array
    {
        return [
            'fiatCurrencies'   => ['USD', 'EUR', 'GBP'],
            'cryptoCurrencies' => ['USDC'],
            'modes'            => ['buy', 'sell'],
            'limits'           => [
                'minAmount'  => (int) config('ramp.limits.min_fiat_amount', 10),
                'maxAmount'  => (int) config('ramp.limits.max_fiat_amount', 10000),
                'dailyLimit' => (int) config('ramp.limits.daily_limit', 50000),
            ],
        ];
    }

    /**
     * Map Stripe onramp status to our internal session status.
     */
    public function mapStripeStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'initialized'      => 'pending',
            'payment_pending'  => 'processing',
            'payment_complete' => 'processing',
            'fulfilled'        => 'completed',
            'payment_failed'   => 'failed',
            'expired'          => 'expired',
            default            => 'processing',
        };
    }

    /**
     * Map Stripe onramp status to a human-readable label.
     */
    public function mapStripeStatusLabel(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'initialized'      => 'Waiting for payment',
            'payment_pending'  => 'Payment processing',
            'payment_complete' => 'Sending crypto',
            'fulfilled'        => 'Completed',
            'payment_failed'   => 'Payment failed',
            'expired'          => 'Session expired',
            default            => 'Processing',
        };
    }

    /**
     * Build an estimated quote when the quotes endpoint is unavailable.
     *
     * @param  numeric-string  $amount
     * @return array{providerName: string, quoteId: string, fiatAmount: string, cryptoAmount: string, exchangeRate: string, fee: string, networkFee: string, feeCurrency: string, paymentMethods: list<string>}
     */
    private function buildEstimatedQuote(string $fiatCurrency, string $amount, string $cryptoCurrency): array
    {
        // USDC is 1:1 pegged; estimate 1.5% total fees
        $fee = bcmul($amount, '0.01', 2);
        $networkFee = bcmul($amount, '0.005', 2);
        $totalFee = bcadd($fee, $networkFee, 2);
        $netAmount = bcsub($amount, $totalFee, 2);

        return [
            'providerName'   => 'Stripe',
            'quoteId'        => 'stripe_quote_' . bin2hex(random_bytes(8)),
            'fiatAmount'     => $amount,
            'cryptoAmount'   => $netAmount,
            'exchangeRate'   => '1.0',
            'fee'            => $fee,
            'networkFee'     => $networkFee,
            'feeCurrency'    => strtoupper($fiatCurrency),
            'paymentMethods' => ['card', 'bank_transfer'],
        ];
    }

    /**
     * Build the checkout URL from a client secret.
     */
    private function buildCheckoutUrl(string $clientSecret): ?string
    {
        if ($clientSecret === '') {
            return null;
        }

        return 'https://crypto-onramp.stripe.com/crypto/onramp/' . $clientSecret;
    }

    /**
     * Map crypto symbol to Stripe's destination currency format.
     */
    private function mapCryptoSymbol(string $crypto): string
    {
        return match (strtoupper($crypto)) {
            'USDC'  => 'usdc',
            'USDT'  => 'usdt',
            'ETH'   => 'eth',
            'BTC'   => 'btc',
            default => strtolower($crypto),
        };
    }

    /**
     * Map crypto symbol to its network for Stripe.
     */
    private function mapNetwork(string $crypto): string
    {
        return match (strtoupper($crypto)) {
            'USDC', 'USDT', 'ETH' => 'ethereum',
            'BTC'   => 'bitcoin',
            default => 'ethereum',
        };
    }

    /**
     * Ensure Stripe API key is configured.
     */
    private function ensureConfigured(): void
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('Stripe API key is not configured. Set STRIPE_SECRET in your environment.');
        }
    }
}
