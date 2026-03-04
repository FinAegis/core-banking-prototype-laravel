<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Contracts;

interface RampProviderInterface
{
    /**
     * Create a ramp session (returns provider widget URL or config).
     *
     * @param  array{type: string, fiat_currency: string, fiat_amount: float, crypto_currency: string, wallet_address: string}  $params
     * @return array{session_id: string, redirect_url: string|null, widget_config: array<string, mixed>|null}
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
     * Get a quote for a ramp transaction.
     *
     * @return array{fiat_amount: float, crypto_amount: float, exchange_rate: float, fee: float, fee_currency: string}
     */
    public function getQuote(string $type, string $fiatCurrency, float $fiatAmount, string $cryptoCurrency): array;

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
