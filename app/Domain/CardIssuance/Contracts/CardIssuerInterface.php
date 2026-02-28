<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Contracts;

use App\Domain\CardIssuance\Enums\CardNetwork;
use App\Domain\CardIssuance\Enums\WalletType;
use App\Domain\CardIssuance\ValueObjects\CardTransaction;
use App\Domain\CardIssuance\ValueObjects\ProvisioningData;
use App\Domain\CardIssuance\ValueObjects\VirtualCard;

/**
 * Interface for card issuer adapters (Marqeta, Lithic, Stripe Issuing).
 */
interface CardIssuerInterface
{
    /**
     * Create a new virtual card for a user.
     *
     * @param array<string, mixed> $metadata
     */
    public function createCard(
        string $userId,
        string $cardholderName,
        array $metadata = [],
        ?CardNetwork $network = null,
        ?string $label = null,
    ): VirtualCard;

    /**
     * Get provisioning data for Apple Pay / Google Pay.
     *
     * @param array<string> $certificates
     * @return ProvisioningData Data to pass directly to native wallet APIs
     */
    public function getProvisioningData(
        string $cardToken,
        WalletType $walletType,
        string $deviceId,
        array $certificates = []
    ): ProvisioningData;

    /**
     * Freeze a card (temporary block).
     */
    public function freezeCard(string $cardToken): bool;

    /**
     * Unfreeze a previously frozen card.
     */
    public function unfreezeCard(string $cardToken): bool;

    /**
     * Permanently cancel a card.
     */
    public function cancelCard(string $cardToken, string $reason): bool;

    /**
     * Get card details by token.
     */
    public function getCard(string $cardToken): ?VirtualCard;

    /**
     * List all cards for a given user.
     *
     * @return array<VirtualCard>
     */
    public function listUserCards(string $userId): array;

    /**
     * Get transaction history for a card.
     *
     * @return array{transactions: array<CardTransaction>, next_cursor: string|null}
     */
    public function getTransactions(string $cardToken, int $limit = 20, ?string $cursor = null): array;

    /**
     * Get the issuer name for identification.
     */
    public function getName(): string;
}
