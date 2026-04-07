<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Providers;

use App\Domain\Ramp\Contracts\RampProviderInterface;
use App\Domain\Ramp\Services\StripeBridgeService;
use App\Models\RampSession;

class StripeBridgeProvider implements RampProviderInterface
{
    public function __construct(
        private readonly StripeBridgeService $service,
    ) {
    }

    public function createSession(array $params): array
    {
        /** @var numeric-string $rawAmount */
        $rawAmount = (string) $params['fiat_amount'];
        $fiatAmount = bcadd($rawAmount, '0', 2);

        $result = $this->service->createSession(
            $params['type'],
            $params['fiat_currency'],
            $fiatAmount,
            $params['crypto_currency'],
            $params['wallet_address'],
        );

        return [
            'session_id'   => $result['session_id'],
            'checkout_url' => $result['checkout_url'],
            'metadata'     => [
                'provider'          => 'stripe_bridge',
                'stripe_session_id' => $result['session_id'],
                'client_secret'     => $result['client_secret'],
                'checkout_url'      => $result['checkout_url'],
                'type'              => $params['type'],
            ],
        ];
    }

    public function getSessionStatus(string $sessionId): array
    {
        // Stripe Bridge status is updated via webhooks; return current DB state
        return [
            'status'        => RampSession::STATUS_PENDING,
            'fiat_amount'   => null,
            'crypto_amount' => null,
            'metadata'      => ['provider' => 'stripe_bridge'],
        ];
    }

    public function getSupportedCurrencies(): array
    {
        $supported = $this->service->getSupportedCurrencies();
        $pairs = [];

        foreach ($supported['fiatCurrencies'] as $fiat) {
            foreach ($supported['cryptoCurrencies'] as $crypto) {
                $pairs[] = ['fiat' => $fiat, 'crypto' => $crypto];
            }
        }

        return $pairs;
    }

    public function getQuotes(string $type, string $fiatCurrency, string $fiatAmount, string $cryptoCurrency): array
    {
        /** @var numeric-string $fiatAmount */
        $amount = bcadd($fiatAmount, '0', 2);
        $quote = $this->service->getQuote($type, $fiatCurrency, $amount, $cryptoCurrency);

        return [
            [
                'provider_name'   => $quote['providerName'],
                'quote_id'        => $quote['quoteId'],
                'fiat_amount'     => (float) $quote['fiatAmount'],
                'crypto_amount'   => (float) $quote['cryptoAmount'],
                'exchange_rate'   => (float) $quote['exchangeRate'],
                'fee'             => (float) $quote['fee'],
                'network_fee'     => (float) $quote['networkFee'],
                'fee_currency'    => $quote['feeCurrency'],
                'payment_methods' => $quote['paymentMethods'],
            ],
        ];
    }

    public function getWebhookValidator(): callable
    {
        return function (string $payload, string $signature): bool {
            $secret = (string) config('services.stripe.bridge_webhook_secret', '');

            if ($secret === '') {
                return ! app()->environment('production');
            }

            $computed = hash_hmac('sha256', $payload, $secret);

            return hash_equals($computed, $signature);
        };
    }

    public function getName(): string
    {
        return 'stripe_bridge';
    }
}
