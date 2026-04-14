<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Providers;

use App\Domain\Ramp\Clients\OnramperClient;
use App\Domain\Ramp\Contracts\RampProviderInterface;
use App\Models\RampSession;
use RuntimeException;

class OnramperProvider implements RampProviderInterface
{
    public function __construct(
        private readonly OnramperClient $client,
    ) {
    }

    public function createSession(array $params): array
    {
        $quoteId = $params['quote_id'] ?? null;
        $walletAddress = $params['wallet_address'];

        if (! $quoteId) {
            throw new RuntimeException('A quote_id is required. Call GET /ramp/quotes first and select a provider.');
        }

        $redirectUrl = config('ramp.providers.onramper.success_redirect_url')
            ?: config('app.url') . '/ramp/complete';

        $result = $this->client->createCheckoutIntent([
            'quoteId'       => $quoteId,
            'walletAddress' => $walletAddress,
            'redirectURL'   => $redirectUrl,
        ]);

        $transactionId = $result['transactionId'] ?? $result['id'] ?? ('onr_' . bin2hex(random_bytes(16)));
        $checkoutUrl = $result['checkoutUrl'] ?? $result['checkout_url'] ?? null;

        return [
            'session_id'   => $transactionId,
            'checkout_url' => $checkoutUrl,
            'metadata'     => [
                'provider'       => 'onramper',
                'transaction_id' => $transactionId,
                'checkout_url'   => $checkoutUrl,
                'quote_id'       => $quoteId,
                'type'           => $params['type'],
            ],
        ];
    }

    public function getSessionStatus(string $sessionId): array
    {
        try {
            $transaction = $this->client->getTransaction($sessionId);

            $status = $this->mapOnramperStatus($transaction['status'] ?? 'unknown');

            return [
                'status'        => $status,
                'fiat_amount'   => (float) ($transaction['fiatAmount'] ?? 0),
                'crypto_amount' => (float) ($transaction['cryptoAmount'] ?? 0),
                'metadata'      => [
                    'provider'        => 'onramper',
                    'onramper_id'     => $transaction['id'] ?? $sessionId,
                    'payment_method'  => $transaction['paymentMethod'] ?? null,
                    'onramp_provider' => $transaction['provider'] ?? null,
                ],
            ];
        } catch (RuntimeException) {
            return [
                'status'        => RampSession::STATUS_PENDING,
                'fiat_amount'   => null,
                'crypto_amount' => null,
                'metadata'      => ['provider' => 'onramper'],
            ];
        }
    }

    public function getSupportedCurrencies(): array
    {
        /** @var list<string> $fiats */
        $fiats = config('ramp.supported_fiat', ['USD', 'EUR', 'GBP']);
        /** @var list<string> $cryptos */
        $cryptos = config('ramp.supported_crypto', ['USDC', 'USDT', 'ETH', 'BTC']);

        return [
            'fiatCurrencies'   => array_values($fiats),
            'cryptoCurrencies' => array_values($cryptos),
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
        $source = strtolower($fiatCurrency);
        $destination = strtolower($cryptoCurrency);

        if ($type === 'off') {
            [$source, $destination] = [$destination, $source];
        }

        $rawQuotes = $this->client->getQuotes($source, $destination, $fiatAmount);

        if (empty($rawQuotes)) {
            return [];
        }

        $normalized = [];
        foreach ($rawQuotes as $q) {
            if (! is_array($q)) {
                continue;
            }

            $cryptoAmount = (float) ($q['cryptoAmount'] ?? $q['payout'] ?? 0);
            $fiatFee = (float) ($q['fee']['fiatFee'] ?? $q['fiatFee'] ?? $q['totalFee'] ?? 0);
            $networkFee = (float) ($q['fee']['networkFee'] ?? $q['networkFee'] ?? 0);
            $totalFee = $fiatFee + $networkFee;
            $netFiat = (float) $fiatAmount - $totalFee;
            $exchangeRate = $netFiat > 0 ? $cryptoAmount / $netFiat : 0;

            $normalized[] = [
                'provider_name'   => $q['provider'] ?? 'unknown',
                'quote_id'        => $q['quoteId'] ?? $q['id'] ?? null,
                'fiat_amount'     => (float) $fiatAmount,
                'crypto_amount'   => round($cryptoAmount, 8),
                'exchange_rate'   => round($exchangeRate, 8),
                'fee'             => round($fiatFee, 2),
                'network_fee'     => round($networkFee, 2),
                'fee_currency'    => $fiatCurrency,
                'payment_methods' => (array) ($q['paymentMethods'] ?? $q['paymentMethod'] ?? []),
            ];
        }

        return $normalized;
    }

    public function getWebhookValidator(): callable
    {
        return fn (string $rawBody, string $signatureHeader): bool => $this->client->verifyWebhookSignature($rawBody, $signatureHeader);
    }

    public function getWebhookSignatureHeader(): string
    {
        return 'X-Onramper-Webhook-Signature';
    }

    public function normalizeWebhookPayload(array $payload): ?array
    {
        /** @var mixed $sessionId */
        $sessionId = $payload['session_id'] ?? $payload['partnerContext'] ?? $payload['id'] ?? null;
        if (! is_string($sessionId) || $sessionId === '') {
            return null;
        }

        $rawStatus = (string) ($payload['status'] ?? '');
        if ($rawStatus === '') {
            return null;
        }

        $cryptoAmount = null;
        if (isset($payload['crypto_amount']) && is_numeric($payload['crypto_amount'])) {
            $cryptoAmount = bcadd((string) $payload['crypto_amount'], '0', 8);
        }

        return [
            'session_id'    => $sessionId,
            'status'        => $this->mapOnramperStatus($rawStatus),
            'crypto_amount' => $cryptoAmount,
            'raw'           => $payload,
        ];
    }

    public function getName(): string
    {
        return 'onramper';
    }

    private function mapOnramperStatus(string $status): string
    {
        return match (strtolower($status)) {
            'completed', 'success', 'done'            => RampSession::STATUS_COMPLETED,
            'failed', 'error', 'cancelled', 'expired' => RampSession::STATUS_FAILED,
            'pending', 'new', 'created'               => RampSession::STATUS_PENDING,
            default                                   => RampSession::STATUS_PROCESSING,
        };
    }
}
