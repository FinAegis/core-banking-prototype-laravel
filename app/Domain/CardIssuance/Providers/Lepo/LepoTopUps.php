<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers\Lepo;

use App\Domain\CardIssuance\Events\Broadcast\CardTopUpUpdated;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\CardDepositAddress;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardTopUp;
use App\Domain\CardIssuance\Providers\CardProviderException;
use App\Domain\CardIssuance\Providers\CardProviderSelector;
use App\Infrastructure\Lepo\LepoApiException;
use App\Infrastructure\Lepo\LepoClient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;

/**
 * "Add to card": estimates, starts, records the wallet transaction hash of,
 * and cancels top-ups. A top-up waits for the user's USDC deposit to the
 * Lepo address shown here; the money pipeline takes it from there.
 * Amounts are USDC decimal strings (bcmath); cents are USD.
 */
final class LepoTopUps
{
    /** Cards in these statuses cannot receive money. */
    private const CLOSED_CARD_STATUSES = [Card::STATUS_CANCELLED, Card::STATUS_EXPIRED, Card::STATUS_FAILED, Card::STATUS_BLOCKED];

    /** Lepo takes this from the user's balance when it opens the card. */
    public const ISSUANCE_FEE_CENTS = 100;

    public function __construct(private readonly LepoClient $client)
    {
    }

    public static function enabled(): bool
    {
        return (bool) config('cardissuance.providers.lepo_topups_enabled', false);
    }

    /**
     * @return numeric-string
     */
    public static function minimum(): string
    {
        return bcadd(self::number((string) config('cardissuance.providers.lepo_topup_min', '10.00')), '0', 2);
    }

    /**
     * @return numeric-string
     */
    public static function maximum(): string
    {
        return bcadd(self::number((string) config('cardissuance.providers.lepo_topup_max', '1000.00')), '0', 2);
    }

    /**
     * A plain decimal ("25", "25.50"); anything else is refused as out of range.
     *
     * @return numeric-string
     */
    public static function number(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d+(\.\d+)?$/', $value) !== 1 || ! is_numeric($value)) {
            throw CardProviderException::topUp('amount_out_of_range');
        }

        return $value;
    }

    /**
     * What the card receives for this USDC amount, from Lepo's quote: the
     * USDC→USD rate and conversion fee, Lepo's top-up fee, and the one-time
     * issuance fee when the card is not open yet.
     *
     * @return array{amount: string, asset: string, network: string, credited_cents: int, fee_cents: int, issuance_fee_cents: int, min_amount: string, max_amount: string}
     */
    public function estimate(Card $card, string $amount, string $network): array
    {
        $amount = self::number($amount);
        $this->assertCanTopUp($card, $amount, $network);

        try {
            $quote = $this->client->estimateCardTopup(['userId' => $this->lepoUserId($card), 'amount' => $amount]);
        } catch (LepoApiException $e) {
            throw CardProviderException::providerUnavailable('lepo', $e->lepoCode());
        }

        $leg = is_array($quote['usdcToUsd'] ?? null) ? $quote['usdcToUsd'] : [];
        $rate = $this->decimal($leg['rate'] ?? null);
        if ($rate === null || bccomp($rate, '0', 8) !== 1) {
            throw CardProviderException::providerUnavailable('lepo', 'NO_QUOTE');
        }

        $conversionFee = $this->decimal($leg['fee'] ?? null) ?? '0';
        if (in_array(strtoupper((string) ($leg['feeCurrency'] ?? 'USD')), ['USDC', 'USDT'], true)) {
            $conversionFee = bcmul($conversionFee, $rate, 8);
        }
        $issuance = $card->isOpenAtProvider() ? 0 : self::ISSUANCE_FEE_CENTS;

        // Dollars after the conversion, less the issue fee on the first
        // top-up: that is what gets loaded onto the card.
        $loaded = bcsub(bcsub(bcmul($amount, $rate, 8), $conversionFee, 8), bcdiv((string) $issuance, '100', 2), 8);

        // Lepo (2026-11-23): the load fee is a percentage of the loaded amount,
        // taken out of it and rounded to the cent; topupfeepercent is a
        // fraction (0.015 = 1.5 %). Rounded up here, so the estimate never
        // promises more than the card receives. Only without a percentage is
        // topupfee used, as a fixed fee, so the fee is never counted twice.
        $topUpFeePercent = $this->decimal($quote['topupfeepercent'] ?? null) ?? '0';
        $topUpFee = bccomp($topUpFeePercent, '0', 8) === 1
            ? self::centsUp(bcmul(bccomp($loaded, '0', 8) === 1 ? $loaded : '0', $topUpFeePercent, 8))
            : ($this->decimal($quote['topupfee'] ?? null) ?? '0');

        $credited = max(0, (int) bcmul(bcsub($loaded, $topUpFee, 8), '100', 0));

        return [
            'amount'             => bcadd($amount, '0', 2),
            'asset'              => 'USDC',
            'network'            => $network,
            'credited_cents'     => $credited,
            'fee_cents'          => max(0, (int) bcmul(bcadd($conversionFee, $topUpFee, 8), '100', 0)),
            'issuance_fee_cents' => $issuance,
            'min_amount'         => self::minimum(),
            'max_amount'         => self::maximum(),
        ];
    }

    public function start(Card $card, string $amount, string $network): CardTopUp
    {
        $amount = self::number($amount);

        // One start per user at a time, so two taps cannot open two top-ups.
        return Cache::lock("cards:lepo:topup-start:{$card->user_id}", 10)->block(5, function () use ($card, $amount, $network): CardTopUp {
            $address = $this->assertCanTopUp($card, $amount, $network);

            $open = CardTopUp::query()->where('user_id', $card->user_id)->whereIn('status', CardTopUp::OPEN)->exists();
            if ($open) {
                throw CardProviderException::topUp('topup_in_progress');
            }

            $topup = new CardTopUp();
            $topup->forceFill([
                'user_id'            => $card->user_id,
                'card_id'            => $card->id,
                'funding_account_id' => $card->funding_account_id,
                'provider'           => 'lepo',
                'source'             => CardTopUp::SOURCE_APP,
                'asset'              => 'USDC',
                'network'            => $network,
                'amount'             => bcadd($amount, '0', 6),
                'deposit_address'    => $address,
                'status'             => CardTopUp::STATUS_AWAITING_DEPOSIT,
                'expires_at'         => now()->addMinutes(max(5, (int) config('cardissuance.providers.lepo_topup_ttl_minutes', 60))),
            ])->save();

            $this->announce($topup);

            return $topup;
        });
    }

    /**
     * The hash of the wallet send that funds the top-up (optional; it lets the
     * deposit be matched exactly). Recorded once.
     */
    public function attachTxHash(CardTopUp $topup, string $txHash): CardTopUp
    {
        // A conditional write: the deposit may be detected at the same moment.
        try {
            $changed = CardTopUp::query()->whereKey($topup->id)
                ->where('status', CardTopUp::STATUS_AWAITING_DEPOSIT)
                ->whereNull('tx_hash')
                ->update(['tx_hash' => strtolower($txHash), 'updated_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            $changed = 0;
        }

        if ($changed !== 1) {
            throw CardProviderException::topUp('not_cancellable');
        }

        return $topup->refresh();
    }

    public function cancel(CardTopUp $topup): CardTopUp
    {
        // A conditional write: a deposit detected meanwhile must not be hidden
        // under a cancelled top-up.
        $changed = CardTopUp::query()->whereKey($topup->id)
            ->where('status', CardTopUp::STATUS_AWAITING_DEPOSIT)
            ->whereNull('tx_hash')
            ->whereNull('provider_deposit_id')
            ->update(['status' => CardTopUp::STATUS_CANCELLED, 'updated_at' => now()]);

        if ($changed !== 1) {
            throw CardProviderException::topUp('not_cancellable');
        }

        $topup->refresh();
        $this->announce($topup);

        return $topup;
    }

    public function announce(CardTopUp $topup): void
    {
        CardTopUpUpdated::dispatch((int) $topup->user_id, $topup->id, $topup->card_id, $topup->status, $topup->credited_cents);
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(CardTopUp $topup): array
    {
        return [
            'id'                 => $topup->id,
            'card_id'            => $topup->card_id,
            'source'             => $topup->source,
            'status'             => $topup->status,
            'amount'             => bcadd(self::number((string) $topup->amount), '0', 2),
            'asset'              => $topup->asset,
            'network'            => $topup->network,
            'deposit_address'    => $topup->deposit_address,
            'tx_hash'            => $topup->tx_hash,
            'credited_cents'     => $topup->credited_cents,
            'fee_cents'          => $topup->fee_cents,
            'issuance_fee_cents' => $topup->issuance_fee_cents,
            'expires_at'         => $topup->expires_at->toIso8601String(),
            'created_at'         => $topup->created_at?->toIso8601String(),
            'completed_at'       => $topup->completed_at?->toIso8601String(),
        ];
    }

    /**
     * @param  numeric-string  $amount
     * @return string the deposit address for the network
     */
    private function assertCanTopUp(Card $card, string $amount, string $network): string
    {
        if (! self::enabled()) {
            throw CardProviderException::topUp('topups_disabled');
        }

        if (bccomp($amount, self::minimum(), 6) === -1 || bccomp($amount, self::maximum(), 6) === 1) {
            throw CardProviderException::topUp('amount_out_of_range');
        }

        if (in_array($card->status, self::CLOSED_CARD_STATUSES, true)) {
            throw CardProviderException::topUp('card_unavailable');
        }

        $address = in_array($network, CardProviderSelector::listFromConfig('cardissuance.providers.lepo_deposit_networks'), true) && $card->funding_account_id !== null
            ? CardDepositAddress::query()
                ->where('funding_account_id', $card->funding_account_id)
                ->where('network', $network)
                ->where('asset', 'USDC')
                ->where('is_active', true)
                ->value('address')
            : null;

        if (! is_string($address) || $address === '') {
            throw CardProviderException::topUp('network_unsupported');
        }

        return $address;
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
     * @return numeric-string|null
     */
    private function decimal(mixed $value): ?string
    {
        if (is_float($value)) {
            $value = is_finite($value) ? sprintf('%.8F', $value) : null;
        }

        $text = is_string($value) || is_int($value) ? trim((string) $value) : '';

        return preg_match('/^-?\d+(\.\d+)?$/', $text) === 1 && is_numeric($text) ? bcadd($text, '0', 8) : null;
    }

    /**
     * A non-negative amount rounded up to the cent.
     *
     * @param  numeric-string  $amount
     * @return numeric-string
     */
    private static function centsUp(string $amount): string
    {
        $cents = bcmul($amount, '100', 8);
        $whole = bcadd($cents, '0', 0);
        if (bccomp($cents, $whole, 8) === 1) {
            $whole = bcadd($whole, '1', 0);
        }

        return bcdiv($whole, '100', 2);
    }
}
