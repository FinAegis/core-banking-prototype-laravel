<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\Services;

use App\Domain\VisaCli\Contracts\VisaCliClientInterface;
use App\Domain\VisaCli\DataObjects\VisaCliCard;
use App\Domain\VisaCli\DataObjects\VisaCliPaymentResult;
use App\Domain\VisaCli\DataObjects\VisaCliStatus;
use App\Domain\VisaCli\Enums\VisaCliCardStatus;
use App\Domain\VisaCli\Enums\VisaCliPaymentStatus;
use Illuminate\Support\Facades\Cache;

/**
 * Demo implementation of Visa CLI client for development and testing.
 */
class DemoVisaCliClient implements VisaCliClientInterface
{
    private const CACHE_PREFIX = 'visacli_demo:';

    public function getStatus(): VisaCliStatus
    {
        return new VisaCliStatus(
            initialized: true,
            version: '0.1.0-beta',
            githubUsername: 'demo-user',
            enrolledCards: count($this->listCards()),
            metadata: ['driver' => 'demo'],
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function enrollCard(string $userId, array $metadata = []): VisaCliCard
    {
        $cardId = 'visa_demo_' . bin2hex(random_bytes(8));
        $last4 = (string) random_int(1000, 9999);

        $card = new VisaCliCard(
            cardIdentifier: $cardId,
            last4: $last4,
            network: 'visa',
            status: VisaCliCardStatus::ENROLLED,
            githubUsername: 'demo-user',
            metadata: array_merge($metadata, ['user_id' => $userId]),
        );

        // Store in cache for demo
        /** @var array<string, array<string, mixed>> $cards */
        $cards = Cache::get(self::CACHE_PREFIX . 'cards', []);
        $cards[$cardId] = $card->toArray();
        Cache::put(self::CACHE_PREFIX . 'cards', $cards, now()->addDays(30));

        // Track user cards
        /** @var array<string> $userCards */
        $userCards = Cache::get(self::CACHE_PREFIX . "user_cards:{$userId}", []);
        $userCards[] = $cardId;
        Cache::put(self::CACHE_PREFIX . "user_cards:{$userId}", $userCards, now()->addDays(30));

        return $card;
    }

    /**
     * @return array<VisaCliCard>
     */
    public function listCards(?string $userId = null): array
    {
        if ($userId !== null) {
            return $this->listUserCards($userId);
        }

        /** @var array<string, array<string, mixed>> $cards */
        $cards = Cache::get(self::CACHE_PREFIX . 'cards', []);

        return array_map(
            fn (array $data) => new VisaCliCard(
                cardIdentifier: (string) $data['card_identifier'],
                last4: (string) $data['last4'],
                network: (string) $data['network'],
                status: VisaCliCardStatus::from((string) $data['status']),
                githubUsername: $data['github_username'] ?? null,
                metadata: (array) ($data['metadata'] ?? []),
            ),
            array_values($cards),
        );
    }

    public function pay(string $url, int $amountCents, ?string $cardId = null): VisaCliPaymentResult
    {
        $reference = 'visa_pay_demo_' . bin2hex(random_bytes(12));

        // Store demo payment
        /** @var array<string, array<string, mixed>> $payments */
        $payments = Cache::get(self::CACHE_PREFIX . 'payments', []);
        $payments[$reference] = [
            'reference'    => $reference,
            'url'          => $url,
            'amount_cents' => $amountCents,
            'currency'     => 'USD',
            'status'       => VisaCliPaymentStatus::COMPLETED->value,
            'card_id'      => $cardId,
            'created_at'   => now()->toIso8601String(),
        ];
        Cache::put(self::CACHE_PREFIX . 'payments', $payments, now()->addDays(30));

        return new VisaCliPaymentResult(
            paymentReference: $reference,
            status: VisaCliPaymentStatus::COMPLETED,
            amountCents: $amountCents,
            currency: 'USD',
            url: $url,
            cardLast4: '4242',
            metadata: ['driver' => 'demo'],
        );
    }

    public function isInitialized(): bool
    {
        return true;
    }

    public function initialize(): bool
    {
        return true;
    }

    /**
     * @return array<VisaCliCard>
     */
    private function listUserCards(string $userId): array
    {
        /** @var array<string> $userCardIds */
        $userCardIds = Cache::get(self::CACHE_PREFIX . "user_cards:{$userId}", []);

        /** @var array<string, array<string, mixed>> $allCards */
        $allCards = Cache::get(self::CACHE_PREFIX . 'cards', []);

        $cards = [];
        foreach ($userCardIds as $cardId) {
            if (isset($allCards[$cardId])) {
                $data = $allCards[$cardId];
                $cards[] = new VisaCliCard(
                    cardIdentifier: (string) $data['card_identifier'],
                    last4: (string) $data['last4'],
                    network: (string) $data['network'],
                    status: VisaCliCardStatus::from((string) $data['status']),
                    githubUsername: $data['github_username'] ?? null,
                    metadata: (array) ($data['metadata'] ?? []),
                );
            }
        }

        return $cards;
    }
}
