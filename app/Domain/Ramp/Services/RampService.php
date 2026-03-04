<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Services;

use App\Domain\Ramp\Contracts\RampProviderInterface;
use App\Models\RampSession;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RampService
{
    public function __construct(
        private readonly RampProviderInterface $provider,
    ) {
    }

    /**
     * Get a quote for a ramp transaction.
     *
     * @return array{fiat_amount: float, crypto_amount: float, exchange_rate: float, fee: float, fee_currency: string, provider: string}
     */
    public function getQuote(string $type, string $fiatCurrency, float $fiatAmount, string $cryptoCurrency): array
    {
        $this->validateRampParams($type, $fiatCurrency, $fiatAmount, $cryptoCurrency);

        $quote = $this->provider->getQuote($type, $fiatCurrency, $fiatAmount, $cryptoCurrency);
        $quote['provider'] = $this->provider->getName();
        $quote['valid_until'] = now()->addSeconds(60)->toIso8601String();

        return $quote;
    }

    /**
     * Create a ramp session.
     */
    public function createSession(
        User $user,
        string $type,
        string $fiatCurrency,
        float $fiatAmount,
        string $cryptoCurrency,
        string $walletAddress,
    ): RampSession {
        $this->validateRampParams($type, $fiatCurrency, $fiatAmount, $cryptoCurrency);

        $providerResult = $this->provider->createSession([
            'type'            => $type,
            'fiat_currency'   => $fiatCurrency,
            'fiat_amount'     => $fiatAmount,
            'crypto_currency' => $cryptoCurrency,
            'wallet_address'  => $walletAddress,
        ]);

        $session = RampSession::create([
            'user_id'             => $user->id,
            'provider'            => $this->provider->getName(),
            'type'                => $type,
            'fiat_currency'       => $fiatCurrency,
            'fiat_amount'         => $fiatAmount,
            'crypto_currency'     => $cryptoCurrency,
            'status'              => RampSession::STATUS_PENDING,
            'provider_session_id' => $providerResult['session_id'],
            'metadata'            => [
                'redirect_url'  => $providerResult['redirect_url'],
                'widget_config' => $providerResult['widget_config'],
            ],
        ]);

        Log::info('Ramp session created', [
            'session_id' => $session->id,
            'user_id'    => $user->id,
            'type'       => $type,
            'provider'   => $this->provider->getName(),
        ]);

        return $session;
    }

    /**
     * Get session status (refreshes from provider if pending/processing).
     */
    public function getSessionStatus(RampSession $session): RampSession
    {
        if (in_array($session->status, [RampSession::STATUS_PENDING, RampSession::STATUS_PROCESSING], true)
            && $session->provider_session_id) {
            $providerStatus = $this->provider->getSessionStatus($session->provider_session_id);

            $session->update([
                'status'        => $providerStatus['status'],
                'crypto_amount' => $providerStatus['crypto_amount'],
                'metadata'      => array_merge($session->metadata ?? [], $providerStatus['metadata']),
            ]);
        }

        return $session;
    }

    /**
     * Handle a webhook from a provider.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(string $provider, array $payload, string $signature): void
    {
        $validator = $this->provider->getWebhookValidator();

        if (! $validator(json_encode($payload), $signature)) {
            throw new RuntimeException('Invalid webhook signature');
        }

        $sessionId = $payload['session_id'] ?? $payload['id'] ?? null;
        if (! $sessionId) {
            Log::warning('Ramp webhook missing session_id', ['provider' => $provider]);

            return;
        }

        $session = RampSession::where('provider_session_id', $sessionId)->first();
        if (! $session) {
            Log::warning('Ramp session not found for webhook', [
                'provider'   => $provider,
                'session_id' => $sessionId,
            ]);

            return;
        }

        $status = $payload['status'] ?? 'processing';
        $session->update([
            'status'        => $status,
            'crypto_amount' => $payload['crypto_amount'] ?? $session->crypto_amount,
            'metadata'      => array_merge($session->metadata ?? [], ['webhook' => $payload]),
        ]);

        Log::info('Ramp webhook processed', [
            'session_id' => $session->id,
            'provider'   => $provider,
            'status'     => $status,
        ]);
    }

    /**
     * Get user's ramp sessions.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, RampSession>
     */
    public function getUserSessions(User $user, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return RampSession::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    private function validateRampParams(string $type, string $fiatCurrency, float $fiatAmount, string $cryptoCurrency): void
    {
        if (! in_array($type, ['on', 'off'], true)) {
            throw new RuntimeException('Invalid transaction type. Use "on" for buying crypto or "off" for selling.');
        }

        $supportedFiat = config('ramp.supported_fiat', []);
        if (! in_array($fiatCurrency, $supportedFiat, true)) {
            throw new RuntimeException("{$fiatCurrency} is not a supported currency. Please try USD, EUR, or GBP.");
        }

        $supportedCrypto = config('ramp.supported_crypto', []);
        if (! in_array($cryptoCurrency, $supportedCrypto, true)) {
            throw new RuntimeException("{$cryptoCurrency} is not available for trading at this time.");
        }

        $min = (float) config('ramp.limits.min_fiat_amount', 10);
        $max = (float) config('ramp.limits.max_fiat_amount', 10000);
        if ($fiatAmount < $min || $fiatAmount > $max) {
            throw new RuntimeException("Amount must be between \${$min} and \${$max}.");
        }
    }
}
