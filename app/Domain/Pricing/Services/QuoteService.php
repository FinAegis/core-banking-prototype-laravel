<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Services;

use App\Domain\Pricing\Exceptions\QuoteRedemptionException;
use App\Domain\Pricing\Models\PriceQuote;
use App\Domain\Pricing\ValueObjects\Quote;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * QuoteService — public facade exposing create, retrieve, and redeem.
 *
 * Downstream slices (slice 1 subscription checkout, slice 5 card deposit)
 * inject QuoteService. They do NOT inject PriceQuoteIssuer directly.
 *
 * Redemption contract (§5.4):
 *  1. Quote must exist and belong to the authenticated user → ERR_QUO_001 (404)
 *  2. Quote must not be consumed → ERR_QUO_002 (409)
 *  3. Quote must not be expired → ERR_QUOTE_001 (410)
 *  4. For send/swap: userOpHash must match → ERR_QUOTE_002 (409)
 *  5. DB::transaction() + lockForUpdate() — prevents concurrent redemption races
 *  6. Set consumed_at and consumed_by inside the transaction
 *
 * Multi-connection safety note:
 * price_quotes is on the default (global) connection — no UsesTenantConnection
 * models involved. DB::transaction() is safe here.
 * Callers that also write to tenant-connection models (Domain/Wallet) MUST
 * use the saga pattern: redeem inside its own transaction, then dispatch a
 * queued job for the tenant write.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-3-pricing-design.md §5.4
 */
final class QuoteService
{
    public function __construct(
        private readonly PriceQuoteIssuer $issuer,
    ) {
    }

    /**
     * Create a new quote (or return a live deduplicated one).
     *
     * @param  array<string, mixed>  $requestData  validated request payload
     */
    public function create(User $user, array $requestData, bool $dryRun = false): Quote
    {
        return $this->issuer->issue($user, $requestData, $dryRun);
    }

    /**
     * Retrieve a quote by ID for the authenticated user.
     *
     * Returns the Quote VO with a populated `status` field.
     * Only the quote owner can read it (ERR_QUO_001 if different user or not found).
     *
     * @throws QuoteRedemptionException with errorCode ERR_QUO_001 if not found or ownership mismatch
     */
    public function retrieve(string $quoteId, User $user): Quote
    {
        /** @var PriceQuote|null $model */
        $model = PriceQuote::query()->find($quoteId);

        if ($model === null || (int) $model->user_id !== (int) $user->id) {
            throw QuoteRedemptionException::notFound();
        }

        $status = $model->statusLabel();

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
                ? \App\Domain\Pricing\ValueObjects\Money::fiat((string) $txFlatData['amount'], (int) $txFlatData['decimals'], (string) $txFlatData['currency'])
                : \App\Domain\Pricing\ValueObjects\Money::asset((string) $txFlatData['amount'], (int) $txFlatData['decimals'], (string) $txFlatData['asset']);
        }

        $feeTier = new \App\Domain\Pricing\ValueObjects\FeeTier(
            txFlat: $txFlat,
            swapMarginBps: (int) ($feeTierData['swapMarginBps'] ?? 0),
            rampMarginBps: (int) ($feeTierData['rampMarginBps'] ?? 0),
            tierName: (string) $model->user_tier,
        );

        /** @var array<string, mixed>|null $saveWithPro */
        $saveWithPro = isset($payload['saveWithPro']) && is_array($payload['saveWithPro'])
            ? $payload['saveWithPro']
            : null;

        $quote = new Quote(
            id: (string) $model->id,
            kind: (string) $model->kind,
            expiresAt: DateTimeImmutable::createFromMutable($model->expires_at->toDateTime()),
            feeBreakdown: $feeBreakdown,
            rates: $rates,
            feeTier: $feeTier,
            userOpHash: $model->user_op_hash,
            termsChanged: (bool) $model->terms_changed,
            saveWithPro: $saveWithPro,
            status: $status,
        );

        return $quote;
    }

    /**
     * Redeem a quote — mark it consumed inside a DB::transaction with lockForUpdate.
     *
     * @param  string|null  $userOpHash  keccak256 of signed userOp (send/swap only)
     * @param  string  $consumedBy  reference string (tx_hash | subscription_id | deposit_id)
     *
     * @throws QuoteRedemptionException on any redemption failure
     */
    public function redeem(
        string $quoteId,
        User $user,
        string $consumedBy,
        ?string $userOpHash = null,
    ): void {
        DB::transaction(function () use ($quoteId, $user, $consumedBy, $userOpHash): void {
            /** @var PriceQuote|null $quote */
            $quote = PriceQuote::query()
                ->where('id', $quoteId)
                ->lockForUpdate()
                ->first();

            // 1. Must exist and belong to the authenticated user.
            if ($quote === null || (int) $quote->user_id !== (int) $user->id) {
                throw QuoteRedemptionException::notFound();
            }

            // 2. Must not already be consumed.
            if ($quote->isConsumed()) {
                throw QuoteRedemptionException::alreadyConsumed();
            }

            // 3. Must not be expired.
            if ($quote->expires_at->isPast()) {
                throw QuoteRedemptionException::expired();
            }

            // 4. For send/swap: submitted userOpHash must match stored hash.
            if (
                $userOpHash !== null
                && $quote->user_op_hash !== null
                && ! hash_equals($quote->user_op_hash, $userOpHash)
            ) {
                throw QuoteRedemptionException::payloadMismatch();
            }

            // 5+6. Mark consumed.
            $quote->update([
                'consumed_at' => now(),
                'consumed_by' => $consumedBy,
            ]);
        });
    }
}
