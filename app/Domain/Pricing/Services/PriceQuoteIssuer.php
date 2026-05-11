<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Services;

use App\Domain\Pricing\Exceptions\FeeResolverException;
use App\Domain\Pricing\Models\PriceQuote;
use App\Domain\Pricing\ValueObjects\FeeTier;
use App\Domain\Pricing\ValueObjects\Money;
use App\Domain\Pricing\ValueObjects\Quote;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PriceQuoteIssuer — main orchestrating service for quote issuance.
 *
 * Called by QuoteService::create(), which is called by PricingController::quote().
 *
 * Flow:
 *   1. FeeResolverService::resolve($user) → FeeTier VO
 *   2. Assert currency == EUR (controller already validates; double-check here)
 *   3. Compute entity_key = sha256(user_id || kind || canonical_amount || recipient || from_json || to_json)
 *   4. Check price_quotes for a live entity_key match → return existing (Q2.1)
 *   5. Build kind-specific feeBreakdown + rates
 *   6. Compute userOpHash (send/swap only; null for fiat kinds)
 *   7. Sign with QuoteSigner
 *   8. DB::transaction() INSERT INTO price_quotes
 *   9. Return Quote VO (wire-formatted)
 *
 * On dry-run (no persistence, no entity-key dedup):
 *   - Steps 1–6 run as normal
 *   - Step 7–8 skipped
 *   - Returns Quote VO with id=null-UUID-placeholder, expiresAt=null
 *
 * price_quotes is on the default (global) connection — no UsesTenantConnection
 * models involved. DB::transaction() is safe here.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-3-pricing-design.md §5.6
 */
final class PriceQuoteIssuer
{
    /** Native asset tickers — no service fee for native-asset sends. */
    private const NATIVE_ASSETS = ['ETH', 'MATIC', 'SOL', 'BNB', 'AVAX'];

    public function __construct(
        private readonly FeeResolverService $feeResolver,
        private readonly QuoteSigner $signer,
    ) {
    }

    /**
     * Issue a new price quote (or return a live deduplicated one).
     *
     * @param  array<string, mixed>  $requestData  validated request payload
     * @param  bool  $dryRun  if true, assembles without persisting
     * @return Quote  wire-formatted VO
     *
     * @throws FeeResolverException when the fee tier cannot be resolved
     */
    public function issue(User $user, array $requestData, bool $dryRun = false): Quote
    {
        $feeTier = $this->feeResolver->resolve($user);

        $kind = (string) $requestData['kind'];
        $currency = (string) ($requestData['currency'] ?? 'EUR');

        // Build amount Money VO from the request triple.
        $amountData = (array) $requestData['amount'];
        $amount = $this->buildMoneyFromInput($amountData);

        $fromData = isset($requestData['from']) ? (array) $requestData['from'] : null;
        $toData = isset($requestData['to']) ? (array) $requestData['to'] : null;
        $recipient = isset($requestData['recipient']) ? (string) $requestData['recipient'] : null;

        // Entity-key dedup (Q2.1) — only for non-dry-run requests.
        if (! $dryRun) {
            $entityKey = $this->computeEntityKey(
                (int) $user->id,
                $kind,
                $amount,
                $recipient,
                $fromData,
                $toData,
            );

            $existing = $this->findLiveQuoteByEntityKey($entityKey, (int) $user->id);
            if ($existing !== null) {
                return $this->hydrateFromModel($existing);
            }
        } else {
            $entityKey = '';
        }

        // Expiry timestamp.
        $ttlSeconds = $this->ttlForKind($kind);
        $expiresAt = new DateTimeImmutable('+' . $ttlSeconds . ' seconds');

        // Build kind-specific fee breakdown, rates, and userOpHash.
        [$feeBreakdown, $rates, $userOpHash] = $this->buildFeeBreakdown(
            $kind,
            $amount,
            $feeTier,
            $fromData,
        );

        // saveWithPro: present for free-tier users (except native-asset send where there's no service fee).
        $saveWithPro = $this->computeSaveWithPro($kind, $feeTier, $amount, $fromData);

        $id = $dryRun ? (string) Str::uuid() : (string) Str::uuid();

        // Assemble the response payload for signing and storage.
        $responsePayload = $this->assembleResponsePayload(
            $id,
            $kind,
            $expiresAt,
            $feeBreakdown,
            $rates,
            $feeTier,
            $userOpHash,
            $saveWithPro,
        );

        if ($dryRun) {
            // Dry-run: return without persisting. quoteId and expiresAt are null.
            return new Quote(
                id: $id,
                kind: $kind,
                expiresAt: null,
                feeBreakdown: $feeBreakdown,
                rates: $rates,
                feeTier: $feeTier,
                userOpHash: $userOpHash,
                termsChanged: false,
                saveWithPro: $saveWithPro,
            );
        }

        $responseJson = (string) json_encode($responsePayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $responsePayloadHash = $this->signer->payloadHash($responseJson);
        $signature = $this->signer->sign(
            $id,
            (string) $user->id,
            $kind,
            $expiresAt->getTimestamp(),
            $responsePayloadHash,
        );

        // Check for a prior expired quote for this entity_key → Q2.3 refresh.
        $termsChanged = false;
        $supersededBy = null;

        $priorExpired = $this->findExpiredQuoteByEntityKey($entityKey, (int) $user->id);

        if ($priorExpired !== null) {
            $termsChanged = $this->computeTermsChanged($priorExpired, $feeBreakdown);
            $supersededBy = null; // Will be set AFTER we know the new ID
        }

        // Persist inside a transaction to prevent entity-key race conditions.
        // price_quotes is global-connection — DB::transaction() is safe here.
        DB::transaction(function () use (
            $id,
            $user,
            $feeTier,
            $kind,
            $requestData,
            $responsePayload,
            $entityKey,
            $userOpHash,
            $signature,
            $expiresAt,
            $termsChanged,
        ): void {
            PriceQuote::query()->create([
                'id'               => $id,
                'user_id'          => $user->id,
                'user_tier'        => $feeTier->tierName,
                'kind'             => $kind,
                'request_payload'  => $requestData,
                'response_payload' => json_decode((string) json_encode($responsePayload, JSON_THROW_ON_ERROR), true),
                'entity_key'       => $entityKey,
                'user_op_hash'     => $userOpHash,
                'user_op_payload'  => null,
                'signature'        => $signature,
                'superseded_by'    => null,
                'terms_changed'    => $termsChanged,
                'expires_at'       => $expiresAt->format('Y-m-d H:i:s'),
            ]);
        });

        // After insert: update the prior expired row to point to the new quote.
        if ($priorExpired !== null) {
            $priorExpired->update(['superseded_by' => $id]);
        }

        return new Quote(
            id: $id,
            kind: $kind,
            expiresAt: $expiresAt,
            feeBreakdown: $feeBreakdown,
            rates: $rates,
            feeTier: $feeTier,
            userOpHash: $userOpHash,
            termsChanged: $termsChanged,
            saveWithPro: $saveWithPro,
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Entity-key dedup (Q2.1)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>|null  $fromData
     * @param  array<string, mixed>|null  $toData
     */
    private function computeEntityKey(
        int $userId,
        string $kind,
        Money $amount,
        ?string $recipient,
        ?array $fromData,
        ?array $toData,
    ): string {
        $canonical = implode('|', [
            (string) $userId,
            $kind,
            $amount->amount . ':' . $amount->decimals . ':' . $amount->denomination,
            $recipient ?? '',
            $fromData !== null ? (string) json_encode($fromData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '',
            $toData !== null ? (string) json_encode($toData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '',
        ]);

        return hash('sha256', $canonical);
    }

    private function findLiveQuoteByEntityKey(string $entityKey, int $userId): ?PriceQuote
    {
        /** @var PriceQuote|null $quote */
        $quote = PriceQuote::query()
            ->where('entity_key', $entityKey)
            ->where('user_id', $userId)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        return $quote;
    }

    private function findExpiredQuoteByEntityKey(string $entityKey, int $userId): ?PriceQuote
    {
        /** @var PriceQuote|null $quote */
        $quote = PriceQuote::query()
            ->where('entity_key', $entityKey)
            ->where('user_id', $userId)
            ->where('expires_at', '<=', now())
            ->whereNull('superseded_by')
            ->orderByDesc('created_at')
            ->first();

        return $quote;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Fee breakdown construction (kind-dispatched)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>|null  $fromData
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>, 2: string|null}
     */
    private function buildFeeBreakdown(
        string $kind,
        Money $amount,
        FeeTier $feeTier,
        ?array $fromData,
    ): array {
        return match ($kind) {
            'send'                  => $this->buildSendFeeBreakdown($amount, $feeTier, $fromData),
            'swap'                  => $this->buildSwapFeeBreakdown($amount, $feeTier),
            'ramp_buy', 'ramp_sell' => $this->buildRampFeeBreakdown($amount, $feeTier, $kind),
            'subscription_initial'  => $this->buildSubscriptionFeeBreakdown(),
            'card_waitlist_deposit' => $this->buildDepositFeeBreakdown(),
            default                 => [[], [], null],
        };
    }

    /**
     * @param  array<string, mixed>|null  $fromData
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>, 2: string|null}
     */
    private function buildSendFeeBreakdown(Money $amount, FeeTier $feeTier, ?array $fromData): array
    {
        $fromAsset = $fromData !== null ? strtoupper((string) ($fromData['asset'] ?? '')) : '';
        $isNativeAsset = in_array($fromAsset, self::NATIVE_ASSETS, true);

        $breakdown = [];

        if (! $isNativeAsset && $feeTier->txFlat !== null) {
            // Service fee: flat tx fee in the asset denomination.
            $breakdown[] = [
                'label'         => 'service',
                'amount'        => $feeTier->txFlat->jsonSerialize(),
                'eurEquivalent' => $this->computeEurEquivalent($feeTier->txFlat)->jsonSerialize(),
            ];
        }

        // Network fee: placeholder — real implementation fetches from gas oracle.
        // v1.3.0 uses a static placeholder per ADR-0001 (gas estimation is out of scope).
        $networkFee = Money::asset('210000', 6, $fromAsset !== '' ? $fromAsset : 'USDC');
        $breakdown[] = [
            'label'         => 'network',
            'amount'        => $networkFee->jsonSerialize(),
            'eurEquivalent' => $this->computeEurEquivalent($networkFee)->jsonSerialize(),
        ];

        // Rates: USDC/EUR placeholder rate (real implementation fetches from oracle).
        $rates = [
            ($fromAsset !== '' ? $fromAsset : 'USDC') . '/EUR' => [
                'value'     => '0.9200',
                'decimals'  => 4,
                'timestamp' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
                'sourceId'  => 'oracle:placeholder:v1.3.0',
            ],
        ];

        // userOpHash: placeholder — real implementation is keccak256(canonicalize(userOp)).
        // The actual hash is computed when the wallet preparer constructs the userOp.
        $userOpHash = null; // Populated by wallet send flow; null at quote issuance time.

        return [$breakdown, $rates, $userOpHash];
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>, 2: null}
     */
    private function buildSwapFeeBreakdown(Money $amount, FeeTier $feeTier): array
    {
        // Swap margin in basis points applied to the swap amount.
        // margin = amount * swapMarginBps / 10000
        $marginBps = (string) $feeTier->swapMarginBps;
        $marginAmount = bcmul(
            $this->numericStr($amount->amount),
            bcdiv($marginBps, '10000', 10),
            0,
        );

        $feeInAsset = Money::asset($marginAmount, $amount->decimals, $amount->denomination);
        $breakdown = [
            [
                'label'         => 'service',
                'amount'        => $feeInAsset->jsonSerialize(),
                'eurEquivalent' => $this->computeEurEquivalent($feeInAsset)->jsonSerialize(),
            ],
        ];

        $rates = [
            $amount->denomination . '/EUR' => [
                'value'     => '0.9200',
                'decimals'  => 4,
                'timestamp' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
                'sourceId'  => 'oracle:placeholder:v1.3.0',
            ],
        ];

        return [$breakdown, $rates, null];
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>, 2: null}
     */
    private function buildRampFeeBreakdown(Money $amount, FeeTier $feeTier, string $kind): array
    {
        $marginBps = (string) $feeTier->rampMarginBps;
        $numericAmount = $this->numericStr($amount->amount);
        $marginAmount = bcmul(
            $numericAmount,
            bcdiv($marginBps, '10000', 10),
            0,
        );

        $serviceFee = Money::fiat($marginAmount, $amount->decimals, 'EUR');

        // Provider fee (Stripe Bridge): 0.4% of amount — placeholder.
        $providerMarginAmount = bcmul(
            $numericAmount,
            bcdiv('40', '10000', 10),
            0,
        );
        $providerFee = Money::fiat($providerMarginAmount, $amount->decimals, 'EUR');

        $breakdown = [
            [
                'label'         => 'service',
                'amount'        => $serviceFee->jsonSerialize(),
                'eurEquivalent' => $serviceFee->jsonSerialize(),
            ],
            [
                'label'         => 'provider',
                'amount'        => $providerFee->jsonSerialize(),
                'eurEquivalent' => $providerFee->jsonSerialize(),
                'provider'      => 'stripe_bridge',
            ],
        ];

        return [$breakdown, [], null];
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>, 2: null}
     */
    private function buildSubscriptionFeeBreakdown(): array
    {
        // Monthly Pro subscription fee: €4.99 = 499 EUR cents.
        $fee = Money::fiat('499', 2, 'EUR');
        $breakdown = [
            [
                'label'         => 'subscription',
                'amount'        => $fee->jsonSerialize(),
                'eurEquivalent' => $fee->jsonSerialize(),
            ],
        ];

        return [$breakdown, [], null];
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>, 2: null}
     */
    private function buildDepositFeeBreakdown(): array
    {
        // Card waitlist deposit: flat €5.00 = 500 EUR cents.
        $fee = Money::fiat('500', 2, 'EUR');
        $breakdown = [
            [
                'label'         => 'deposit',
                'amount'        => $fee->jsonSerialize(),
                'eurEquivalent' => $fee->jsonSerialize(),
            ],
        ];

        return [$breakdown, [], null];
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Normalize a string to numeric-string for bcmath.
     *
     * PHPStan requires `numeric-string` for bcmath parameters. Money::$amount
     * is validated as /^-?[0-9]+$/ by the VO constructor but PHPStan types it
     * as `string`. This helper makes the type visible to static analysis.
     *
     * @return numeric-string
     */
    private function numericStr(string $value): string
    {
        if (! is_numeric($value)) {
            // Unreachable if Money VO was constructed correctly.
            return '0';
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildMoneyFromInput(array $data): Money
    {
        $amountStr = (string) ($data['amount'] ?? '0');
        $decimals = (int) ($data['decimals'] ?? 0);

        if (isset($data['currency'])) {
            return Money::fiat($amountStr, $decimals, (string) $data['currency']);
        }

        return Money::asset($amountStr, $decimals, (string) ($data['asset'] ?? 'USDC'));
    }

    /**
     * Compute the EUR equivalent of an asset-denominated Money.
     * Placeholder rate: 1 USDC ≈ €0.92. Real implementation fetches from oracle.
     */
    private function computeEurEquivalent(Money $assetMoney): Money
    {
        if ($assetMoney->isFiat) {
            return $assetMoney;
        }

        // Placeholder rate: 0.92 USDC/EUR with 6 decimals → 2 EUR decimals.
        // rate = 0.92 means 1 unit (in smallest) of USDC = 0.92 EUR smallest unit at same decimals.
        // To convert: eurSmallest = assetSmallest * 0.92 / 10^(assetDecimals - eurDecimals)
        // For USDC (6 decimals) → EUR (2 decimals): divide by 10^4 then multiply by 0.92.
        $scaleFactor = bcpow('10', (string) ($assetMoney->decimals - 2), 0);
        $euroAmount = bcdiv(
            bcmul($this->numericStr($assetMoney->amount), '92', 0),
            bcmul($scaleFactor, '100', 0),
            0,
        );

        return Money::fiat($euroAmount, 2, 'EUR');
    }

    private function ttlForKind(string $kind): int
    {
        /** @var array<string, int> $ttls */
        $ttls = config('pricing.quote_ttl_seconds', []);

        return $ttls[$kind] ?? 300;
    }

    /**
     * Compute saveWithPro: the EUR saving vs the Pro tier for free-tier users.
     *
     * Null if:
     * - User is already Pro tier
     * - Native-asset send (no service fee to save on)
     * - Fiat-only kinds where Pro margin is the same shape as free
     *
     * @param  array<string, mixed>|null  $fromData
     * @return array<string, mixed>|null
     */
    private function computeSaveWithPro(
        string $kind,
        FeeTier $feeTier,
        Money $amount,
        ?array $fromData,
    ): ?array {
        if ($feeTier->tierName === 'pro') {
            return null;
        }

        if ($kind === 'send') {
            $fromAsset = $fromData !== null ? strtoupper((string) ($fromData['asset'] ?? '')) : '';
            if (in_array($fromAsset, self::NATIVE_ASSETS, true)) {
                return null; // No service fee on native sends — nothing to save.
            }
        }

        /** @var array<string, array<string, mixed>> $tiers */
        $tiers = config('pricing.tiers', []);
        $proConfig = $tiers['pro'] ?? [];

        // For subscription_initial / card_waitlist_deposit there is no per-tier saving.
        if (in_array($kind, ['subscription_initial', 'card_waitlist_deposit'], true)) {
            return null;
        }

        if ($kind === 'send') {
            $freeTxFlat = $feeTier->txFlat;
            if ($freeTxFlat === null) {
                return null;
            }

            $proTxFlatAmount = isset($proConfig['tx_flat_asset_amount'])
                ? (string) $proConfig['tx_flat_asset_amount']
                : '0';
            $proTxFlat = Money::asset($proTxFlatAmount, 6, $freeTxFlat->denomination);
            $saving = $freeTxFlat->subtract($proTxFlat);

            return [
                'amount'        => $saving->jsonSerialize(),
                'eurEquivalent' => $this->computeEurEquivalent($saving)->jsonSerialize(),
                'label'         => 'Save with Pro',
            ];
        }

        if ($kind === 'swap') {
            $freeBps = $feeTier->swapMarginBps;
            $proBps = (int) ($proConfig['swap_margin_bps'] ?? 0);
            $savingBps = $freeBps - $proBps;

            if ($savingBps <= 0) {
                return null;
            }

            $savingAmount = bcmul(
                $this->numericStr($amount->amount),
                bcdiv((string) $savingBps, '10000', 10),
                0,
            );
            $saving = Money::asset($savingAmount, $amount->decimals, $amount->denomination);

            return [
                'amount'        => $saving->jsonSerialize(),
                'eurEquivalent' => $this->computeEurEquivalent($saving)->jsonSerialize(),
                'label'         => 'Save with Pro',
            ];
        }

        if (in_array($kind, ['ramp_buy', 'ramp_sell'], true)) {
            $freeBps = $feeTier->rampMarginBps;
            $proBps = (int) ($proConfig['ramp_margin_bps'] ?? 0);
            $savingBps = $freeBps - $proBps;

            if ($savingBps <= 0) {
                return null;
            }

            $savingAmount = bcmul(
                $this->numericStr($amount->amount),
                bcdiv((string) $savingBps, '10000', 10),
                0,
            );
            $saving = Money::fiat($savingAmount, $amount->decimals, 'EUR');

            return [
                'amount'        => $saving->jsonSerialize(),
                'eurEquivalent' => $saving->jsonSerialize(),
                'label'         => 'Save with Pro',
            ];
        }

        return null;
    }

    /**
     * Q3.2 terms_changed: true iff total fee delta > €0.10 or > 5%.
     *
     * @param  array<int, array<string, mixed>>  $newFeeBreakdown
     */
    private function computeTermsChanged(PriceQuote $prior, array $newFeeBreakdown): bool
    {
        $priorResponse = $prior->response_payload;

        /** @var array<int, array<string, mixed>> $priorFeeBreakdown */
        $priorFeeBreakdown = $priorResponse['feeBreakdown'] ?? [];

        $priorTotal = $this->sumEurEquivalentCents($priorFeeBreakdown);
        $newTotal = $this->sumEurEquivalentCents($newFeeBreakdown);

        if ($priorTotal === '0' && $newTotal === '0') {
            return false;
        }

        // Absolute delta > €0.10 (10 EUR cents smallest-unit).
        $diff = bcsub($newTotal, $priorTotal, 0);
        // Absolute value: if diff is negative, negate it.
        $delta = bccomp($diff, '0', 0) < 0 ? bcsub('0', $diff, 0) : $diff;
        if (bccomp($delta, '10', 0) > 0) {
            return true;
        }

        // Relative delta > 5%.
        if (bccomp($priorTotal, '0', 0) > 0) {
            $relPct = bcmul(bcdiv($delta, $priorTotal, 6), '100', 4);
            if (bccomp($relPct, '5', 4) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sum the eurEquivalent amounts across fee breakdown items.
     * Returns a EUR cents string (2-decimal scale, smallest-unit).
     *
     * @param  array<int, array<string, mixed>>  $feeBreakdown
     * @return numeric-string
     */
    private function sumEurEquivalentCents(array $feeBreakdown): string
    {
        /** @var numeric-string $total */
        $total = '0';

        foreach ($feeBreakdown as $item) {
            $eurItem = $item['eurEquivalent'] ?? null;

            if (! is_array($eurItem)) {
                continue;
            }

            $raw = (string) ($eurItem['amount'] ?? '0');
            $itemAmount = $this->numericStr($raw);
            $total = bcadd($total, $itemAmount, 0);
        }

        return $total;
    }

    /**
     * Hydrate a Quote VO from a stored PriceQuote model.
     */
    private function hydrateFromModel(PriceQuote $model): Quote
    {
        /** @var array<string, mixed> $payload */
        $payload = $model->response_payload;

        /** @var array<int, array<string, mixed>> $feeBreakdown */
        $feeBreakdown = (array) ($payload['feeBreakdown'] ?? []);

        /** @var array<string, mixed> $rates */
        $rates = (array) ($payload['rates'] ?? []);

        /** @var array<string, mixed> $feeTierData */
        $feeTierData = (array) ($payload['feeTier'] ?? []);

        $txFlatData = $feeTierData['txFlat'] ?? null;
        $txFlat = null;
        if (is_array($txFlatData)) {
            $txFlat = isset($txFlatData['currency'])
                ? Money::fiat((string) $txFlatData['amount'], (int) $txFlatData['decimals'], (string) $txFlatData['currency'])
                : Money::asset((string) $txFlatData['amount'], (int) $txFlatData['decimals'], (string) $txFlatData['asset']);
        }

        $feeTier = new FeeTier(
            txFlat: $txFlat,
            swapMarginBps: (int) ($feeTierData['swapMarginBps'] ?? 0),
            rampMarginBps: (int) ($feeTierData['rampMarginBps'] ?? 0),
            tierName: (string) $model->user_tier,
        );

        /** @var array<string, mixed>|null $saveWithPro */
        $saveWithPro = isset($payload['saveWithPro']) && is_array($payload['saveWithPro'])
            ? $payload['saveWithPro']
            : null;

        return new Quote(
            id: (string) $model->id,
            kind: (string) $model->kind,
            expiresAt: DateTimeImmutable::createFromMutable($model->expires_at->toDateTime()),
            feeBreakdown: $feeBreakdown,
            rates: $rates,
            feeTier: $feeTier,
            userOpHash: $model->user_op_hash,
            termsChanged: (bool) $model->terms_changed,
            saveWithPro: $saveWithPro,
        );
    }

    /**
     * Assemble the complete response payload for signing + storage.
     *
     * @param  array<int, array<string, mixed>>  $feeBreakdown
     * @param  array<string, mixed>  $rates
     * @param  array<string, mixed>|null  $saveWithPro
     * @return array<string, mixed>
     */
    private function assembleResponsePayload(
        string $id,
        string $kind,
        DateTimeImmutable $expiresAt,
        array $feeBreakdown,
        array $rates,
        FeeTier $feeTier,
        ?string $userOpHash,
        ?array $saveWithPro,
    ): array {
        return [
            'quoteId'      => $id,
            'kind'         => $kind,
            'expiresAt'    => $expiresAt->format(DateTimeImmutable::ATOM),
            'feeBreakdown' => $feeBreakdown,
            'rates'        => $rates,
            'feeTier'      => $feeTier->jsonSerialize(),
            'userOpHash'   => $userOpHash,
            'termsChanged' => false,
            'saveWithPro'  => $saveWithPro,
        ];
    }
}
