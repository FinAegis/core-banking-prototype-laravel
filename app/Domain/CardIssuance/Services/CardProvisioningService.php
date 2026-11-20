<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Services;

use App\Domain\CardIssuance\Contracts\CardIssuerInterface;
use App\Domain\CardIssuance\Enums\CardNetwork;
use App\Domain\CardIssuance\Enums\CardStatus;
use App\Domain\CardIssuance\Enums\WalletType;
use App\Domain\CardIssuance\Events\CardProvisioned;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\CardStaffFreeze;
use App\Domain\CardIssuance\ValueObjects\ProvisioningData;
use App\Domain\CardIssuance\ValueObjects\VirtualCard;
use App\Support\StaffRoles;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

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
        array $metadata = [],
        ?CardNetwork $network = null,
        ?string $label = null,
    ): VirtualCard {
        Log::info('Creating virtual card', [
            'user_id' => $userId,
            'issuer'  => $this->cardIssuer->getName(),
        ]);

        $card = $this->cardIssuer->createCard($userId, $cardholderName, $metadata, $network, $label);

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
     * Whether the card belongs to the user: a local card record links the
     * issuer token to the user, the issuer lists the card among the user's
     * cards, or the issuer's own record of the card names the user as its
     * owner. The last check covers cancelled cards (left out of the
     * listing) and cards beyond the first page of the issuer's listing.
     */
    public function isOwnedBy(string $cardToken, string $userId): bool
    {
        if ($cardToken === '' || $userId === '') {
            return false;
        }

        $linked = Card::query()
            ->where('issuer_card_token', $cardToken)
            ->where('user_id', $userId)
            ->exists();

        if ($linked) {
            return true;
        }

        foreach ($this->cardIssuer->listUserCards($userId) as $card) {
            if (hash_equals($card->cardToken, $cardToken)) {
                return true;
            }
        }

        try {
            $card = $this->cardIssuer->getCard($cardToken);
        } catch (Throwable $e) {
            Log::warning('Card ownership lookup failed', [
                'issuer' => $this->cardIssuer->getName(),
                'error'  => $e->getMessage(),
            ]);

            return false;
        }

        $owner = $card === null ? '' : $this->issuerSideOwner($card);

        return $owner !== '' && hash_equals($owner, $userId);
    }

    /**
     * The user id the issuer itself holds as the card's owner, set at card
     * creation from the platform user id and never from caller metadata:
     * Marqeta's `user_token` (mapped by the adapter) and the demo issuer's
     * `user_id`. Other issuers keep a local card record instead.
     */
    private function issuerSideOwner(VirtualCard $card): string
    {
        $owner = match ($this->cardIssuer->getName()) {
            'marqeta' => $card->metadata['user_token'] ?? '',
            'demo'    => $card->metadata['user_id'] ?? '',
            default   => '',
        };

        return is_scalar($owner) ? (string) $owner : '';
    }

    /**
     * Freeze a card. When staff (StaffRoles::OPERATORS) freeze it, the
     * freeze is recorded, so the owner cannot lift it; staff also take over
     * a freeze the owner placed earlier.
     */
    public function freezeCard(string $cardToken, ?Authenticatable $actor = null): bool
    {
        if (! StaffRoles::userHasAny($actor, StaffRoles::OPERATORS)) {
            return $this->cardIssuer->freezeCard($cardToken);
        }

        // Recorded before the issuer call, so the owner cannot lift the
        // freeze in between.
        $alreadyHeld = CardStaffFreeze::isHeld($cardToken);
        $actorId = $actor?->getAuthIdentifier();
        CardStaffFreeze::place($cardToken, is_numeric($actorId) ? (int) $actorId : null);

        if ($this->cardIssuer->getCard($cardToken)?->status === CardStatus::FROZEN) {
            return true;
        }

        $frozen = false;

        try {
            $frozen = $this->cardIssuer->freezeCard($cardToken);
        } finally {
            if (! $frozen && ! $alreadyHeld) {
                CardStaffFreeze::lift($cardToken);
            }
        }

        return $frozen;
    }

    /**
     * Whether the user may lift the card's current freeze: staff
     * (StaffRoles::OPERATORS) always, anyone else only when no staff or
     * issuer freeze is recorded. Card ownership is checked by the caller.
     */
    public function mayUnfreeze(string $cardToken, ?Authenticatable $actor): bool
    {
        return StaffRoles::userHasAny($actor, StaffRoles::OPERATORS)
            || ! CardStaffFreeze::isHeld($cardToken);
    }

    /**
     * Unfreeze a card. Check {@see mayUnfreeze()} first.
     */
    public function unfreezeCard(string $cardToken): bool
    {
        $unfrozen = $this->cardIssuer->unfreezeCard($cardToken);

        if ($unfrozen) {
            CardStaffFreeze::lift($cardToken);
        }

        return $unfrozen;
    }

    /**
     * Cancel a card permanently.
     */
    public function cancelCard(string $cardToken, string $reason): bool
    {
        return $this->cardIssuer->cancelCard($cardToken, $reason);
    }

    /**
     * List all non-cancelled cards for a user.
     *
     * @return array<VirtualCard>
     */
    public function listUserCards(string $userId): array
    {
        return $this->cardIssuer->listUserCards($userId);
    }

    /**
     * Get transaction history for a card.
     *
     * @return array{transactions: array<\App\Domain\CardIssuance\ValueObjects\CardTransaction>, next_cursor: string|null}
     */
    public function getTransactions(string $cardToken, int $limit = 20, ?string $cursor = null): array
    {
        return $this->cardIssuer->getTransactions($cardToken, $limit, $cursor);
    }
}
