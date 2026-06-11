<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Asset\Services\ExchangeRateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tracks the real cost of gas-sponsored wallet sends.
 *
 * Sponsored sends spend platform money on every transaction — Pimlico
 * paymaster gas on EVM, sponsor-account SOL on Solana. The count caps in
 * `config/wallet.php` bound *volume*, but a count alone is blind to gas
 * price: a slow bleed of expensive sends stays invisible under it. This
 * service records the actual per-send fee at confirmation time and keeps a
 * daily USD spend counter so the prepare path can also enforce a
 * value-denominated budget (`wallet.sponsorship.daily_budget_usd`).
 *
 * Budget semantics — checked pre-send, incremented post-confirmation: the
 * counter only grows once the on-chain fee is known, so a burst of
 * in-flight sends can overshoot the configured budget. Accepted: the
 * overshoot is bounded by the per-user/global count caps, and a hard
 * pre-send reservation would have to guess the fee (and block sends
 * whenever rates are unavailable).
 *
 * USD conversion goes through the platform's ExchangeRateService
 * (read-only). When no native→USD rate is available the increment is
 * logged and skipped — cost tracking must never block or fail a
 * confirmation path.
 */
class SponsorshipCostTracker
{
    /**
     * The budget counter's minor unit is micro-USD (1e-6 USD). Cents are
     * too coarse — a single L2 send costs fractions of a cent and would
     * round to zero forever.
     */
    private const string USD_MICRO_FACTOR = '1000000';

    public function __construct(
        private readonly ExchangeRateService $exchangeRates,
    ) {
    }

    /**
     * Native asset code recorded on the send record for the given network.
     */
    public static function feeAssetForNetwork(string $network): ?string
    {
        return match (strtolower($network)) {
            'solana'   => 'SOL',
            'polygon'  => 'MATIC',
            'base'     => 'ETH-BASE',
            'arbitrum' => 'ETH-ARB',
            'ethereum' => 'ETH',
            default    => null,
        };
    }

    /**
     * Decimals of the network's native fee unit (lamports → SOL = 9,
     * wei → ETH/MATIC = 18).
     */
    public static function feeDecimalsForNetwork(string $network): int
    {
        return strtolower($network) === 'solana' ? 9 : 18;
    }

    /**
     * Symbol used for the USD rate lookup. L2 fee assets price as ETH.
     */
    public static function rateSymbolForNetwork(string $network): ?string
    {
        return match (strtolower($network)) {
            'solana'                       => 'SOL',
            'polygon'                      => 'MATIC',
            'base', 'arbitrum', 'ethereum' => 'ETH',
            default                        => null,
        };
    }

    /**
     * Whether the Solana fee-payer (sponsor) account is configured — i.e.
     * whether the platform, not the sender, pays Solana tx fees.
     */
    public function isSolanaSponsorConfigured(): bool
    {
        return (string) config('wallet.solana.sponsor.secret_key', '') !== '';
    }

    /**
     * Configured USD budget per UTC day, or null when budget enforcement is
     * disabled (WALLET_SPONSORSHIP_DAILY_BUDGET_USD unset / non-numeric / <= 0).
     *
     * @return numeric-string|null
     */
    public function dailyBudgetUsd(): ?string
    {
        $budget = config('wallet.sponsorship.daily_budget_usd');

        if (! is_numeric($budget)) {
            return null;
        }

        $normalized = bcadd((string) $budget, '0', 6);

        return bccomp($normalized, '0', 6) > 0 ? $normalized : null;
    }

    /**
     * Whether today's accumulated sponsored spend has reached the configured
     * USD budget. Always false when no budget is configured.
     */
    public function isDailyBudgetExhausted(): bool
    {
        $budget = $this->dailyBudgetUsd();

        if ($budget === null) {
            return false;
        }

        $spentMicro = (string) $this->todaysSpendUsdMicro();
        $budgetMicro = bcmul($budget, self::USD_MICRO_FACTOR, 0);

        return bccomp($spentMicro, $budgetMicro, 0) >= 0;
    }

    /**
     * Accumulated sponsored spend for the current UTC day, in micro-USD.
     */
    public function todaysSpendUsdMicro(): int
    {
        $value = Cache::get(self::budgetCacheKey());

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Cache key for today's (UTC) global USD spend counter. Day-stamped so
     * it naturally resets at 00:00 UTC, matching the count-cap keys.
     */
    public static function budgetCacheKey(): string
    {
        return 'wallet_send:budget_usd_micro:global:' . now()->utc()->format('Y-m-d');
    }

    /**
     * Cache key for today's (UTC) global send count — shared with the
     * count-cap enforcement in MobileWalletController.
     */
    public static function globalSendCountCacheKey(): string
    {
        return 'wallet_send:count:global:' . now()->utc()->format('Y-m-d');
    }

    /**
     * Add a confirmed send's sponsored fee to today's USD spend counter.
     *
     * Best-effort by design: when no native→USD rate is available the
     * increment is skipped (with a warning) rather than blocking the
     * confirmation path. The raw fee is still persisted on the send record,
     * so the spend can be reconciled later.
     *
     * @param string $feeRaw Native units as a decimal string (lamports/wei)
     */
    public function recordSpendUsd(string $network, string $feeRaw): void
    {
        if (! is_numeric($feeRaw) || bccomp(bcadd($feeRaw, '0', 0), '0', 0) <= 0) {
            return;
        }

        $usdMicro = $this->estimateUsdMicro($network, $feeRaw);

        if ($usdMicro === null) {
            Log::warning('Sponsored-send cost: no USD rate available, skipping budget increment', [
                'network' => $network,
                'fee_raw' => $feeRaw,
            ]);

            return;
        }

        $key = self::budgetCacheKey();

        // Cache::add + increment — never read-then-write (concurrency-safe).
        Cache::add($key, 0, now()->addDay());
        Cache::increment($key, $usdMicro);
    }

    /**
     * Conservative micro-USD estimate for a native fee amount, rounded UP
     * to a whole micro-USD so dust-sized fees are never counted as zero.
     */
    public function estimateUsdMicro(string $network, string $feeRaw): ?int
    {
        $usd = $this->estimateUsd($network, $feeRaw);

        if ($usd === null) {
            return null;
        }

        $micro = bcmul($usd, self::USD_MICRO_FACTOR, 6);
        $floor = bcadd($micro, '0', 0);

        if (bccomp($micro, $floor, 6) > 0) {
            $floor = bcadd($floor, '1', 0);
        }

        return (int) $floor;
    }

    /**
     * USD estimate for a native fee amount, or null when the network is
     * unknown or no rate is available.
     *
     * @return numeric-string|null USD value at 12-decimal precision
     */
    public function estimateUsd(string $network, string $feeRaw): ?string
    {
        if (! is_numeric($feeRaw)) {
            return null;
        }

        $symbol = self::rateSymbolForNetwork($network);

        if ($symbol === null) {
            return null;
        }

        $rate = $this->usdRateFor($symbol);

        if ($rate === null) {
            return null;
        }

        $decimals = self::feeDecimalsForNetwork($network);
        $major = bcdiv(bcadd($feeRaw, '0', 0), bcpow('10', (string) $decimals, 0), $decimals);

        return bcmul($major, $rate, 12);
    }

    /**
     * Convert a hex quantity (`0x…`) to a base-10 string via bcmath —
     * 256-bit EVM values overflow hexdec()/PHP ints.
     *
     * @return numeric-string
     */
    public static function hexToDecimalString(string $hex): string
    {
        $hex = (string) preg_replace('/^0x/i', '', trim($hex));

        if ($hex === '' || ! ctype_xdigit($hex)) {
            return '0';
        }

        /** @var numeric-string $decimal */
        $decimal = '0';

        foreach (str_split($hex) as $nibble) {
            $decimal = bcadd(bcmul($decimal, '16', 0), (string) (int) hexdec($nibble), 0);
        }

        return $decimal;
    }

    /**
     * Current native→USD rate, read through the platform rate service.
     *
     * @return numeric-string|null
     */
    private function usdRateFor(string $symbol): ?string
    {
        try {
            $rate = $this->exchangeRates->getRate($symbol, 'USD');
        } catch (Throwable $e) {
            Log::warning('Sponsored-send cost: exchange rate lookup failed', [
                'symbol' => $symbol,
                'error'  => $e->getMessage(),
            ]);

            return null;
        }

        if ($rate === null) {
            return null;
        }

        $value = (string) $rate->rate;

        if (! is_numeric($value)) {
            return null;
        }

        return bcadd($value, '0', 10);
    }
}
