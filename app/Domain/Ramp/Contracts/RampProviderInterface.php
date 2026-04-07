<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Contracts;

interface RampProviderInterface
{
    /**
     * Create a ramp session via checkout API (not widget).
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
     * Get supported fiat/crypto currency pairs.
     *
     * @return array<int, array{fiat: string, crypto: string}>
     */
    public function getSupportedCurrencies(): array;

    /**
     * Get all quotes from aggregated providers for a ramp transaction.
     *
     * @return array<int, array{provider_name: string, quote_id: string|null, fiat_amount: float, crypto_amount: float, exchange_rate: float, fee: float, network_fee: float, fee_currency: string, payment_methods: array<string>}>
     */
    public function getQuotes(string $type, string $fiatCurrency, string $fiatAmount, string $cryptoCurrency): array;

    /**
     * Get the webhook validator for this provider.
     *
     * @return callable(string $payload, string $signature): bool
     */
    public function getWebhookValidator(): callable;

    /**
     * Get the provider name.
     */
    public function getName(): string;
}
