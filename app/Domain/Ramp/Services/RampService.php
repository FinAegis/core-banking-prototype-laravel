<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Services;

use App\Domain\Ramp\Contracts\RampProviderInterface;
use App\Domain\Ramp\Exceptions\InvalidWebhookSignatureException;
use App\Models\RampSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RampService
{
    public function __construct(
        private readonly RampProviderInterface $provider,
    ) {
    }

    /**
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
     * Refresh session status from the provider if non-terminal. Wraps the
     * update in a row lock and checks for terminal state after the remote
     * call, so a webhook that arrived during the fetch is not clobbered.
     */
    public function getSessionStatus(RampSession $session): RampSession
    {
        if (! in_array($session->status, [RampSession::STATUS_PENDING, RampSession::STATUS_PROCESSING], true)) {
            return $session;
        }

        if (! $session->provider_session_id) {
            return $session;
        }

        $providerStatus = $this->provider->getSessionStatus($session->provider_session_id);

        return DB::transaction(function () use ($session, $providerStatus) {
            /** @var RampSession $fresh */
            $fresh = RampSession::where('id', $session->id)->lockForUpdate()->first();

            if (
                in_array($fresh->status, [
                RampSession::STATUS_COMPLETED,
                RampSession::STATUS_FAILED,
                RampSession::STATUS_EXPIRED,
                ], true)
            ) {
                return $fresh;
            }

            $fresh->update([
                'status'        => $providerStatus['status'],
                'crypto_amount' => $providerStatus['crypto_amount'] ?? $fresh->crypto_amount,
                'metadata'      => array_merge($fresh->metadata ?? [], $providerStatus['metadata']),
            ]);

            return $fresh;
        });
    }

    /**
     * Handle a webhook from a provider. Verifies the signature first (never
     * parses untrusted JSON before verification), then delegates payload shape
     * handling to the provider's own normalizeWebhookPayload().
     *
     * @throws InvalidWebhookSignatureException
     * @throws RuntimeException
     */
    public function handleWebhook(
        RampProviderInterface $provider,
        string $rawBody,
        string $signatureHeader,
    ): void {
        $validator = $provider->getWebhookValidator();
        if (! $validator($rawBody, $signatureHeader)) {
            throw new InvalidWebhookSignatureException();
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            throw new RuntimeException('Webhook body is not valid JSON');
        }

        $normalized = $provider->normalizeWebhookPayload($payload);
        if ($normalized === null) {
            return;
        }

        DB::transaction(function () use ($provider, $normalized, $payload) {
            /** @var RampSession|null $session */
            $session = RampSession::where('provider', $provider->getName())
                ->where('provider_session_id', $normalized['session_id'])
                ->lockForUpdate()
                ->first();

            if (! $session) {
                Log::warning('Ramp webhook: session not found', [
                    'provider'   => $provider->getName(),
                    'session_id' => $normalized['session_id'],
                ]);

                return;
            }

            if (
                in_array($session->status, [
                RampSession::STATUS_COMPLETED,
                RampSession::STATUS_FAILED,
                RampSession::STATUS_EXPIRED,
                ], true)
            ) {
                Log::info('Ramp webhook skipped — session already terminal', [
                    'session_id' => $session->id,
                    'status'     => $session->status,
                ]);

                return;
            }

            $cryptoAmount = $normalized['crypto_amount'] !== null
                ? (float) $normalized['crypto_amount']
                : $session->crypto_amount;

            $previousStatus = $session->status;

            $session->update([
                'status'        => $normalized['status'],
                'crypto_amount' => $cryptoAmount,
                'metadata'      => array_merge($session->metadata ?? [], [
                    'webhook' => [
                        'received_at'        => now()->toIso8601String(),
                        'event'              => $payload['type'] ?? null,
                        'snapshot'           => $normalized['raw'],
                        'session_transition' => $previousStatus . ' → ' . $normalized['status'],
                    ],
                ]),
            ]);

            Log::info('Ramp webhook processed', [
                'session_id'         => $session->id,
                'provider'           => $provider->getName(),
                'status'             => $normalized['status'],
                'stripe_event_type'  => $payload['type'] ?? null,
                'session_transition' => $previousStatus . ' → ' . $normalized['status'],
            ]);
        });
    }

    /**
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

        $supported = $this->provider->getSupportedCurrencies();

        if (! in_array($fiatCurrency, $supported['fiatCurrencies'], true)) {
            throw new RuntimeException(
                "{$fiatCurrency} is not supported by {$this->provider->getName()}. "
                . 'Supported: ' . implode(', ', $supported['fiatCurrencies'])
            );
        }

        if (! in_array($cryptoCurrency, $supported['cryptoCurrencies'], true)) {
            throw new RuntimeException(
                "{$cryptoCurrency} is not available through {$this->provider->getName()}. "
                . 'Supported: ' . implode(', ', $supported['cryptoCurrencies'])
            );
        }

        /** @var numeric-string $minStr */
        $minStr = (string) $supported['limits']['minAmount'];
        /** @var numeric-string $maxStr */
        $maxStr = (string) $supported['limits']['maxAmount'];
        $min = bcadd($minStr, '0', 2);
        $max = bcadd($maxStr, '0', 2);
        /** @var numeric-string $fiatAmount */
        $amount = bcadd($fiatAmount, '0', 2);

        if (bccomp($amount, $min, 2) < 0 || bccomp($amount, $max, 2) > 0) {
            throw new RuntimeException("Amount must be between {$fiatCurrency} {$min} and {$fiatCurrency} {$max}.");
        }
    }
}
