<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Contracts;

interface RampProviderInterface
{
    /**
     * Create a ramp session via checkout API.
     *
     * @param  array{type: string, fiat_currency: string, fiat_amount: string, crypto_currency: string, wallet_address: string, quote_id: string|null}  $params
     * @return array{session_id: string, checkout_url: string|null, metadata: array<string, mixed>}
     */
    public function createSession(array $params): array;

    /**
     * Get the status of a ramp session.
     *
     * @return array{status: string, fiat_amount: float|null, crypto_amount: float|null, metadata: array<string, mixed>}
     */
    public function getSessionStatus(string $sessionId): array;

    /**
     * Get supported capabilities (fiat currencies, crypto currencies, modes, and limits)
     * for this provider. Used by RampService::validateRampParams and RampController::supported.
     *
     * @return array{fiatCurrencies: list<string>, cryptoCurrencies: list<string>, modes: list<string>, limits: array{minAmount: int, maxAmount: int, dailyLimit: int}}
     */
    public function getSupportedCurrencies(): array;

    /**
     * Get all quotes from the provider for a ramp transaction.
     *
     * @return array<int, array{provider_name: string, quote_id: string|null, fiat_amount: float, crypto_amount: float, exchange_rate: float, fee: float, network_fee: float, fee_currency: string, payment_methods: array<string>}>
     */
    public function getQuotes(string $type, string $fiatCurrency, string $fiatAmount, string $cryptoCurrency): array;

    /**
     * Get the webhook validator for this provider.
     *
     * The callable receives the raw HTTP body bytes (NOT re-encoded) and the
     * full signature header string. Each provider parses its own header format.
     * Must use hash_equals() for constant-time comparison.
     *
     * @return callable(string $rawBody, string $signatureHeader): bool
     */
    public function getWebhookValidator(): callable;

    /**
     * Return the HTTP header name the validator should read. The webhook controller
     * uses this to fetch the right header from the incoming Request.
     *
     * Example: "Stripe-Signature", "X-Onramper-Webhook-Signature".
     */
    public function getWebhookSignatureHeader(): string;

    /**
     * Unwrap a provider-specific webhook event envelope into a canonical shape.
     * Providers that wrap their payload (Stripe) unwrap it; flat-payload providers
     * (Onramper) return their fields in the canonical shape.
     *
     * Return null to explicitly ignore the event (e.g. Stripe event types we
     * don't care about). Returning null is not an error — the controller will
     * still return 200.
     *
     * The `status` field MUST be one of RampSession::STATUS_* constants — the
     * provider owns the mapping from its vendor-specific status vocabulary.
     *
     * @param  array<string, mixed>  $payload  Parsed JSON body (already decoded after signature verification)
     * @return array{session_id: string, status: string, crypto_amount: string|null, raw: array<string, mixed>}|null
     */
    public function normalizeWebhookPayload(array $payload): ?array;

    /**
     * Get the provider name. Used as the stable identifier in routes, logs,
     * and the `provider` column of ramp_sessions.
     */
    public function getName(): string;
}
