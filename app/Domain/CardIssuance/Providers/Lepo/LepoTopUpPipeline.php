<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers\Lepo;

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardTopUp;
use App\Domain\CardIssuance\Providers\CardProviderException;
use App\Infrastructure\Lepo\LepoApiException;
use App\Infrastructure\Lepo\LepoClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Moves a received deposit onto the card: converts the USDC to US dollars,
 * opens the card on the first top-up (Lepo takes the 1 USD issue fee from
 * that money) and loads the dollars onto the card.
 *
 * Lepo's conversion and card-load calls take no idempotency key and no
 * client reference, so a call is never repeated: each step is recorded
 * (status + step_started_at) before its call, and any failure or unclear
 * answer stops the top-up in needs_attention for an operator. One money
 * operation per Lepo user at a time (also taken by a card cancel).
 */
final class LepoTopUpPipeline
{
    /** Lepo's card-load minimum (USD). */
    private const LOAD_MINIMUM = '5.00';

    /** A step left in flight longer than this was interrupted mid-call. */
    private const STEP_TIMEOUT_MINUTES = 15;

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

        $lock = Cache::lock(self::moneyLockKey((int) $topup->user_id), 120);
        if (! $lock->get()) {
            return $topup;
        }

        try {
            return $this->run($topup->refresh());
        } finally {
            $lock->release();
        }
    }

    private function run(CardTopUp $topup): CardTopUp
    {
        if (in_array($topup->status, [CardTopUp::STATUS_CONVERTING, CardTopUp::STATUS_LOADING], true)) {
            // A previous run stopped during a Lepo call; never call again.
            if ($topup->step_started_at === null || $topup->step_started_at->lt(now()->subMinutes(self::STEP_TIMEOUT_MINUTES))) {
                return $this->stop($topup, 'STEP_INTERRUPTED');
            }

            return $topup;
        }

        if ($topup->status !== CardTopUp::STATUS_DEPOSIT_RECEIVED || $topup->received_amount === null) {
            return $topup;
        }

        $card = $topup->card()->first();
        $holder = $card?->cardholder()->first();
        $userId = $holder instanceof Cardholder ? (string) $holder->issuer_cardholder_id : '';
        if (! $card instanceof Card || $userId === '') {
            return $this->stop($topup, 'NO_CARD');
        }

        // 1. Convert the received USDC to US dollars.
        $this->mark($topup, CardTopUp::STATUS_CONVERTING);
        try {
            $converted = $this->client->convertCryptoToUsd($userId, 'USDC', LepoTopUps::number((string) $topup->received_amount));
        } catch (LepoApiException) {
            return $this->stop($topup, 'CONVERSION_UNCLEAR');
        }

        $transferId = $converted['transferId'] ?? null;
        if (($converted['success'] ?? false) !== true || ! is_scalar($transferId) || (string) $transferId === '') {
            return $this->stop($topup, 'CONVERSION_UNCLEAR');
        }
        $topup->forceFill(['provider_conversion_id' => (string) $transferId])->save();

        // 2. Open the card on the first top-up.
        $issuance = 0;
        if (! $card->isOpenAtProvider()) {
            try {
                $card = $this->cards->open($card);
            } catch (CardProviderException) {
                return $this->stop($topup, 'CARD_OPEN_FAILED');
            }

            if ($card->status === Card::STATUS_PAYMENT_REQUIRED) {
                return $this->stop($topup, 'CARD_PAYMENT_REQUIRED');
            }
            if (! $card->isOpenAtProvider()) {
                return $this->stop($topup, 'CARD_OPEN_FAILED');
            }
            $issuance = LepoTopUps::ISSUANCE_FEE_CENTS;
        }

        // 3. Load the US dollars now on the account onto the card.
        $value = $this->availableUsd($userId);
        if ($value === null) {
            return $this->stop($topup, 'BALANCE_UNREADABLE');
        }
        if (bccomp($value, self::LOAD_MINIMUM, 2) === -1) {
            return $this->stop($topup, 'BELOW_LOAD_MINIMUM');
        }

        $this->mark($topup, CardTopUp::STATUS_LOADING);
        try {
            $loaded = $this->client->topUpCard((string) $card->issuer_card_token, $value);
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

        $fee = $loaded['fee'] ?? null;
        $topup->forceFill([
            'status'             => CardTopUp::STATUS_COMPLETED,
            'credited_cents'     => (int) bcmul($value, '100', 0),
            'fee_cents'          => is_int($fee) || is_float($fee) || (is_string($fee) && is_numeric($fee)) ? (int) bcmul(sprintf('%.6F', (float) $fee), '100', 0) : null,
            'issuance_fee_cents' => $issuance,
            'provider_load_id'   => is_scalar($loaded['orderId'] ?? null) ? (string) $loaded['orderId'] : null,
            'step_started_at'    => null,
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
     * The available US-dollar balance at Lepo, 2 decimals, rounded down.
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

        $total = '0';
        $seen = false;
        foreach ($assets as $asset) {
            if (! is_array($asset) || strtoupper((string) ($asset['currency'] ?? '')) !== 'USD') {
                continue;
            }

            $available = $asset['availableBalance'] ?? $asset['balance'] ?? null;
            $text = is_string($available) || is_int($available) ? trim((string) $available) : '';
            if (preg_match('/^\d+(\.\d+)?$/', $text) !== 1 || ! is_numeric($text)) {
                return null;
            }

            $total = bcadd($total, $text, 6);
            $seen = true;
        }

        return $seen ? bcadd($total, '0', 2) : null;
    }

    private function mark(CardTopUp $topup, string $status): void
    {
        $topup->forceFill(['status' => $status, 'step_started_at' => now()])->save();
        $this->topups->announce($topup);
    }

    private function stop(CardTopUp $topup, string $reason): CardTopUp
    {
        $topup->forceFill(['status' => CardTopUp::STATUS_NEEDS_ATTENTION, 'last_error' => $reason, 'step_started_at' => null])->save();
        Log::error('Lepo top-up needs attention', ['topup_id' => $topup->id, 'reason' => $reason]);
        $this->topups->announce($topup);

        return $topup;
    }
}
