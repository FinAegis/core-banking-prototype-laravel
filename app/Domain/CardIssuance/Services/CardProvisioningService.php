<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Services;

use App\Domain\CardIssuance\Contracts\CardIssuerInterface;
use App\Domain\CardIssuance\Enums\WalletType;
use App\Domain\CardIssuance\Events\CardProvisioned;
use App\Domain\CardIssuance\ValueObjects\ProvisioningData;
use App\Domain\CardIssuance\ValueObjects\VirtualCard;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Service for provisioning virtual cards to Apple Pay / Google Pay.
 */
class CardProvisioningService
{
    public function __construct(
        private readonly CardIssuerInterface $cardIssuer,
    ) {
    }

    /**
     * Create a new virtual card for a user.
     *
     * @param array<string, mixed> $metadata
     */
    public function createCard(
        string $userId,
        string $cardholderName,
        array $metadata = []
    ): VirtualCard {
        Log::info('Creating virtual card', [
            'user_id' => $userId,
            'issuer'  => $this->cardIssuer->getName(),
        ]);

        $card = $this->cardIssuer->createCard($userId, $cardholderName, $metadata);

        Log::info('Virtual card created', [
            'user_id'    => $userId,
            'card_token' => $card->cardToken,
            'last4'      => $card->last4,
        ]);

        return $card;
    }

    /**
     * Get provisioning data for adding card to Apple Pay / Google Pay.
     *
     * This data must be passed DIRECTLY to native wallet APIs without modification.
     *
     * @param array<string> $certificates
     */
    public function getProvisioningData(
        string $userId,
        string $cardToken,
        WalletType $walletType,
        string $deviceId,
        array $certificates = []
    ): ProvisioningData {
        Log::info('Getting provisioning data', [
            'user_id'     => $userId,
            'card_token'  => $cardToken,
            'wallet_type' => $walletType->value,
            'device_id'   => $deviceId,
        ]);

        // Verify card exists and belongs to user
        $card = $this->cardIssuer->getCard($cardToken);
        if ($card === null) {
            throw new RuntimeException('Card not found');
        }

        if (! $card->isUsable()) {
            throw new RuntimeException('Card is not usable');
        }

        // Get provisioning data from issuer
        $provisioningData = $this->cardIssuer->getProvisioningData(
            $cardToken,
            $walletType,
            $deviceId,
            $certificates
        );

        Event::dispatch(new CardProvisioned(
            userId: $userId,
            cardToken: $cardToken,
            walletType: $walletType,
            deviceId: $deviceId,
        ));

        Log::info('Provisioning data generated', [
            'user_id'     => $userId,
            'card_token'  => $cardToken,
            'wallet_type' => $walletType->value,
        ]);

        return $provisioningData;
    }

    /**
     * Get a card by token.
     */
    public function getCard(string $cardToken): ?VirtualCard
    {
        return $this->cardIssuer->getCard($cardToken);
    }

    /**
     * Freeze a card.
     */
    public function freezeCard(string $cardToken): bool
    {
        return $this->cardIssuer->freezeCard($cardToken);
    }

    /**
     * Unfreeze a card.
     */
    public function unfreezeCard(string $cardToken): bool
    {
        return $this->cardIssuer->unfreezeCard($cardToken);
    }

    /**
     * Cancel a card permanently.
     */
    public function cancelCard(string $cardToken, string $reason): bool
    {
        return $this->cardIssuer->cancelCard($cardToken, $reason);
    }
}
