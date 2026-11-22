<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers\Lepo;

use App\Domain\CardIssuance\Events\Broadcast\CardStateChanged;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Providers\CardProviderException;
use App\Infrastructure\Lepo\LepoApiException;
use App\Infrastructure\Lepo\LepoClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Lepo virtual cards and the local `cards` row that mirrors each one.
 *
 * A card row exists before Lepo opens the card: the card opens after the
 * first top-up (plan 4 calls open()), because Lepo takes the issue fee from
 * the user's balance. Lepo amounts are decimal strings; the row keeps cents.
 * Card numbers and security codes never reach this class (the client masks
 * them); the card-details page shows them through Interlace Widget.js.
 */
final class LepoCardLifecycle
{
    /** Lepo card status → our card status. */
    private const STATUSES = [
        'ACTIVE'           => Card::STATUS_ACTIVE,
        'PENDING'          => Card::STATUS_PENDING,
        'UNACTIVATED'      => Card::STATUS_PENDING,
        'SHIPMENTPENDING'  => Card::STATUS_PENDING,
        'SHIPPED'          => Card::STATUS_PENDING,
        'FROZEN'           => Card::STATUS_FROZEN,
        'SUSPENDED'        => Card::STATUS_FROZEN,
        'PAYMENT_REQUIRED' => Card::STATUS_PAYMENT_REQUIRED,
        'BLOCKED'          => Card::STATUS_BLOCKED,
        'FAILED'           => Card::STATUS_FAILED,
        'EXPIRED'          => Card::STATUS_EXPIRED,
        'CANCELLED'        => Card::STATUS_CANCELLED,
    ];

    public function __construct(private readonly LepoClient $client)
    {
    }

    /**
     * The local card row, before Lepo opens the card.
     */
    public function createPending(Cardholder $cardholder): Card
    {
        // One request at a time per user, so two taps cannot pass the limit.
        return Cache::lock("cards:lepo:create:{$cardholder->user_id}", 10)->block(5, function () use ($cardholder): Card {
            $limit = max(1, (int) config('cardissuance.providers.lepo_max_cards', 1));
            $held = Card::query()
                ->where('user_id', $cardholder->user_id)
                ->where('issuer', 'lepo')
                ->whereNotIn('status', Card::CLOSED_STATUSES)
                ->count();

            if ($held >= $limit) {
                throw CardProviderException::cardLimit();
            }

            return Card::create([
                'user_id'            => $cardholder->user_id,
                'cardholder_id'      => $cardholder->id,
                'funding_account_id' => $cardholder->fundingAccount()->value('id'),
                'issuer'             => 'lepo',
                'status'             => Card::STATUS_PENDING,
                'currency'           => 'USD',
            ]);
        });
    }

    /**
     * Opens the card at Lepo. Safe to retry: a card Lepo already created for
     * this row (found by our card id) is adopted instead of opening another.
     */
    public function open(Card $card): Card
    {
        if ($card->isOpenAtProvider()) {
            return $card;
        }

        $cardholder = $this->cardholderOf($card);
        $userId = $this->lepoUserId($cardholder);
        $cardTypeId = (int) config('cardissuance.issuers.lepo.card_type_id_virtual');
        if ($cardTypeId <= 0) {
            throw CardProviderException::providerUnavailable('lepo', 'NO_CARD_TYPE');
        }

        try {
            $lepoCard = $this->client->createCard([
                'userId'         => (int) $userId,
                'cardTypeId'     => $cardTypeId,
                'phone'          => (string) $cardholder->phone,
                'externalCardId' => $card->id,
            ]);
        } catch (LepoApiException $e) {
            $lepoCard = $this->findByExternalId($userId, $card->id)
                ?? throw CardProviderException::providerUnavailable('lepo', $e->lepoCode());
        }

        $id = $lepoCard['id'] ?? null;
        if (! is_scalar($id) || (string) $id === '') {
            throw CardProviderException::providerUnavailable('lepo', 'NO_CARD_ID');
        }

        $card->issuer_card_token = (string) $id;

        return $this->sync($card, $lepoCard);
    }

    /**
     * Copies Lepo's view of the card onto the row and announces a status change.
     *
     * @param  array<mixed>  $lepoCard
     */
    public function sync(Card $card, array $lepoCard): Card
    {
        $before = $card->status;
        $status = self::STATUSES[strtoupper(trim((string) ($lepoCard['status'] ?? '')))] ?? $card->status;

        $card->forceFill([
            'status'        => $status,
            'last4'         => $this->last4($lepoCard['cardNumber'] ?? null) ?? $card->last4,
            'network'       => $this->network($lepoCard['cardNetwork'] ?? null) ?? $card->network,
            'expires_at'    => $this->expiry($lepoCard['expiryDate'] ?? null) ?? $card->expires_at,
            'balance_cents' => $this->cents(is_array($lepoCard['balance'] ?? null) ? ($lepoCard['balance']['available'] ?? null) : null) ?? $card->balance_cents,
            'frozen_at'     => $status === Card::STATUS_FROZEN ? ($card->frozen_at ?? now()) : null,
            'cancelled_at'  => $status === Card::STATUS_CANCELLED ? ($card->cancelled_at ?? now()) : $card->cancelled_at,
        ])->save();

        if ($status !== $before) {
            CardStateChanged::dispatch((int) $card->user_id, $card->id, $status, (int) ($card->balance_cents ?? 0));
        }

        return $card;
    }

    public function refresh(Card $card): Card
    {
        return $this->sync($card, $this->live($card));
    }

    public function freeze(Card $card): Card
    {
        $this->call(fn (): array => $this->client->freezeCard($this->token($card), $this->lepoUserId($this->cardholderOf($card))));

        return $this->refresh($card);
    }

    public function unfreeze(Card $card): Card
    {
        $this->call(fn (): array => $this->client->enableCard($this->token($card), $this->lepoUserId($this->cardholderOf($card))));

        return $this->refresh($card);
    }

    /**
     * Cancels the card only after Lepo confirms it holds no money (available,
     * pending and frozen balance). An unreadable balance refuses the cancel.
     */
    public function cancel(Card $card): Card
    {
        $live = $this->live($card);
        $balance = is_array($live['balance'] ?? null) ? $live['balance'] : [];

        $total = '0';
        foreach (['available', 'pending', 'frozen'] as $part) {
            $amount = $balance[$part] ?? '0';
            if (! is_numeric($amount)) {
                throw CardProviderException::providerUnavailable('lepo', 'NO_BALANCE');
            }
            $total = bcadd($total, bcadd((string) $amount, '0', 6), 6);
        }

        if (bccomp($total, '0', 6) === 1) {
            $this->sync($card, $live);

            throw CardProviderException::holdsMoney();
        }

        $this->call(fn (): array => $this->client->deleteCard($this->token($card), $this->lepoUserId($this->cardholderOf($card))));

        // Lepo deletes the card permanently, so it may no longer be readable.
        return $this->sync($card, ['status' => 'CANCELLED'] + $live);
    }

    /**
     * @return array{items: list<array{id: string, amount: string, currency: string, fee: string, type: string, status: string, merchant: ?string, merchant_country: ?string, occurred_at: ?string}>, has_more: bool}
     */
    public function transactions(Card $card, int $page, int $limit): array
    {
        $response = $this->call(fn (): array => $this->client->getCardTransactions($this->token($card), $this->lepoUserId($this->cardholderOf($card)), max(0, $page), max(1, min(50, $limit))));

        $items = [];
        foreach (is_array($response['transactions'] ?? null) ? $response['transactions'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $items[] = [
                'id'               => $this->text($row['id'] ?? null) ?? '',
                'amount'           => $this->text($row['amount'] ?? null) ?? '0',
                'currency'         => $this->text($row['currency'] ?? null) ?? 'USD',
                'fee'              => $this->text($row['fee'] ?? null) ?? '0',
                'type'             => $this->text($row['type'] ?? null) ?? '',
                'status'           => $this->text($row['status'] ?? null) ?? '',
                'merchant'         => $this->text($row['merchantName'] ?? null),
                'merchant_country' => $this->text($row['merchantCountry'] ?? null),
                'occurred_at'      => $this->text($row['transactionTime'] ?? null),
            ];
        }

        return ['items' => $items, 'has_more' => ($response['hasMorePages'] ?? false) === true];
    }

    /**
     * A short-lived Interlace Widget.js token for the card-details page.
     *
     * @return array{token: string, expires_in: int}
     */
    public function widgetToken(Card $card): array
    {
        $response = $this->call(fn (): array => $this->client->getCardWidget($this->token($card), $this->lepoUserId($this->cardholderOf($card))));

        $token = $response['widgetToken'] ?? null;
        if (! is_string($token) || $token === '') {
            throw CardProviderException::providerUnavailable('lepo', 'NO_WIDGET_TOKEN');
        }

        $expiresIn = $response['expiresIn'] ?? 300;

        return ['token' => $token, 'expires_in' => is_numeric($expiresIn) ? (int) $expiresIn : 300];
    }

    /**
     * @return array<mixed>
     */
    private function live(Card $card): array
    {
        return $this->call(fn (): array => $this->client->getCard($this->token($card), $this->lepoUserId($this->cardholderOf($card))));
    }

    /**
     * @return array<mixed>|null
     */
    private function findByExternalId(string $userId, string $cardId): ?array
    {
        try {
            $cards = $this->client->listCards($userId)['cards'] ?? [];
        } catch (LepoApiException) {
            return null;
        }

        foreach (is_array($cards) ? $cards : [] as $lepoCard) {
            if (is_array($lepoCard) && ($lepoCard['externalCardId'] ?? null) === $cardId) {
                return $lepoCard;
            }
        }

        return null;
    }

    private function cardholderOf(Card $card): Cardholder
    {
        return $card->cardholder()->first() ?? throw CardProviderException::providerUnavailable('lepo', 'NO_CARDHOLDER');
    }

    private function lepoUserId(Cardholder $cardholder): string
    {
        $id = (string) $cardholder->issuer_cardholder_id;
        if ($id === '') {
            throw CardProviderException::providerUnavailable('lepo', 'NO_USER_ID');
        }

        return $id;
    }

    private function token(Card $card): string
    {
        if (! $card->isOpenAtProvider()) {
            throw CardProviderException::providerUnavailable('lepo', 'CARD_NOT_OPEN');
        }

        return (string) $card->issuer_card_token;
    }

    private function last4(mixed $cardNumber): ?string
    {
        return is_string($cardNumber) && preg_match('/(\d{4})\D*$/', $cardNumber, $m) === 1 ? $m[1] : null;
    }

    private function network(mixed $network): ?string
    {
        $value = is_string($network) ? strtolower(trim($network)) : '';

        return $value !== '' ? $value : null;
    }

    private function expiry(mixed $date): ?Carbon
    {
        if (! is_string($date) || trim($date) === '') {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * "12.345" → 1234 (whole cents, rounded down).
     */
    private function cents(mixed $amount): ?int
    {
        if (! is_string($amount) && ! is_int($amount)) {
            return null;
        }

        $value = trim((string) $amount);
        if (! is_numeric($value)) {
            return null;
        }

        return (int) bcmul(bcadd($value, '0', 6), '100', 0);
    }

    private function text(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     */
    private function call(callable $call): mixed
    {
        try {
            return $call();
        } catch (LepoApiException $e) {
            throw CardProviderException::providerUnavailable('lepo', $e->lepoCode());
        }
    }
}
