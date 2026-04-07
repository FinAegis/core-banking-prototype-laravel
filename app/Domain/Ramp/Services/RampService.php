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
     * Get all quotes from aggregated providers.
     *
     * @return array{quotes: array<int, array<string, mixed>>, provider: string, valid_until: string}
     */
    public function getQuotes(string $type, string $fiatCurrency, string $fiatAmount, string $cryptoCurrency): array
    {
        $this->validateRampParams($type, $fiatCurrency, $fiatAmount, $cryptoCurrency);

        $quotes = $this->provider->getQuotes($type, $fiatCurrency, $fiatAmount, $cryptoCurrency);

        return [
            'quotes'      => $quotes,
            'provider'    => $this->provider->getName(),
            'valid_until' => now()->addSeconds(60)->toIso8601String(),
        ];
    }

    /**
     * Create a ramp session using a selected quote.
     */
    public function createSession(
        User $user,
        string $type,
        string $fiatCurrency,
        string $fiatAmount,
        string $cryptoCurrency,
        string $walletAddress,
        ?string $quoteId = null,
    ): RampSession {
        $this->validateRampParams($type, $fiatCurrency, $fiatAmount, $cryptoCurrency);

        $providerResult = $this->provider->createSession([
            'type'            => $type,
            'fiat_currency'   => $fiatCurrency,
            'fiat_amount'     => $fiatAmount,
            'crypto_currency' => $cryptoCurrency,
            'wallet_address'  => $walletAddress,
            'quote_id'        => $quoteId,
        ]);

        $sessionData = [
            'user_id'             => $user->id,
            'provider'            => $this->provider->getName(),
            'type'                => $type,
            'fiat_currency'       => $fiatCurrency,
            'fiat_amount'         => $fiatAmount,
            'crypto_currency'     => $cryptoCurrency,
            'wallet_address'      => $walletAddress,
            'status'              => RampSession::STATUS_PENDING,
            'provider_session_id' => $providerResult['session_id'],
            'metadata'            => [
                'checkout_url' => $providerResult['checkout_url'],
                'provider'     => $providerResult['metadata'] ?? [],
            ],
        ];

        // Store Stripe Bridge specific fields
        $metadata = $providerResult['metadata'] ?? [];
        if (isset($metadata['stripe_session_id'])) {
            $sessionData['stripe_session_id'] = $metadata['stripe_session_id'];
        }
        if (isset($metadata['client_secret'])) {
            $sessionData['stripe_client_secret'] = $metadata['client_secret'];
        }

        $session = RampSession::create($sessionData);

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
        if (
            in_array($session->status, [RampSession::STATUS_PENDING, RampSession::STATUS_PROCESSING], true)
            && $session->provider_session_id
        ) {
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

        if (! $validator((string) json_encode($payload), $signature)) {
            throw new RuntimeException('Invalid webhook signature');
        }

        $sessionId = $payload['session_id'] ?? $payload['partnerContext'] ?? $payload['id'] ?? null;
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

        // Verify the webhook provider matches the session provider
        if ($session->provider !== $provider) {
            Log::warning('Ramp webhook provider mismatch', [
                'expected'   => $session->provider,
                'received'   => $provider,
                'session_id' => $session->id,
            ]);

            return;
        }

        // Idempotency: don't overwrite terminal states
        if (in_array($session->status, [RampSession::STATUS_COMPLETED, RampSession::STATUS_FAILED, RampSession::STATUS_EXPIRED], true)) {
            Log::info('Ramp webhook skipped — session already terminal', [
                'session_id' => $session->id,
                'status'     => $session->status,
            ]);

            return;
        }

        $status = $this->normalizeWebhookStatus($payload['status'] ?? 'processing');
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

    private function validateRampParams(string $type, string $fiatCurrency, string $fiatAmount, string $cryptoCurrency): void
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

        /** @var numeric-string $minStr */
        $minStr = (string) config('ramp.limits.min_fiat_amount', 10);
        /** @var numeric-string $maxStr */
        $maxStr = (string) config('ramp.limits.max_fiat_amount', 10000);
        $min = bcadd($minStr, '0', 2);
        $max = bcadd($maxStr, '0', 2);
        /** @var numeric-string $fiatAmount */
        $amount = bcadd($fiatAmount, '0', 2);
        if (bccomp($amount, $min, 2) < 0 || bccomp($amount, $max, 2) > 0) {
            throw new RuntimeException("Amount must be between \${$min} and \${$max}.");
        }
    }

    /**
     * Normalize webhook status to known session status constants.
     */
    private function normalizeWebhookStatus(string $status): string
    {
        return match (strtolower($status)) {
            'completed', 'success', 'done', 'fulfilled' => RampSession::STATUS_COMPLETED,
            'failed', 'error', 'cancelled', 'payment_failed' => RampSession::STATUS_FAILED,
            'expired' => RampSession::STATUS_EXPIRED,
            'pending', 'new', 'created', 'initialized' => RampSession::STATUS_PENDING,
            'payment_pending', 'payment_complete' => RampSession::STATUS_PROCESSING,
            default => RampSession::STATUS_PROCESSING,
        };
    }
}
