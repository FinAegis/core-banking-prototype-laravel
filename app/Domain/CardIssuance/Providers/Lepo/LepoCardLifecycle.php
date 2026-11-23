<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers\Lepo;

use App\Domain\CardIssuance\Events\Broadcast\CardStateChanged;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardStaffFreeze;
use App\Domain\CardIssuance\Models\CardTopUp;
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

    /** Lepo (and Interlace) transaction type → our type; anything else is `other`. */
    private const TRANSACTION_TYPES = [
        'payment'    => ['card_payment', 'purchase', 'payment_transfer', 'internal_payment', 'consumption'],
        'refund'     => ['card_payment_reversal', 'crypto_refund', 'fee_reversal', 'refund', 'reversal'],
        'top_up'     => ['card_topup', 'deposit', 'crypto_deposit', 'transfer_in', 'transfer_from_master', 'wallet_credit', 'p2p_receive', 'credit', 'crypto_to_quantum', 'crypto_to_quantum_transfer'],
        'withdrawal' => ['card_withdrawal', 'card_unload', 'withdrawal', 'crypto_withdrawal', 'transfer_out', 'transfer_to_master', 'wallet_debit', 'p2p_send', 'payout', 'debit'],
        'fee'        => ['card_payment_fee', 'card_fee', 'fees', 'card_issuance_fee', 'card_topup_fee', 'card_decline_fee'],
    ];

    /** Lepo transaction status → our status; anything else is `unknown`. */
    private const TRANSACTION_STATUSES = [
        'pending'   => 'pending', 'processing' => 'pending', 'on_hold' => 'pending', 'onhold' => 'pending',
        'completed' => 'completed', 'success' => 'completed', 'succeeded' => 'completed',
        'failed'    => 'failed', 'declined' => 'failed', 'fail' => 'failed',
        'cancelled' => 'cancelled', 'canceled' => 'cancelled',
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

        // One open per card at a time, and a card a previous attempt already
        // created at Lepo (found by our card id) is adopted, never created twice.
        return Cache::lock("cards:lepo:open:{$card->id}", 30)->block(10, function () use ($card): Card {
            $card->refresh();

            return $card->isOpenAtProvider() ? $card : $this->createAtLepo($card);
        });
    }

    private function createAtLepo(Card $card): Card
    {
        $cardholder = $this->cardholderOf($card);
        $userId = $this->lepoUserId($cardholder);
        $cardTypeId = (int) config('cardissuance.issuers.lepo.card_type_id_virtual');
        if ($cardTypeId <= 0) {
            throw CardProviderException::providerUnavailable('lepo', 'NO_CARD_TYPE');
        }

        $lepoCard = $this->findByExternalId($userId, $card->id);
        if ($lepoCard !== null) {
            return $this->adopt($card, $lepoCard);
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

        return $this->adopt($card, $lepoCard);
    }

    /**
     * @param  array<mixed>  $lepoCard
     */
    private function adopt(Card $card, array $lepoCard): Card
    {
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
            'expires_at'    => $this->expiry($lepoCard) ?? $card->expires_at,
            'balance_cents' => $this->cents(is_array($lepoCard['balance'] ?? null) ? ($lepoCard['balance']['available'] ?? null) : null) ?? $card->balance_cents,
            'frozen_at'     => $status === Card::STATUS_FROZEN ? ($card->frozen_at ?? now()) : null,
            'cancelled_at'  => $status === Card::STATUS_CANCELLED ? ($card->cancelled_at ?? now()) : $card->cancelled_at,
        ])->save();

        $this->recordIssuerFreeze($card, strtoupper(trim((string) ($lepoCard['status'] ?? ''))));

        if ($status !== $before) {
            CardStateChanged::dispatch((int) $card->user_id, $card->id, $status, (int) ($card->balance_cents ?? 0));
        }

        return $card;
    }

    /**
     * A suspension or block by the card issuer is not the owner's freeze, so
     * the owner must not lift it (as for FinCard issuer blocks). It is lifted
     * when Lepo reports the card active again; a support freeze stays.
     */
    private function recordIssuerFreeze(Card $card, string $lepoStatus): void
    {
        if (! $card->isOpenAtProvider()) {
            return;
        }

        $token = (string) $card->issuer_card_token;
        if (in_array($lepoStatus, ['SUSPENDED', 'BLOCKED'], true)) {
            CardStaffFreeze::place($token, null, CardStaffFreeze::SOURCE_ISSUER);
        } elseif ($lepoStatus === 'ACTIVE') {
            CardStaffFreeze::query()->where('issuer_card_token', $token)->where('source', CardStaffFreeze::SOURCE_ISSUER)->delete();
        }
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
        return $this->underMoneyLock($card, fn (): Card => $this->cancelUnlocked($card->refresh()));
    }

    /**
     * Never while a top-up moves money for this user, and never while the
     * card has a top-up waiting, moving or unresolved: a load landing on a
     * deleted card would be lost.
     *
     * @param  callable(): Card  $cancel
     */
    private function underMoneyLock(Card $card, callable $cancel): Card
    {
        $lock = Cache::lock(LepoTopUpPipeline::moneyLockKey((int) $card->user_id), LepoTopUpPipeline::LOCK_SECONDS);
        if (! $lock->get()) {
            throw CardProviderException::providerUnavailable('lepo', 'MONEY_OPERATION_IN_PROGRESS');
        }

        try {
            $busy = CardTopUp::query()
                ->where('card_id', $card->id)
                ->whereIn('status', [...CardTopUp::OPEN, CardTopUp::STATUS_NEEDS_ATTENTION])
                ->exists();
            if ($busy) {
                throw CardProviderException::topUp('topup_in_progress');
            }

            return $cancel();
        } finally {
            $lock->release();
        }
    }

    private function cancelUnlocked(Card $card): Card
    {
        $live = $this->live($card);
        $balance = $live['balance'] ?? null;
        if (! is_array($balance)) {
            throw CardProviderException::providerUnavailable('lepo', 'NO_BALANCE');
        }

        // Every part must be present and readable: a missing part is not zero,
        // and Lepo deletes a cancelled card permanently.
        $total = '0';
        foreach (['available', 'pending', 'frozen'] as $part) {
            $amount = $balance[$part] ?? null;
            if ((! is_string($amount) && ! is_int($amount)) || ! is_numeric(trim((string) $amount))) {
                throw CardProviderException::providerUnavailable('lepo', 'NO_BALANCE');
            }
            $total = bcadd($total, bcadd(trim((string) $amount), '0', 6), 6);
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
     * Cancels a row Lepo has not opened as far as we know. An open attempt
     * may still have created the card at Lepo, so Lepo is asked first: a card
     * found by our card id goes through the guarded cancel above, and when
     * Lepo cannot be asked the row stays as it is.
     */
    public function cancelUnopened(Card $card): Card
    {
        return $this->underMoneyLock($card, function () use ($card): Card {
            $card->refresh();
            if ($card->isOpenAtProvider()) {
                return $this->cancelUnlocked($card);
            }

            $userId = $this->lepoUserId($this->cardholderOf($card));
            $cards = $this->call(fn (): array => $this->client->listCards($userId))['cards'] ?? [];
            foreach (is_array($cards) ? $cards : [] as $lepoCard) {
                if (is_array($lepoCard) && ($lepoCard['externalCardId'] ?? null) === $card->id) {
                    return $this->cancelUnlocked($this->adopt($card, $lepoCard));
                }
            }

            return $this->sync($card, ['status' => 'CANCELLED']);
        });
    }

    /**
     * One shape for every transaction: our type (payment, refund, top_up,
     * withdrawal, fee, other), a direction (debit or credit), the amount as a
     * positive 2-decimal string and our status (pending, completed, failed,
     * cancelled, unknown). Lepo's own type stays in provider_type. Pages count
     * from 0, as Lepo documents them ("starting from 0, default: 0").
     *
     * @return array{items: list<array{id: string, type: string, direction: string, amount: string, currency: string, fee: string, status: string, merchant: ?string, merchant_country: ?string, occurred_at: ?string, provider_type: ?string}>, has_more: bool, total: int|null}
     */
    public function transactions(Card $card, int $page, int $limit): array
    {
        $response = $this->call(fn (): array => $this->client->getCardTransactions($this->token($card), $this->lepoUserId($this->cardholderOf($card)), max(0, $page), max(1, min(50, $limit))));

        $items = [];
        foreach (is_array($response['transactions'] ?? null) ? $response['transactions'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $providerType = $this->text($row['type'] ?? null);
            $type = $this->transactionType($providerType);
            $amount = $row['amount'] ?? null;

            $items[] = [
                'id'        => $this->text($row['id'] ?? null) ?? '',
                'type'      => $type,
                'direction' => match ($type) {
                    'payment', 'withdrawal', 'fee' => 'debit',
                    'refund', 'top_up'             => 'credit',
                    default                        => $this->isNegative($amount) ? 'debit' : 'credit',
                },
                'amount'           => $this->money($amount),
                'currency'         => $this->text($row['currency'] ?? null) ?? 'USD',
                'fee'              => $this->money($row['fee'] ?? null),
                'status'           => self::TRANSACTION_STATUSES[strtolower((string) $this->text($row['status'] ?? null))] ?? 'unknown',
                'merchant'         => $this->text($row['merchantName'] ?? null),
                'merchant_country' => $this->text($row['merchantCountry'] ?? null),
                'occurred_at'      => $this->text($row['transactionTime'] ?? null),
                'provider_type'    => $providerType === null ? null : strtolower($providerType),
            ];
        }

        $total = $response['total'] ?? null;

        return ['items' => $items, 'has_more' => ($response['hasMorePages'] ?? false) === true, 'total' => is_numeric($total) ? (int) $total : null];
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

    /**
     * The card's expiry: Lepo's expMonth/expYear when given, else expiryDate
     * in a format we know (MM/YY, MM/YYYY or YYYY-MM-DD). A card expires at
     * the end of its month. Anything else is ignored rather than guessed.
     *
     * @param  array<mixed>  $lepoCard
     */
    private function expiry(array $lepoCard): ?Carbon
    {
        $month = $lepoCard['expMonth'] ?? null;
        $year = $lepoCard['expYear'] ?? null;
        if (is_scalar($month) && is_scalar($year) && preg_match('/^\d{1,2}$/', (string) $month) === 1 && preg_match('/^\d{4}$/', (string) $year) === 1) {
            return $this->endOfMonth((int) $year, (int) $month);
        }

        $date = is_string($lepoCard['expiryDate'] ?? null) ? trim($lepoCard['expiryDate']) : '';
        if (preg_match('#^(\d{1,2})/(\d{2}|\d{4})$#', $date, $m) === 1) {
            return $this->endOfMonth(strlen($m[2]) === 2 ? 2000 + (int) $m[2] : (int) $m[2], (int) $m[1]);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date) === 1) {
            try {
                return Carbon::parse(substr($date, 0, 10))->endOfDay();
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function endOfMonth(int $year, int $month): ?Carbon
    {
        return $month >= 1 && $month <= 12 ? Carbon::create($year, $month, 1)?->endOfMonth() : null;
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

    private function transactionType(?string $providerType): string
    {
        $type = strtolower((string) $providerType);
        foreach (self::TRANSACTION_TYPES as $ours => $theirs) {
            if (in_array($type, $theirs, true)) {
                return $ours;
            }
        }

        return 'other';
    }

    /**
     * A Lepo amount (a JSON number or a decimal string) as a positive
     * 2-decimal string; the sign is carried by `direction`.
     */
    private function money(mixed $amount): string
    {
        if (is_float($amount)) {
            // A JSON number: printf avoids exponent forms such as 1.0E-5.
            $amount = is_finite($amount) ? sprintf('%.6F', $amount) : '0';
        }

        $value = is_string($amount) || is_int($amount) ? trim((string) $amount) : '0';
        if (! is_numeric($value)) {
            return '0.00';
        }

        return bcadd(ltrim(bcadd($value, '0', 6), '-'), '0', 2);
    }

    private function isNegative(mixed $amount): bool
    {
        return (is_int($amount) || is_float($amount) || is_string($amount)) && is_numeric(trim((string) $amount)) && str_starts_with(trim((string) $amount), '-');
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
