<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Providers;

use App\Domain\Ramp\Contracts\RampProviderInterface;
use App\Domain\Ramp\Services\StripeBridgeService;

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
        $stripeSession = $this->service->getSession($sessionId);

        $cryptoAmount = null;
        if ($stripeSession['destination_amount'] !== null) {
            $cryptoAmount = (float) $stripeSession['destination_amount'];
        }

        return [
            'status'        => $this->service->mapStripeStatus($stripeSession['status']),
            'fiat_amount'   => null,
            'crypto_amount' => $cryptoAmount,
            'metadata'      => [
                'provider'      => 'stripe_bridge',
                'stripe_status' => $stripeSession['status'],
            ],
        ];
    }

    public function getSupportedCurrencies(): array
    {
        return $this->service->getSupportedCurrencies();
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
        return function (string $rawBody, string $signatureHeader): bool {
            $secret = (string) config('services.stripe.bridge_webhook_secret', '');

            if ($secret === '') {
                return ! app()->environment('production');
            }

            if ($signatureHeader === '') {
                return false;
            }

            /** @var array<string, list<string>> $parts */
            $parts = [];
            foreach (explode(',', $signatureHeader) as $element) {
                $element = trim($element);
                if ($element === '') {
                    continue;
                }
                $pair = array_pad(explode('=', $element, 2), 2, '');
                $parts[$pair[0]][] = $pair[1];
            }

            $timestamp = (int) ($parts['t'][0] ?? 0);
            $signatures = $parts['v1'] ?? [];

            if ($timestamp === 0 || $signatures === []) {
                return false;
            }

            if (abs(time() - $timestamp) > 300) {
                return false;
            }

            $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);

            foreach ($signatures as $candidate) {
                if (hash_equals($expected, $candidate)) {
                    return true;
                }
            }

            return false;
        };
    }

    public function getWebhookSignatureHeader(): string
    {
        return 'Stripe-Signature';
    }

    public function normalizeWebhookPayload(array $payload): ?array
    {
        $eventType = (string) ($payload['type'] ?? '');
        if (! str_starts_with($eventType, 'crypto_onramp_session.')) {
            return null;
        }

        $object = $payload['data']['object'] ?? null;
        if (! is_array($object)) {
            return null;
        }

        $sessionId = (string) ($object['id'] ?? '');
        if ($sessionId === '') {
            return null;
        }

        $stripeStatus = (string) ($object['status'] ?? '');
        if ($stripeStatus === '') {
            return null;
        }

        $cryptoAmount = null;
        if (isset($object['destination_amount']) && is_numeric($object['destination_amount'])) {
            $cryptoAmount = bcadd((string) $object['destination_amount'], '0', 8);
        }

        return [
            'session_id'    => $sessionId,
            'status'        => $this->service->mapStripeStatus($stripeStatus),
            'crypto_amount' => $cryptoAmount,
            'raw'           => $object,
        ];
    }

    public function getName(): string
    {
        return 'stripe_bridge';
    }
}
