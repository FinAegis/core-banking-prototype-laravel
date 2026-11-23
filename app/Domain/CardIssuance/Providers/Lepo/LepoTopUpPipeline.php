<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers\Lepo;

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardStaffFreeze;
use App\Domain\CardIssuance\Models\CardTopUp;
use App\Domain\CardIssuance\Providers\CardProviderException;
use App\Infrastructure\Lepo\LepoApiException;
use App\Infrastructure\Lepo\LepoClient;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Moves a received deposit onto the card: converts the USDC to US dollars
 * (final when Lepo answers 200), opens the card on the first top-up (Lepo
 * takes the 1 USD issue fee from that money) and loads the dollars onto the
 * card from the account's USD balance.
 *
 * Lepo's conversion and card-load calls take no idempotency key and no
 * client reference, so neither is ever repeated: each is recorded
 * (status + step_started_at) before the call, and a failure or an unclear
 * answer stops the top-up in needs_attention for an operator. The steps
 * between the two calls (waiting for the conversion, opening the card,
 * reading the balance) are safe to repeat, so they are retried for up to an
 * hour. While any top-up of the user has an unclear money outcome, the user's
 * other top-ups wait. One money operation per Lepo user at a time (a card
 * cancel takes the same lock).
 */
final class LepoTopUpPipeline
{
    /** Lepo's card-load minimum (USD). */
    private const LOAD_MINIMUM = '5.00';

    /** Room for the conversion and load fees when checking a deposit. */
    private const FEE_MARGIN = '0.50';

    /** A Lepo call left in flight longer than this was interrupted. */
    private const STEP_TIMEOUT_MINUTES = 15;

    /** The safe steps after a conversion are retried this long. */
    private const PREPARE_MINUTES = 60;

    /** Outcomes where money may have moved: the user's other top-ups wait. */
    public const UNCLEAR = ['CONVERSION_UNCLEAR', 'CONVERSION_FAILED', 'STEP_INTERRUPTED', 'LOAD_UNCLEAR', 'LOAD_CHECKOUT_LINK', 'LOAD_PARTIAL', 'LOAD_AMOUNT_MISMATCH'];

    /**
     * Lepo allows one conversion every 15 seconds for all users together. The
     * slot is a lock that is never released, so it expires after the gap.
     */
    public const CONVERSION_SLOT_KEY = 'cards:lepo:conversion-slot';

    private const CONVERSION_GAP_SECONDS = 15;

    /** Lock lifetime: above the job timeout, so a slow run keeps it. */
    public const LOCK_SECONDS = 300;

    public function __construct(
        private readonly LepoClient $client,
        private readonly LepoCardLifecycle $cards,
        private readonly LepoTopUps $topups,
    ) {
    }

    public static function moneyLockKey(int $userId): string
    {
        return "cards:lepo:money:{$userId}";
    }

    public function advance(CardTopUp $topup): CardTopUp
    {
        if (! LepoTopUps::enabled()) {
            return $topup;
        }

        $lock = Cache::lock(self::moneyLockKey((int) $topup->user_id), self::LOCK_SECONDS);
        if (! $lock->get()) {
            return $topup;
        }

        try {
            $topup->refresh();

            return $this->hasUnclearMoney($topup) ? $topup : $this->run($topup);
        } finally {
            $lock->release();
        }
    }

    private function run(CardTopUp $topup): CardTopUp
    {
        // A Lepo call was in flight when a previous run stopped: never repeat it.
        if (in_array($topup->status, [CardTopUp::STATUS_CONVERTING, CardTopUp::STATUS_LOADING], true) && $topup->step_started_at !== null) {
            return $topup->step_started_at->lt(now()->subMinutes(self::STEP_TIMEOUT_MINUTES))
                ? $this->stop($topup, 'STEP_INTERRUPTED')
                : $topup;
        }

        if ($topup->status === CardTopUp::STATUS_CONVERTING && $topup->provider_conversion_id !== null) {
            return $this->prepareAndLoad($topup);
        }

        if ($topup->status !== CardTopUp::STATUS_DEPOSIT_RECEIVED || $topup->received_amount === null) {
            return $topup;
        }

        return $this->convert($topup);
    }

    private function convert(CardTopUp $topup): CardTopUp
    {
        [$card, $reason] = $this->loadableCard($topup);
        if ($reason === 'WAIT') {
            return $topup; // the owner froze the card; the top-up finishes once it is unfrozen
        }
        if (! $card instanceof Card) {
            return $this->stop($topup, $reason);
        }

        $received = LepoTopUps::number((string) $topup->received_amount);
        $needed = bcadd(bcadd(self::LOAD_MINIMUM, self::FEE_MARGIN, 2), $card->isOpenAtProvider() ? '0' : '1.00', 2);
        if (bccomp($received, $needed, 6) === -1) {
            return $this->stop($topup, 'BELOW_MINIMUM');
        }

        if ($card->id !== $topup->card_id) {
            $topup->forceFill(['card_id' => $card->id])->save();
        }

        $userId = $this->lepoUserId($card);
        if (! Cache::lock(self::CONVERSION_SLOT_KEY, self::CONVERSION_GAP_SECONDS)->get()) {
            return $this->retry($topup, 'CONVERSION_QUEUED', false);
        }

        $this->mark($topup, CardTopUp::STATUS_CONVERTING, ['conversion_requested_at' => now()]);
        try {
            $converted = $this->client->convertCryptoToUsd($userId, 'USDC', $received);
        } catch (LepoApiException $e) {
            // 429 is Lepo's rate limit: the conversion did not happen.
            return $e->httpStatus() === 429
                ? $this->backToDeposit($topup, 'CONVERSION_RATE_LIMITED')
                : $this->stop($topup, 'CONVERSION_UNCLEAR');
        }

        $transferId = $converted['transferId'] ?? null;
        if (($converted['success'] ?? false) !== true || ! is_scalar($transferId) || (string) $transferId === '') {
            return $this->stop($topup, 'CONVERSION_UNCLEAR');
        }

        // Final on 200 with success: the dollars are on the balance at once.
        $details = is_array($converted['details'] ?? null) ? $converted['details'] : [];
        $status = strtoupper((string) ($details['status'] ?? ''));
        $topup->forceFill([
            'provider_conversion_id' => (string) $transferId,
            'converted_at'           => now(),
            'step_started_at'        => null,
        ])->save();

        if (in_array($status, ['FAILED', 'CANCELLED'], true)) {
            return $this->stop($topup, 'CONVERSION_FAILED');
        }

        return $this->prepareAndLoad($topup);
    }

    /**
     * The steps after a conversion: all safe to repeat until the load call.
     */
    private function prepareAndLoad(CardTopUp $topup): CardTopUp
    {
        $started = $topup->conversion_requested_at ?? $topup->updated_at ?? now();
        $giveUp = $started->lt(now()->subMinutes(self::PREPARE_MINUTES));

        // A conversion that answered success is final (Lepo, 2026-11-23); a row
        // from before that answer may still lack the time.
        if ($topup->converted_at === null) {
            $topup->forceFill(['converted_at' => now()])->save();
        }

        [$card, $reason] = $this->loadableCard($topup);
        if ($reason === 'WAIT') {
            return $this->retry($topup, 'CARD_FROZEN', $giveUp);
        }
        if (! $card instanceof Card) {
            return $this->stop($topup, $reason);
        }
        if ($card->id !== $topup->card_id) {
            $topup->forceFill(['card_id' => $card->id])->save();
        }

        if (! $card->isOpenAtProvider()) {
            try {
                $card = $this->cards->open($card);
            } catch (CardProviderException | LockTimeoutException) {
                return $this->retry($topup, 'CARD_OPEN_FAILED', $giveUp);
            }

            if ($card->status === Card::STATUS_PAYMENT_REQUIRED) {
                return $this->stop($topup, 'CARD_PAYMENT_REQUIRED');
            }
            if (! $card->isOpenAtProvider()) {
                return $this->retry($topup, 'CARD_OPEN_FAILED', $giveUp);
            }

            // Recorded at once, so a later run knows the fee was taken here.
            $topup->forceFill(['issuance_fee_cents' => LepoTopUps::ISSUANCE_FEE_CENTS])->save();
        }

        $available = $this->availableUsd($this->lepoUserId($card));
        if ($available === null) {
            return $this->retry($topup, 'BALANCE_UNREADABLE', $giveUp);
        }

        // Never more than this deposit brought (less the issue fee it paid):
        // other dollars on the account belong to other top-ups.
        $cap = bcadd(LepoTopUps::number((string) $topup->received_amount), '0', 2);
        if (($topup->issuance_fee_cents ?? 0) > 0) {
            $cap = bcsub($cap, bcdiv((string) $topup->issuance_fee_cents, '100', 2), 2);
        }
        $value = bccomp($available, $cap, 2) === 1 ? $cap : $available;
        if (bccomp($value, self::LOAD_MINIMUM, 2) === -1) {
            return $this->stop($topup, 'BELOW_LOAD_MINIMUM');
        }

        $this->mark($topup, CardTopUp::STATUS_LOADING);
        try {
            $loaded = $this->client->topUpCard((string) $card->issuer_card_token, $value, 'USD');
        } catch (LepoApiException) {
            return $this->stop($topup, 'LOAD_UNCLEAR');
        }

        // A checkout link means Lepo wants another payment: never hand it on.
        if (is_string($loaded['transakUrl'] ?? null) && $loaded['transakUrl'] !== '') {
            return $this->stop($topup, 'LOAD_CHECKOUT_LINK');
        }
        if (($loaded['success'] ?? false) !== true) {
            return $this->stop($topup, 'LOAD_UNCLEAR');
        }
        if (($loaded['isPartial'] ?? false) === true) {
            return $this->stop($topup, 'LOAD_PARTIAL');
        }

        // Lepo answers requestedValue (what we sent), grossValue (taken from the
        // balance), fee and value (what the card received; the fee comes out
        // of it). The request must be ours; the credit is value.
        $requested = $this->decimal($loaded['requestedValue'] ?? null, 2);
        $credited = $this->decimal($loaded['value'] ?? null, 2);
        if ($requested === null || $credited === null || bccomp($requested, $value, 2) !== 0) {
            return $this->stop($topup, 'LOAD_AMOUNT_MISMATCH');
        }

        $fee = $this->decimal($loaded['fee'] ?? null, 2);
        $topup->forceFill([
            'status'             => CardTopUp::STATUS_COMPLETED,
            'credited_cents'     => (int) bcmul($credited, '100', 0),
            'fee_cents'          => $fee === null ? null : (int) bcmul($fee, '100', 0),
            'issuance_fee_cents' => $topup->issuance_fee_cents ?? 0,
            'provider_load_id'   => is_scalar($loaded['orderId'] ?? null) ? (string) $loaded['orderId'] : null,
            'step_started_at'    => null,
            'last_error'         => null,
            'completed_at'       => now(),
        ])->save();
        $this->topups->announce($topup);

        try {
            $this->cards->refresh($card);
        } catch (CardProviderException) {
            // The card shows its new balance on the next read.
        }

        return $topup;
    }

    /**
     * The card that takes the money: the top-up's own card while it can, else
     * the user's current Lepo card. A card frozen by its owner waits; a card
     * waiting for payment and no card at all stop for an operator.
     *
     * @return array{0: Card|null, 1: string}
     */
    private function loadableCard(CardTopUp $topup): array
    {
        $own = $topup->card_id === null ? null : Card::query()->whereKey($topup->card_id)->first();
        $candidates = Card::query()
            ->where('user_id', $topup->user_id)
            ->where('issuer', 'lepo')
            ->orderByDesc('created_at')
            ->get();
        if ($own instanceof Card) {
            $candidates = $candidates->reject(fn (Card $card): bool => $card->id === $own->id)->prepend($own);
        }

        foreach ($candidates as $card) {
            if (in_array($card->status, [Card::STATUS_ACTIVE, Card::STATUS_PENDING], true)) {
                return [$card, ''];
            }
        }

        foreach ($candidates as $card) {
            if ($card->status === Card::STATUS_FROZEN && ! CardStaffFreeze::isHeld((string) $card->issuer_card_token)) {
                return [null, 'WAIT'];
            }
            if ($card->status === Card::STATUS_PAYMENT_REQUIRED) {
                return [null, 'CARD_PAYMENT_REQUIRED'];
            }
        }

        return [null, 'NO_LOADABLE_CARD'];
    }

    private function hasUnclearMoney(CardTopUp $topup): bool
    {
        return CardTopUp::query()
            ->where('user_id', $topup->user_id)
            ->whereKeyNot($topup->id)
            ->where('status', CardTopUp::STATUS_NEEDS_ATTENTION)
            ->whereIn('last_error', self::UNCLEAR)
            ->exists();
    }

    /**
     * The available US-dollar balance of the account at Lepo, 2 decimals,
     * rounded down. Only the fiat QuantumAccount USD row counts: the Card row
     * is money already on the card. Rows are keyed on balanceId. A missing
     * available amount is unreadable, never the total balance.
     *
     * @return numeric-string|null
     */
    private function availableUsd(string $userId): ?string
    {
        try {
            $assets = $this->client->getAssets($userId)['assets'] ?? null;
        } catch (LepoApiException) {
            return null;
        }

        if (! is_array($assets)) {
            return null;
        }

        $rows = [];
        foreach ($assets as $asset) {
            if (
                ! is_array($asset)
                || strtolower((string) ($asset['assetType'] ?? '')) !== 'fiat'
                || strtolower((string) ($asset['balanceType'] ?? '')) !== 'quantumaccount'
                || strtoupper((string) ($asset['currency'] ?? '')) !== 'USD'
            ) {
                continue;
            }

            $available = $this->decimal($asset['availableBalance'] ?? null, 6);
            if ($available === null) {
                return null;
            }

            $rows[(string) ($asset['balanceId'] ?? count($rows))] = $available;
        }

        if ($rows === []) {
            return null;
        }

        $total = '0';
        foreach ($rows as $available) {
            $total = bcadd($total, $available, 6);
        }

        return bcadd($total, '0', 2);
    }

    private function lepoUserId(Card $card): string
    {
        $holder = $card->cardholder()->first();
        $id = $holder instanceof Cardholder ? (string) $holder->issuer_cardholder_id : '';
        if ($id === '') {
            throw CardProviderException::providerUnavailable('lepo', 'NO_USER_ID');
        }

        return $id;
    }

    /**
     * A Lepo amount (JSON number or decimal string), rounded down.
     *
     * @return numeric-string|null
     */
    private function decimal(mixed $value, int $scale): ?string
    {
        if (is_float($value)) {
            $value = is_finite($value) ? sprintf('%.6F', $value) : null;
        }

        $text = is_string($value) || is_int($value) ? trim((string) $value) : '';

        return preg_match('/^\d+(\.\d+)?$/', $text) === 1 && is_numeric($text) ? bcadd($text, '0', $scale) : null;
    }

    /**
     * The conversion call did not happen: undo the "converting" mark so the
     * next run converts again.
     */
    private function backToDeposit(CardTopUp $topup, string $reason): CardTopUp
    {
        $topup->forceFill([
            'status'                  => CardTopUp::STATUS_DEPOSIT_RECEIVED,
            'conversion_requested_at' => null,
            'step_started_at'         => null,
            'last_error'              => $reason,
        ])->save();
        $this->topups->announce($topup);

        return $topup;
    }

    /**
     * @param  array<model-property<CardTopUp>, mixed>  $extra
     */
    private function mark(CardTopUp $topup, string $status, array $extra = []): void
    {
        $topup->forceFill(['status' => $status, 'step_started_at' => now(), 'last_error' => null] + $extra)->save();
        $this->topups->announce($topup);
    }

    /**
     * A safe step did not work yet: try again on the next run, until the hour
     * is up.
     */
    private function retry(CardTopUp $topup, string $reason, bool $giveUp): CardTopUp
    {
        if ($giveUp) {
            return $this->stop($topup, $reason);
        }

        $topup->forceFill(['last_error' => $reason, 'step_started_at' => null])->save();

        return $topup;
    }

    private function stop(CardTopUp $topup, string $reason): CardTopUp
    {
        $topup->forceFill(['status' => CardTopUp::STATUS_NEEDS_ATTENTION, 'last_error' => $reason, 'step_started_at' => null])->save();
        Log::error('Lepo top-up needs attention', ['topup_id' => $topup->id, 'reason' => $reason]);
        $this->topups->announce($topup);

        return $topup;
    }
}
