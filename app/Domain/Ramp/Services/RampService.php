<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Services;

use App\Domain\Ramp\Contracts\RampProviderInterface;
use App\Domain\Ramp\Exceptions\InvalidWebhookSignatureException;
use App\Domain\Ramp\Exceptions\QuoteExpiredException;
use App\Models\RampSession;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RampService
{
    /**
     * Per ADR-0006: Free tier pays 0.75% Zelta markup on top of the provider's
     * own fee. Pro pays 0%. The markup is collected via Bridge developer fees
     * at the provider layer, but we show it as a discrete line item in the
     * quote so the user knows what they're paying for.
     */
    private const ZELTA_MARKUP_BPS_FREE = 75;

    private const ZELTA_MARKUP_BPS_PRO = 0;

    /**
     * Validity window for stateless quote_ids (matches the `validUntil`
     * returned from GET /api/v1/ramp/quotes). Quote_ids in the
     * `qt_<timestamp>_<random>` format older than this are rejected at
     * createSession with QuoteExpiredException → ERR_RAMP_QUOTE_EXPIRED.
     */
    private const QUOTE_TTL_SECONDS = 60;

    /**
     * @param  Closure(User): string|null  $tierResolver  Returns 'free' or 'pro' for the given user.
     *                                                    Null defaults every user to 'free' (no markup waiver).
     */
    public function __construct(
        private readonly RampProviderInterface $provider,
        private readonly ?Closure $tierResolver = null,
    ) {
    }

    /**
     * @return array{quotes: array<int, array<string, mixed>>, provider: string, valid_until: string}
     */
    public function getQuotes(
        string $type,
        string $fiatCurrency,
        string $fiatAmount,
        string $cryptoCurrency,
        ?User $user = null,
    ): array {
        $this->validateRampParams($type, $fiatCurrency, $fiatAmount, $cryptoCurrency);

        $quotes = $this->provider->getQuotes($type, $fiatCurrency, $fiatAmount, $cryptoCurrency);

        $tier = $this->resolveTier($user);
        $quotes = array_map(
            fn (array $quote): array => $this->withZeltaMarkup($quote, $fiatAmount, $fiatCurrency, $tier),
            $quotes,
        );

        return [
            'quotes'      => $quotes,
            'provider'    => $this->provider->getName(),
            'valid_until' => now()->addSeconds(60)->toIso8601String(),
        ];
    }

    /**
     * Layer the Zelta markup on top of a provider quote per ADR-0006. The
     * provider's `fee` is the provider's own fee (e.g. Bridge's 10 bps); we
     * add `zeltaMarkup` and `total` as the rendered cost to the user.
     *
     * FX spread is reported as a discrete line item; the provider sets it on
     * the quote when conversion is needed (passes through verbatim — we
     * never mark up FX per §2.6).
     *
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    private function withZeltaMarkup(array $quote, string $fiatAmount, string $fiatCurrency, string $tier): array
    {
        /** @var numeric-string $fiat */
        $fiat = $fiatAmount;
        $fiat = bcadd($fiat, '0', 4);

        $markupBps = $tier === 'pro' ? self::ZELTA_MARKUP_BPS_PRO : self::ZELTA_MARKUP_BPS_FREE;
        // markup = fiat * (bps / 10000)
        $markup = bcdiv(bcmul($fiat, (string) $markupBps, 4), '10000', 4);
        $markup = bcadd($markup, '0', 2);

        $providerFeeStr = $this->toNumericMoney($quote['fee'] ?? 0);
        $networkFeeStr = $this->toNumericMoney($quote['network_fee'] ?? 0);
        $fxSpreadStr = $this->toNumericMoney($quote['fx_spread'] ?? 0);

        $total = bcadd($providerFeeStr, $networkFeeStr, 2);
        $total = bcadd($total, $fxSpreadStr, 2);
        $total = bcadd($total, $markup, 2);

        $quote['fees'] = [
            'providerFee'       => $providerFeeStr,
            'networkFee'        => $networkFeeStr,
            'fxSpread'          => $fxSpreadStr,
            'zeltaMarkup'       => $markup,
            'zeltaMarkupWaived' => $tier === 'pro',
            'zeltaMarkupTier'   => $tier,
            'feeCurrency'       => $fiatCurrency,
            'total'             => $total,
        ];

        return $quote;
    }

    /**
     * Normalize any numeric scalar to a 2-decimal numeric-string suitable for
     * bcmath. Provider interfaces declare these as float, so we convert via
     * the bcmath roundtrip — start from is_numeric() so PHPStan accepts the
     * input as numeric, then bcadd-normalize down to 2 decimals.
     *
     * @return numeric-string
     */
    private function toNumericMoney(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '0.00';
        }

        // number_format guarantees the literal output is a numeric-string,
        // but its return type is non-empty-string. Use the value through
        // bcmath directly: cast to string is already safe (is_numeric).
        $str = (string) $value;
        if (! preg_match('/^-?\d+(\.\d+)?$/', $str)) {
            return '0.00';
        }

        return bcadd($str, '0', 2);
    }

    /**
     * Reject expired quote_ids of the form `qt_<unix_timestamp>_<random>`
     * (issued by BridgeProvider::getQuotes — see also docs/adr/0006).
     * Other providers' quote_id formats pass through unchecked — their
     * freshness semantics are owned by them.
     *
     * @throws QuoteExpiredException
     */
    private function validateQuoteFreshness(?string $quoteId): void
    {
        if ($quoteId === null || ! preg_match('/^qt_(\d+)_[0-9a-f]+$/', $quoteId, $matches)) {
            return;
        }

        $issuedAt = (int) $matches[1];
        $now = time();

        if (($now - $issuedAt) > self::QUOTE_TTL_SECONDS) {
            throw new QuoteExpiredException($issuedAt, $now, self::QUOTE_TTL_SECONDS);
        }
    }

    private function resolveTier(?User $user): string
    {
        if ($user === null || $this->tierResolver === null) {
            return 'free';
        }

        $tier = ($this->tierResolver)($user);

        return $tier === 'pro' ? 'pro' : 'free';
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
        $this->validateQuoteFreshness($quoteId);

        // user_id is part of the additive shape per RampProviderInterface
        // (PR #1091). BridgeProvider requires it to scope per-user state
        // like the bridge_customers row; other providers may ignore it.
        // Latent gap fix: PR #1091 documented the param on the interface
        // but never threaded it through here; first real Bridge session
        // attempt would have failed.
        $providerResult = $this->provider->createSession([
            'type'            => $type,
            'fiat_currency'   => $fiatCurrency,
            'fiat_amount'     => $fiatAmount,
            'crypto_currency' => $cryptoCurrency,
            'wallet_address'  => $walletAddress,
            'quote_id'        => $quoteId,
            'user_id'         => $user->id,
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
