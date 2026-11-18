<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Adapters;

use App\Domain\CardIssuance\Contracts\CardIssuerInterface;
use App\Domain\CardIssuance\Enums\CardNetwork;
use App\Domain\CardIssuance\Enums\CardStatus;
use App\Domain\CardIssuance\Enums\WalletType;
use App\Domain\CardIssuance\Exceptions\UnsupportedCardOperationException;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Services\FinCardCardService;
use App\Domain\CardIssuance\ValueObjects\CardTransaction;
use App\Domain\CardIssuance\ValueObjects\ProvisioningData;
use App\Domain\CardIssuance\ValueObjects\VirtualCard;
use App\Infrastructure\FinCard\FinCardClient;
use DateTimeImmutable;
use Exception;
use Illuminate\Support\Str;

/**
 * FinCard adapter for the generic CardIssuerInterface (GraphQL + any
 * CardIssuerInterface consumer). It covers the lifecycle overlap FinCard's
 * prefunded model maps to; the rich open/fund/sensitive operations that need a
 * card-type + amount + account are the FinCard-specific /v1/cards/fincard/*
 * endpoints, so `createCard` and `getProvisioningData` (no wallet provisioning
 * in v1) throw {@see UnsupportedCardOperationException}. Reads come from the
 * local Card mirror; state changes delegate to {@see FinCardCardService}.
 *
 * @see docs/superpowers/specs/2026-07-06-fincard-card-issuing-design.md §3.2
 */
final class FinCardCardIssuerAdapter implements CardIssuerInterface
{
    public function __construct(
        private readonly FinCardCardService $cardService,
        private readonly FinCardClient $client,
    ) {
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function createCard(
        string $userId,
        string $cardholderName,
        array $metadata = [],
        ?CardNetwork $network = null,
        ?string $label = null,
    ): VirtualCard {
        throw new UnsupportedCardOperationException(
            'FinCard cards are opened with a card-type + amount against a funded account — use POST /v1/cards/fincard/open.',
        );
    }

    /**
     * @param  array<string>  $certificates
     */
    public function getProvisioningData(
        string $cardToken,
        WalletType $walletType,
        string $deviceId,
        array $certificates = [],
    ): ProvisioningData {
        throw new UnsupportedCardOperationException('FinCard v1 has no Apple/Google Pay push provisioning.');
    }

    public function freezeCard(string $cardToken): bool
    {
        $card = $this->findCard($cardToken);
        if (! $card instanceof Card) {
            return false;
        }
        $this->cardService->freeze($card, $this->orderNo());

        return true;
    }

    public function unfreezeCard(string $cardToken): bool
    {
        $card = $this->findCard($cardToken);
        if (! $card instanceof Card) {
            return false;
        }
        $this->cardService->unfreeze($card, $this->orderNo());

        return true;
    }

    public function cancelCard(string $cardToken, string $reason): bool
    {
        $card = $this->findCard($cardToken);
        if (! $card instanceof Card) {
            return false;
        }
        $this->cardService->cancel($card, $this->orderNo());

        return true;
    }

    public function getCard(string $cardToken): ?VirtualCard
    {
        $card = $this->findCard($cardToken);

        return $card instanceof Card ? $this->toVirtualCard($card) : null;
    }

    /**
     * @return array<VirtualCard>
     */
    public function listUserCards(string $userId): array
    {
        return Card::query()
            ->where('user_id', $userId)
            ->where('issuer', 'fincard')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Card $card): VirtualCard => $this->toVirtualCard($card))
            ->all();
    }

    /**
     * @return array{transactions: array<CardTransaction>, next_cursor: string|null}
     */
    public function getTransactions(string $cardToken, int $limit = 20, ?string $cursor = null): array
    {
        $page = $cursor !== null && ctype_digit($cursor) ? max((int) $cursor, 1) : 1;
        $response = $this->client->getCardPurchaseTransactions($cardToken, $page, $limit);
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $records = is_array($data['records'] ?? null) ? $data['records'] : (array_is_list($data) ? $data : []);

        $transactions = [];
        foreach ($records as $record) {
            if (is_array($record)) {
                $transactions[] = $this->toCardTransaction($cardToken, $record);
            }
        }

        $hasMore = count($records) >= $limit;

        return ['transactions' => $transactions, 'next_cursor' => $hasMore ? (string) ($page + 1) : null];
    }

    public function getName(): string
    {
        return 'fincard';
    }

    private function findCard(string $cardToken): ?Card
    {
        return Card::query()
            ->where('issuer_card_token', $cardToken)
            ->where('issuer', 'fincard')
            ->first();
    }

    private function toVirtualCard(Card $card): VirtualCard
    {
        return new VirtualCard(
            cardToken: $card->issuer_card_token,
            last4: $card->last4,
            network: CardNetwork::tryFrom($card->network) ?? CardNetwork::VISA,
            status: CardStatus::tryFrom($card->status) ?? CardStatus::PENDING,
            cardholderName: $card->cardholder?->getFullName() ?? '',
            expiresAt: $card->expires_at !== null
                ? DateTimeImmutable::createFromInterface($card->expires_at)
                : new DateTimeImmutable('+3 years'),
            metadata: [],
            label: $card->label,
        );
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function toCardTransaction(string $cardToken, array $record): CardTransaction
    {
        $amount = (string) ($record['amount'] ?? $record['tradeAmount'] ?? '0');
        $amountCents = is_numeric($amount) ? (int) bcmul(bcadd($amount, '0', 2), '100', 0) : 0;
        $ts = (string) ($record['tradeTime'] ?? $record['createTime'] ?? '');

        return new CardTransaction(
            transactionId: (string) ($record['orderNo'] ?? $record['tradeNo'] ?? $record['id'] ?? ''),
            cardToken: $cardToken,
            merchantName: (string) ($record['merchantName'] ?? $record['merchant'] ?? ''),
            merchantCategory: (string) ($record['merchantCategory'] ?? $record['mcc'] ?? ''),
            amountCents: $amountCents,
            currency: (string) ($record['currency'] ?? 'USD'),
            status: (string) ($record['status'] ?? 'settled'),
            timestamp: $this->parseTimestamp($ts),
        );
    }

    private function parseTimestamp(string $ts): DateTimeImmutable
    {
        if ($ts !== '' && ctype_digit($ts)) {
            // Epoch seconds or milliseconds.
            $seconds = strlen($ts) > 11 ? (int) substr($ts, 0, 10) : (int) $ts;

            return (new DateTimeImmutable())->setTimestamp($seconds);
        }

        try {
            return $ts !== '' ? new DateTimeImmutable($ts) : new DateTimeImmutable();
        } catch (Exception) {
            return new DateTimeImmutable();
        }
    }

    private function orderNo(): string
    {
        return 'ADP' . str_replace('-', '', (string) Str::uuid());
    }
}
