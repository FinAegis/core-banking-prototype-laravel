<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Adapters;

use App\Domain\CardIssuance\Contracts\CardIssuerInterface;
use App\Domain\CardIssuance\Enums\CardNetwork;
use App\Domain\CardIssuance\Enums\CardStatus;
use App\Domain\CardIssuance\Enums\WalletType;
use App\Domain\CardIssuance\ValueObjects\CardTransaction;
use App\Domain\CardIssuance\ValueObjects\ProvisioningData;
use App\Domain\CardIssuance\ValueObjects\VirtualCard;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Demo implementation of card issuer for development and testing.
 */
class DemoCardIssuerAdapter implements CardIssuerInterface
{
    public function getName(): string
    {
        return 'demo';
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function createCard(
        string $userId,
        string $cardholderName,
        array $metadata = [],
        ?CardNetwork $network = null,
        ?string $label = null,
    ): VirtualCard {
        $cardToken = 'card_demo_' . bin2hex(random_bytes(16));
        $last4 = (string) random_int(1000, 9999);
        $expiresAt = (new DateTimeImmutable())->modify('+3 years');

        $card = new VirtualCard(
            cardToken: $cardToken,
            last4: $last4,
            network: $network ?? CardNetwork::VISA,
            status: CardStatus::ACTIVE,
            cardholderName: $cardholderName,
            expiresAt: $expiresAt,
            metadata: array_merge($metadata, ['user_id' => $userId, 'label' => $label]),
            label: $label,
        );

        // Store in cache for demo purposes
        Cache::put("card:{$cardToken}", $card, now()->addDays(30));
        Cache::put("user_cards:{$userId}", array_merge(
            Cache::get("user_cards:{$userId}", []),
            [$cardToken]
        ), now()->addDays(30));

        return $card;
    }

    /**
     * @param array<string> $certificates
     */
    public function getProvisioningData(
        string $cardToken,
        WalletType $walletType,
        string $deviceId,
        array $certificates = []
    ): ProvisioningData {
        // Demo provisioning data - in production, this would come from the card issuer
        return new ProvisioningData(
            cardId: $cardToken,
            walletType: $walletType,
            encryptedPassData: base64_encode("demo_encrypted_pass_data_{$cardToken}"),
            activationData: base64_encode("demo_activation_data_{$deviceId}"),
            ephemeralPublicKey: base64_encode('demo_ephemeral_key_' . bin2hex(random_bytes(32))),
            certificateChain: [
                'demo_certificate_leaf',
                'demo_certificate_intermediate',
                'demo_certificate_root',
            ],
        );
    }

    public function freezeCard(string $cardToken): bool
    {
        $card = $this->getCard($cardToken);
        if ($card === null) {
            return false;
        }

        $frozenCard = new VirtualCard(
            cardToken: $card->cardToken,
            last4: $card->last4,
            network: $card->network,
            status: CardStatus::FROZEN,
            cardholderName: $card->cardholderName,
            expiresAt: $card->expiresAt,
            metadata: $card->metadata,
            label: $card->label,
        );

        Cache::put("card:{$cardToken}", $frozenCard, now()->addDays(30));

        return true;
    }

    public function unfreezeCard(string $cardToken): bool
    {
        $card = $this->getCard($cardToken);
        if ($card === null || $card->status !== CardStatus::FROZEN) {
            return false;
        }

        $activeCard = new VirtualCard(
            cardToken: $card->cardToken,
            last4: $card->last4,
            network: $card->network,
            status: CardStatus::ACTIVE,
            cardholderName: $card->cardholderName,
            expiresAt: $card->expiresAt,
            metadata: $card->metadata,
            label: $card->label,
        );

        Cache::put("card:{$cardToken}", $activeCard, now()->addDays(30));

        return true;
    }

    public function cancelCard(string $cardToken, string $reason): bool
    {
        $card = $this->getCard($cardToken);
        if ($card === null) {
            return false;
        }

        $cancelledCard = new VirtualCard(
            cardToken: $card->cardToken,
            last4: $card->last4,
            network: $card->network,
            status: CardStatus::CANCELLED,
            cardholderName: $card->cardholderName,
            expiresAt: $card->expiresAt,
            metadata: array_merge($card->metadata, ['cancellation_reason' => $reason]),
            label: $card->label,
        );

        Cache::put("card:{$cardToken}", $cancelledCard, now()->addDays(30));

        return true;
    }

    public function getCard(string $cardToken): ?VirtualCard
    {
        return Cache::get("card:{$cardToken}");
    }

    /**
     * @return array<VirtualCard>
     */
    public function listUserCards(string $userId): array
    {
        /** @var array<string> $tokens */
        $tokens = Cache::get("user_cards:{$userId}", []);

        $cards = [];
        foreach ($tokens as $token) {
            $card = $this->getCard($token);
            if ($card !== null && $card->status !== CardStatus::CANCELLED) {
                $cards[] = $card;
            }
        }

        return $cards;
    }

    /**
     * Get demo transaction history for a card.
     *
     * Generates 5 deterministic demo transactions seeded by card token.
     *
     * @return array{transactions: array<CardTransaction>, next_cursor: string|null}
     */
    public function getTransactions(string $cardToken, int $limit = 20, ?string $cursor = null): array
    {
        $demoMerchants = [
            ['name' => 'Starbucks',  'mcc' => '5814', 'amount' => 475,  'currency' => 'USD'],
            ['name' => 'Amazon',     'mcc' => '5942', 'amount' => 2999, 'currency' => 'USD'],
            ['name' => 'Uber Eats',  'mcc' => '5812', 'amount' => 1850, 'currency' => 'USD'],
            ['name' => 'Netflix',    'mcc' => '4899', 'amount' => 1599, 'currency' => 'USD'],
            ['name' => 'Shell',      'mcc' => '5541', 'amount' => 4520, 'currency' => 'USD'],
        ];

        $startIndex = $cursor !== null ? (int) $cursor : 0;
        $statuses = ['settled', 'settled', 'pending', 'settled', 'settled'];

        $transactions = [];
        $baseTime = new DateTimeImmutable('2026-03-01T12:00:00Z');

        for ($i = $startIndex; $i < min($startIndex + $limit, count($demoMerchants)); $i++) {
            $merchant = $demoMerchants[$i];
            // Deterministic transaction ID seeded from card token
            $seed = hash('sha256', $cardToken . ':' . $i);

            $transactions[] = new CardTransaction(
                transactionId: 'txn_demo_' . substr($seed, 0, 16),
                cardToken: $cardToken,
                merchantName: $merchant['name'],
                merchantCategory: $merchant['mcc'],
                amountCents: $merchant['amount'],
                currency: $merchant['currency'],
                status: $statuses[$i],
                timestamp: $baseTime->modify("-{$i} hours"),
            );
        }

        $hasMore = ($startIndex + $limit) < count($demoMerchants);
        $nextCursor = $hasMore ? (string) ($startIndex + $limit) : null;

        return [
            'transactions' => $transactions,
            'next_cursor'  => $nextCursor,
        ];
    }
}
