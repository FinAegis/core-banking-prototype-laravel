<?php

/**
 * WaitlistDepositService — Plan B Slice 5 deposit lifecycle orchestrator.
 *
 * Entry points map 1:1 to the three new API endpoints + the webhook handler:
 *   - startDeposit:  POST /api/v1/cards/waitlist/deposit
 *   - cancelDeposit: POST /api/v1/cards/waitlist/deposit/cancel
 *   - entryFor:      GET  /api/v1/cards/waitlist/entry
 *   - applyCheckoutCompleted / applyCheckoutExpired / applyChargeRefunded:
 *     called by CardWaitlistWebhookController inside the dedup transaction.
 *
 * Design decisions adopted (spec §8):
 *   OD-1 — synchronous Stripe refund call on cancel; on 5xx mark
 *          refund_pending_manual and return 200 to user (Q9.4).
 *   OD-3 — reject (ERR_CARDS_002) on double-start, never replace.
 *   OD-4 — do NOT reset price_quotes.consumed_at on deposit expiry;
 *          the user issues a fresh quote (TTL is 5 minutes anyway).
 *   OD-5 — implement /entry only; /deposit/status is not added in slice 5.
 *
 * Q9.3 race avoidance: cancel and card.shipped both perform a CHECK-constrained
 * UPDATE against `status IN ('pending_payment', 'paid')`. Whichever commits
 * first wins; the other sees affected_rows = 0 and returns ERR_CARDS_004.
 *
 * Multi-connection safety: card_waitlist_deposits + card_waitlist +
 * price_quotes + processed_webhook_events + revenue_outbox_events all live
 * on the default global connection — DB::transaction() is safe here.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-5-card-waitlist-deposit-design.md
 */

declare(strict_types=1);

namespace App\Domain\CardIssuance\Services;

use App\Domain\CardIssuance\Exceptions\ActiveDepositRaceException;
use App\Domain\CardIssuance\Models\CardWaitlist;
use App\Domain\CardIssuance\Models\CardWaitlistDeposit;
use App\Domain\Pricing\Exceptions\QuoteRedemptionException;
use App\Domain\Pricing\Models\PriceQuote;
use App\Domain\Pricing\Services\QuoteService;
use App\Domain\Pricing\ValueObjects\Money;
use App\Domain\Subscription\Models\RevenueOutboxEvent;
use App\Domain\Subscription\Models\SubscriptionConsentLog;
use App\Models\User;
use App\Support\ErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Stripe\StripeClient;
use Throwable;

final class WaitlistDepositService
{
    /**
     * Source type stored on processed_webhook_events.provider AND on
     * revenue_outbox_events.source_type for the card deposit flow. Distinct
     * from 'stripe' (slice 1 subscription path) so the two webhook streams
     * never collide on dedup or outbox rows.
     */
    public const WEBHOOK_PROVIDER = 'stripe_cards';

    /**
     * Outbox row source_type — distinct from slice 1's `stripe` source. The
     * RevenueOutboxEvent::SOURCE_* constants don't yet cover this; we use
     * the literal string in payloads (the projector inspects event_kind).
     */
    public const OUTBOX_SOURCE = 'stripe_cards';

    public const CHECKOUT_FLOW = 'card_waitlist_deposit';

    public function __construct(
        private readonly QuoteService $quoteService,
    ) {
    }

    /**
     * Resolve the Stripe SDK client lazily.
     *
     * Constructor-injected StripeClient would force eager binding-callback
     * evaluation in AppServiceProvider::boot at controller resolution time,
     * which fails in test environments where `cashier.secret` is empty.
     * Slice 1 uses the same lazy pattern.
     */
    private function stripe(): StripeClient
    {
        return app(StripeClient::class);
    }

    /**
     * POST /api/v1/cards/waitlist/deposit — start a Stripe Checkout session.
     *
     * Returns a JsonResponse for direct return from the controller. The shape
     * matches §7.2; errors map to §7.3 via ErrorResponse::make().
     *
     * @param  array{quoteId: string, withdrawalConsent?: array<string, mixed>|null, successUrl?: string|null, cancelUrl?: string|null}  $input
     */
    public function startDeposit(User $user, array $input, ?string $remoteIp): JsonResponse
    {
        // Step 1 — return-URL allow-list (cheap; fail fast).
        $successUrl = $this->resolveReturnUrl($input['successUrl'] ?? null);
        $cancelUrl = $this->resolveReturnUrl($input['cancelUrl'] ?? null);
        if ($successUrl === null || $cancelUrl === null) {
            return ErrorResponse::make('ERR_CARDS_005');
        }

        // Step 2 — user must be on the waitlist.
        $waitlistRow = CardWaitlist::query()->where('user_id', $user->id)->first();
        if (! $waitlistRow instanceof CardWaitlist) {
            return ErrorResponse::make('ERR_CARDS_001');
        }

        // Step 3 — quote kind check happens BEFORE redeem so a wrong-kind quote
        // doesn't get marked consumed unnecessarily. We do a non-locking read
        // here; redeem() re-checks under lock.
        /** @var PriceQuote|null $quote */
        $quote = PriceQuote::query()->find($input['quoteId']);
        if ($quote === null || (int) $quote->user_id !== (int) $user->id) {
            return ErrorResponse::make('ERR_QUO_001');
        }
        if ($quote->kind !== 'card_waitlist_deposit') {
            return ErrorResponse::make('ERR_CARDS_006');
        }

        // Step 4 — server-side amount check (defense-in-depth vs. tampered
        // quote rows). Compare the quote's feeBreakdown deposit amount against
        // the config-pinned constant; abort on mismatch.
        $expectedCents = (int) config('cards.deposit_amount_cents', 500);
        $expectedDecimals = (int) config('cards.deposit_decimals', 2);
        $expectedCurrency = (string) config('cards.deposit_currency', 'EUR');
        $breakdownMoney = $this->extractDepositMoney($quote->response_payload);
        if (
            $breakdownMoney === null
            || (string) $breakdownMoney->amount !== (string) $expectedCents
            || $breakdownMoney->decimals !== $expectedDecimals
            || $breakdownMoney->denomination !== $expectedCurrency
        ) {
            Log::warning('cards.deposit.start.quote_amount_mismatch', [
                'user_id'      => $user->id,
                'quote_id'     => $quote->id,
                'breakdown'    => $quote->response_payload['feeBreakdown'] ?? null,
                'expected_eur' => $expectedCents,
            ]);

            return ErrorResponse::make('ERR_QUO_001');
        }

        // Step 5 — single-active-deposit invariant (OD-3 reject).
        // We use a non-locking pre-check here, then re-check inside the
        // transaction below. The combination of the unique index on quote_id
        // and the in-transaction re-check is sufficient under contention.
        $activeExists = CardWaitlistDeposit::query()
            ->where('user_id', $user->id)
            ->whereIn('status', CardWaitlistDeposit::ACTIVE_STATUSES)
            ->exists();
        if ($activeExists) {
            return ErrorResponse::make('ERR_CARDS_002');
        }

        // Step 6 — redeem the quote OUTSIDE the Stripe call (compensation
        // pattern per spec §10 risk #5). Doing the redeem here marks the
        // quote consumed; if Stripe then fails, we un-redeem before
        // surfacing the error to the user.
        try {
            $this->quoteService->redeem(
                quoteId: $quote->id,
                user: $user,
                consumedBy: 'card_waitlist_deposit:pending',
                userOpHash: null, // fiat-only kind, no userOp
            );
        } catch (QuoteRedemptionException $e) {
            return ErrorResponse::make($e->errorCode);
        }

        // Step 7 — create the Stripe Checkout session (external call,
        // intentionally NOT inside any DB transaction).
        $consentPayload = $input['withdrawalConsent'] ?? null;
        $consentLogId = null;
        if (is_array($consentPayload)) {
            $consentLogId = $this->writeConsentLog($user, $consentPayload, $remoteIp);
        }

        try {
            $session = $this->stripe()->checkout->sessions->create([
                'mode'                 => 'payment',
                'payment_method_types' => ['card'],
                'line_items'           => [[
                    'price_data' => [
                        'currency'     => strtolower($expectedCurrency),
                        'product_data' => [
                            'name'        => 'Zelta card waitlist deposit',
                            'description' => 'Refundable €5 deposit to reserve your spot on the Zelta card waitlist.',
                        ],
                        'unit_amount' => $expectedCents,
                    ],
                    'quantity' => 1,
                ]],
                'success_url' => $successUrl,
                'cancel_url'  => $cancelUrl,
                'metadata'    => [
                    'flow'    => self::CHECKOUT_FLOW,
                    'userId'  => (string) $user->id,
                    'quoteId' => (string) $quote->id,
                ],
                // Stripe Checkout sessions auto-expire after 24h by default; explicit
                // expiry helps our purge cron line up exactly with Stripe's window.
                'expires_at' => now()->addHours((int) config('cards.session_ttl_hours', 24))->getTimestamp(),
            ]);
        } catch (Throwable $e) {
            Log::error('cards.deposit.start.stripe_failed', [
                'user_id'  => $user->id,
                'quote_id' => $quote->id,
                'error'    => $e->getMessage(),
            ]);
            // Compensate: un-redeem the quote so the user can retry. We do this
            // in a fresh transaction outside the failed Stripe call.
            $this->unredeemQuote($quote->id);

            throw $e;
        }

        // Step 8 — persist the deposit row.
        $depositId = (string) Str::uuid();
        $sessionId = (string) $session->id;
        $checkoutUrl = (string) $session->url;
        $expiresAtTimestamp = $session->expires_at;
        $expiresAt = is_int($expiresAtTimestamp)
            ? Carbon::createFromTimestamp($expiresAtTimestamp)
            : now()->addHours((int) config('cards.session_ttl_hours', 24));

        try {
            DB::transaction(function () use ($user, $depositId, $quote, $sessionId, $consentLogId, $expectedCents, $expectedDecimals, $expectedCurrency): void {
                // Re-check the single-active-deposit invariant under a lock
                // to close the race between Step 5 and the INSERT.
                $activeUnderLock = CardWaitlistDeposit::query()
                    ->where('user_id', $user->id)
                    ->whereIn('status', CardWaitlistDeposit::ACTIVE_STATUSES)
                    ->lockForUpdate()
                    ->exists();
                if ($activeUnderLock) {
                    throw new ActiveDepositRaceException();
                }

                CardWaitlistDeposit::query()->create([
                    'id'                         => $depositId,
                    'user_id'                    => $user->id,
                    'quote_id'                   => $quote->id,
                    'status'                     => CardWaitlistDeposit::STATUS_PENDING_PAYMENT,
                    'deposit_amount_cents'       => $expectedCents,
                    'deposit_decimals'           => $expectedDecimals,
                    'deposit_currency'           => $expectedCurrency,
                    'stripe_checkout_session_id' => $sessionId,
                    'withdrawal_consent_log_id'  => $consentLogId,
                ]);
            });
        } catch (ActiveDepositRaceException) {
            // Another concurrent request beat us to the active-deposit slot.
            // Compensate: expire the now-orphaned Stripe session + un-redeem
            // the quote so the user can retry once they cancel their other
            // deposit.
            $this->expireStripeSessionSafely($sessionId);
            $this->unredeemQuote($quote->id);

            return ErrorResponse::make('ERR_CARDS_002');
        } catch (Throwable $e) {
            // Non-race transaction failure (DB connection drop, unexpected
            // constraint, OOM). Without compensation the quote stays consumed
            // and the Stripe session stays alive — the user could pay via the
            // live link but /entry would never reflect it. Roll both sides
            // back before letting the 500 propagate.
            Log::error('cards.deposit.start.persist_failed', [
                'user_id'    => $user->id,
                'quote_id'   => $quote->id,
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);
            $this->expireStripeSessionSafely($sessionId);
            $this->unredeemQuote($quote->id);

            throw $e;
        }

        return response()->json([
            'depositId'     => $depositId,
            'checkoutUrl'   => $checkoutUrl,
            'sessionId'     => $sessionId,
            'depositAmount' => Money::fiat((string) $expectedCents, $expectedDecimals, $expectedCurrency)->jsonSerialize(),
            'expiresAt'     => $expiresAt->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/cards/waitlist/deposit/cancel — Q9.3 atomic state transition
     * + synchronous Stripe refund (OD-1).
     */
    public function cancelDeposit(User $user): JsonResponse
    {
        // We perform the lookup + CHECK-constrained UPDATE atomically. If
        // affected_rows = 0 either there's no active deposit (404) or the
        // status moved away from pending_payment/paid mid-flight (409).
        // The two cases are distinguished by a post-UPDATE lookup.

        /** @var array{deposit: CardWaitlistDeposit, priorStatus: string}|array{notFound: true}|array{stateConflict: true} $result */
        $result = DB::transaction(function () use ($user): array {
            /** @var CardWaitlistDeposit|null $deposit */
            $deposit = CardWaitlistDeposit::query()
                ->where('user_id', $user->id)
                ->whereIn('status', CardWaitlistDeposit::ACTIVE_STATUSES)
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            if (! $deposit instanceof CardWaitlistDeposit) {
                return ['notFound' => true];
            }

            $priorStatus = (string) $deposit->status;

            // Q9.3 CHECK-constrained UPDATE — only cancellable from
            // pending_payment or paid (NOT cancellation_requested or terminal).
            $affected = CardWaitlistDeposit::query()
                ->where('id', $deposit->id)
                ->whereIn('status', CardWaitlistDeposit::CANCELLABLE_STATUSES)
                ->update([
                    'status'       => CardWaitlistDeposit::STATUS_CANCELLATION_REQUESTED,
                    'cancelled_at' => now(),
                ]);

            if ($affected === 0) {
                // Either status was 'cancellation_requested' already (idempotent)
                // or moved to a terminal state between SELECT and UPDATE.
                return ['stateConflict' => true];
            }

            // Reload to get the post-UPDATE row.
            $deposit->refresh();

            return ['deposit' => $deposit, 'priorStatus' => $priorStatus];
        });

        if (isset($result['notFound'])) {
            return ErrorResponse::make('ERR_CARDS_003');
        }

        if (isset($result['stateConflict'])) {
            return ErrorResponse::make('ERR_CARDS_004');
        }

        $deposit = $result['deposit'];
        $priorStatus = $result['priorStatus'];

        // Q9.4 — Stripe API call OUTSIDE the transaction. Failures land the
        // row in refund_pending_manual and page ops; never block the user.
        $refundedImmediately = false;
        $stripeRefundId = null;
        try {
            if ($priorStatus === CardWaitlistDeposit::STATUS_PENDING_PAYMENT) {
                // Checkout session never completed — expire it so no charge fires.
                $sessionId = $deposit->stripe_checkout_session_id;
                if (is_string($sessionId) && $sessionId !== '') {
                    $this->stripe()->checkout->sessions->expire($sessionId);
                }
                $refundedImmediately = true;
            } else {
                // priorStatus === STATUS_PAID — request a refund on the PI.
                $paymentIntentId = $deposit->stripe_payment_intent_id;
                if (! is_string($paymentIntentId) || $paymentIntentId === '') {
                    throw new RuntimeException('paid deposit has no stripe_payment_intent_id');
                }
                $refund = $this->stripe()->refunds->create([
                    'payment_intent' => $paymentIntentId,
                    'metadata'       => [
                        'flow'      => self::CHECKOUT_FLOW,
                        'depositId' => (string) $deposit->id,
                        'reason'    => CardWaitlistDeposit::REFUNDED_REASON_USER_CANCELLED,
                    ],
                ]);
                $stripeRefundId = is_string($refund->id) ? $refund->id : null;
            }
        } catch (Throwable $e) {
            Log::warning('cards.deposit.cancel.stripe_failed', [
                'user_id'      => $user->id,
                'deposit_id'   => $deposit->id,
                'prior_status' => $priorStatus,
                'error'        => $e->getMessage(),
            ]);

            // Land in refund_pending_manual so ops can replay. We do NOT
            // surface this as a user error per Q9.4.
            $deposit->update([
                'status' => CardWaitlistDeposit::STATUS_REFUND_PENDING_MANUAL,
            ]);

            return response()->json([
                'depositId'               => (string) $deposit->id,
                'status'                  => CardWaitlistDeposit::STATUS_REFUND_PENDING_MANUAL,
                'refundedAt'              => null,
                'estimatedSettlementDays' => (int) config('cards.refund_estimated_settlement_days', 10),
                'message'                 => 'Refund is being processed manually. Our team has been notified.',
            ]);
        }

        if ($refundedImmediately) {
            // pending_payment → expired/refunded path: the Checkout session was
            // expired with no charge captured. Mark refunded for clarity to the
            // user, even though no money moved. Per spec §7.5 alt response.
            $deposit->update([
                'status'          => CardWaitlistDeposit::STATUS_REFUNDED,
                'refunded_at'     => now(),
                'refunded_reason' => CardWaitlistDeposit::REFUNDED_REASON_USER_CANCELLED,
            ]);

            return response()->json([
                'depositId'               => (string) $deposit->id,
                'status'                  => CardWaitlistDeposit::STATUS_REFUNDED,
                'refundedAt'              => $deposit->refunded_at?->toIso8601String(),
                'estimatedSettlementDays' => 0,
                'message'                 => 'Your Checkout session has been cancelled. No charge was made.',
            ]);
        }

        // Paid path: stay in cancellation_requested. The charge.refunded
        // webhook will transition to STATUS_REFUNDED.
        if ($stripeRefundId !== null) {
            $deposit->update(['stripe_refund_id' => $stripeRefundId]);
        }

        return response()->json([
            'depositId'               => (string) $deposit->id,
            'status'                  => CardWaitlistDeposit::STATUS_CANCELLATION_REQUESTED,
            'refundedAt'              => null,
            'estimatedSettlementDays' => (int) config('cards.refund_estimated_settlement_days', 10),
            'message'                 => 'Refund initiated. Funds will return to your card in 5–10 business days.',
        ]);
    }

    /**
     * GET /api/v1/cards/waitlist/entry — holistic waitlist state per §5.3.
     *
     * @return array{
     *     tier: string,
     *     depositStatus: string|null,
     *     depositAmount: array<string, mixed>|null,
     *     queuePosition: int|null,
     *     joinedAt: string|null,
     *     refundEligibleAfter: string|null,
     *     refundedReason: string|null,
     *     refundedAt: string|null,
     *     quoteId: string|null
     * }
     */
    public function entryFor(User $user): array
    {
        $waitlist = CardWaitlist::query()->where('user_id', $user->id)->first();

        if (! $waitlist instanceof CardWaitlist) {
            return [
                'tier'                => 'none',
                'depositStatus'       => null,
                'depositAmount'       => null,
                'queuePosition'       => null,
                'joinedAt'            => null,
                'refundEligibleAfter' => null,
                'refundedReason'      => null,
                'refundedAt'          => null,
                'quoteId'             => null,
            ];
        }

        // Most recent deposit row for this user (any status).
        /** @var CardWaitlistDeposit|null $deposit */
        $deposit = CardWaitlistDeposit::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->first();

        $tier = 'free';
        $depositStatus = 'none';
        $depositAmount = null;
        $refundEligibleAfter = null;
        $refundedReason = null;
        $refundedAt = null;
        $quoteId = null;

        if ($deposit instanceof CardWaitlistDeposit) {
            $depositStatus = (string) $deposit->status;
            $depositAmount = Money::fiat(
                (string) $deposit->deposit_amount_cents,
                (int) $deposit->deposit_decimals,
                (string) $deposit->deposit_currency,
            )->jsonSerialize();
            $refundEligibleAfter = $deposit->refund_eligible_after?->toIso8601String();
            $refundedReason = $deposit->refunded_reason;
            $refundedAt = $deposit->refunded_at?->toIso8601String();

            // Tier: 'deposit' while live (pending_payment or paid). After a
            // refund / expiry / cancellation the user reverts to 'free' tier
            // (still on the waitlist, no priority).
            if (
                in_array($deposit->status, [
                CardWaitlistDeposit::STATUS_PENDING_PAYMENT,
                CardWaitlistDeposit::STATUS_PAID,
                CardWaitlistDeposit::STATUS_CANCELLATION_REQUESTED,
                CardWaitlistDeposit::STATUS_CARD_SHIPPED,
                ], true)
            ) {
                $tier = 'deposit';
            }

            // quoteId surfaces only while the deposit is pending and the
            // underlying quote row exists. Once paid / refunded the quote ref
            // is internal.
            if ($deposit->status === CardWaitlistDeposit::STATUS_PENDING_PAYMENT) {
                $quoteId = (string) $deposit->quote_id;
            }
        }

        $queuePosition = $this->computeQueuePosition($user, $waitlist);

        return [
            'tier'                => $tier,
            'depositStatus'       => $depositStatus,
            'depositAmount'       => $depositAmount,
            'queuePosition'       => $queuePosition,
            'joinedAt'            => $waitlist->joined_at->toIso8601String(),
            'refundEligibleAfter' => $refundEligibleAfter,
            'refundedReason'      => $refundedReason,
            'refundedAt'          => $refundedAt,
            'quoteId'             => $quoteId,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    // Webhook helpers (called from CardWaitlistWebhookController inside the
    // dedup DB::transaction)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * checkout.session.completed + payment_status=paid.
     * Writes the outbox row in the SAME caller transaction. Idempotent on
     * (source_type, source_event_id) via the unique index.
     *
     * @param  array<string, mixed>  $session  Stripe checkout.session.completed.data.object
     */
    public function applyCheckoutCompleted(array $session, string $eventId): void
    {
        $sessionId = (string) ($session['id'] ?? '');
        $paymentStatus = (string) ($session['payment_status'] ?? '');
        if ($sessionId === '' || $paymentStatus !== 'paid') {
            Log::info('cards.deposit.webhook.checkout_completed.skipped', [
                'event_id'       => $eventId,
                'session_id'     => $sessionId,
                'payment_status' => $paymentStatus,
            ]);

            return;
        }

        $paymentIntentId = (string) ($session['payment_intent'] ?? '');
        $months = (int) config('cards.refund_eligible_after_months', 18);

        $affected = CardWaitlistDeposit::query()
            ->where('stripe_checkout_session_id', $sessionId)
            ->where('status', CardWaitlistDeposit::STATUS_PENDING_PAYMENT)
            ->update([
                'status'                   => CardWaitlistDeposit::STATUS_PAID,
                'paid_at'                  => now(),
                'stripe_payment_intent_id' => $paymentIntentId !== '' ? $paymentIntentId : null,
                'refund_eligible_after'    => now()->addMonths($months),
            ]);

        if ($affected === 0) {
            // Either this is a webhook retry (processed_webhook_events would
            // have caught it, so this branch only fires when the cron-replay
            // races a webhook that already landed) or the deposit moved on
            // (refunded, etc.) before this event arrived. Either way, the
            // outbox row was already written by the first caller — adding a
            // second row keyed by a different event_id would double-count the
            // €5 deposit in revenue_events.
            Log::info('cards.deposit.webhook.checkout_completed.no_pending_row', [
                'event_id'   => $eventId,
                'session_id' => $sessionId,
            ]);

            return;
        }

        $this->enqueueOutbox($eventId, 'checkout.session.completed', [
            'sessionId'     => $sessionId,
            'paymentIntent' => $paymentIntentId,
            'amount'        => (int) ($session['amount_total'] ?? 0),
            'decimals'      => 2,
            'denomination'  => strtoupper((string) ($session['currency'] ?? 'eur')),
            'emittedAt'     => now()->toIso8601String(),
            'rawType'       => 'checkout.session.completed',
        ]);
    }

    /**
     * checkout.session.expired — Checkout TTL hit with no payment captured.
     *
     * @param  array<string, mixed>  $session
     */
    public function applyCheckoutExpired(array $session, string $eventId): void
    {
        $sessionId = (string) ($session['id'] ?? '');
        if ($sessionId === '') {
            return;
        }

        CardWaitlistDeposit::query()
            ->where('stripe_checkout_session_id', $sessionId)
            ->where('status', CardWaitlistDeposit::STATUS_PENDING_PAYMENT)
            ->update([
                'status'     => CardWaitlistDeposit::STATUS_EXPIRED,
                'expired_at' => now(),
            ]);

        Log::info('cards.deposit.webhook.checkout_expired', [
            'event_id'   => $eventId,
            'session_id' => $sessionId,
        ]);
    }

    /**
     * charge.refunded — confirms a refund (the cancel handler initiated; or
     * an admin / 18-month auto refund). Writes a negative-amount outbox row
     * per ADR-0004 sign-prefix.
     *
     * @param  array<string, mixed>  $charge
     */
    public function applyChargeRefunded(array $charge, string $eventId): void
    {
        $paymentIntentId = (string) ($charge['payment_intent'] ?? '');
        if ($paymentIntentId === '') {
            return;
        }

        $refundId = $this->extractRefundIdFromCharge($charge);
        $refundedAmount = (int) ($charge['amount_refunded'] ?? 0);

        $affected = CardWaitlistDeposit::query()
            ->where('stripe_payment_intent_id', $paymentIntentId)
            ->whereIn('status', [
                CardWaitlistDeposit::STATUS_PAID,
                CardWaitlistDeposit::STATUS_CANCELLATION_REQUESTED,
                CardWaitlistDeposit::STATUS_REFUND_PENDING_MANUAL,
            ])
            ->update([
                'status'           => CardWaitlistDeposit::STATUS_REFUNDED,
                'refunded_at'      => now(),
                'refunded_reason'  => CardWaitlistDeposit::REFUNDED_REASON_USER_CANCELLED,
                'stripe_refund_id' => $refundId,
            ]);

        if ($affected === 0) {
            Log::info('cards.deposit.webhook.charge_refunded.no_match', [
                'event_id'       => $eventId,
                'payment_intent' => $paymentIntentId,
            ]);
        }

        $this->enqueueOutbox($eventId, 'charge.refunded', [
            'paymentIntent' => $paymentIntentId,
            'refundId'      => $refundId,
            // Negative-prefix per ADR-0004 — refunds reduce revenue.
            'amount'       => -1 * $refundedAmount,
            'decimals'     => 2,
            'denomination' => strtoupper((string) ($charge['currency'] ?? 'eur')),
            'emittedAt'    => now()->toIso8601String(),
            'rawType'      => 'charge.refunded',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ──────────────────────────────────────────────────────────────────────

    private function resolveReturnUrl(?string $candidate): ?string
    {
        /** @var array<int, string> $allowList */
        $allowList = (array) config('cards.allowed_return_urls', []);

        if ($candidate === null || $candidate === '') {
            return $allowList[0] ?? null;
        }

        return in_array($candidate, $allowList, true) ? $candidate : null;
    }

    /**
     * Extract the deposit Money triple from the quote's response_payload
     * feeBreakdown. The 'deposit' label is the only entry for the
     * card_waitlist_deposit kind (see PriceQuoteIssuer::buildDepositFeeBreakdown).
     *
     * @param  array<string, mixed>  $responsePayload
     */
    private function extractDepositMoney(array $responsePayload): ?Money
    {
        /** @var array<int, array<string, mixed>> $feeBreakdown */
        $feeBreakdown = (array) ($responsePayload['feeBreakdown'] ?? []);

        foreach ($feeBreakdown as $item) {
            $label = (string) ($item['label'] ?? '');
            if ($label !== 'deposit') {
                continue;
            }
            /** @var array<string, mixed> $amountShape */
            $amountShape = (array) ($item['amount'] ?? []);
            $amount = isset($amountShape['amount']) ? (string) $amountShape['amount'] : null;
            $decimals = isset($amountShape['decimals']) ? (int) $amountShape['decimals'] : null;
            $currency = isset($amountShape['currency']) ? (string) $amountShape['currency'] : null;
            if ($amount === null || $decimals === null || $currency === null) {
                return null;
            }

            return Money::fiat($amount, $decimals, $currency);
        }

        return null;
    }

    /**
     * Compute the user's live queue position via
     *   ROW_NUMBER() OVER (ORDER BY deposit_paid DESC, joined_at ASC).
     *
     * Implemented as two count queries rather than a window function so SQLite
     * (tests) can execute it. Reads card_waitlist + card_waitlist_deposits;
     * both are global-connection tables.
     */
    private function computeQueuePosition(User $user, CardWaitlist $userRow): int
    {
        // Has the user paid a deposit?
        $userPaid = CardWaitlistDeposit::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                CardWaitlistDeposit::STATUS_PAID,
                CardWaitlistDeposit::STATUS_CARD_SHIPPED,
            ])
            ->exists();

        // Build a set of user_ids who are "deposit-paid" (rank ahead of free-tier).
        /** @var array<int, int> $paidUserIds */
        $paidUserIds = CardWaitlistDeposit::query()
            ->whereIn('status', [
                CardWaitlistDeposit::STATUS_PAID,
                CardWaitlistDeposit::STATUS_CARD_SHIPPED,
            ])
            ->pluck('user_id')
            ->map(static fn ($v): int => (int) $v)
            ->all();

        if ($userPaid) {
            // Position among paid-tier: count of paid users whose card_waitlist
            // joined_at ≤ this user's joined_at.
            $aheadCount = CardWaitlist::query()
                ->whereIn('user_id', $paidUserIds)
                ->where('joined_at', '<', $userRow->joined_at)
                ->count();

            return $aheadCount + 1;
        }

        // Free-tier: positioned after all paid users.
        $paidCount = count($paidUserIds);
        $freeAheadCount = CardWaitlist::query()
            ->whereNotIn('user_id', $paidUserIds === [] ? [0] : $paidUserIds)
            ->where('joined_at', '<', $userRow->joined_at)
            ->count();

        return $paidCount + $freeAheadCount + 1;
    }

    /**
     * Write a subscription_consent_log row when the client provided
     * withdrawalConsent. Mirrors the pattern used in slice 1 but stores
     * 'card_waitlist_deposit' as the consent's logical context (no schema
     * change — recorded in the consent_text snapshot).
     *
     * @param  array<string, mixed>  $consentPayload
     */
    private function writeConsentLog(User $user, array $consentPayload, ?string $remoteIp): ?int
    {
        $consentText = is_string($consentPayload['consentText'] ?? null)
            ? (string) $consentPayload['consentText']
            : '';
        if ($consentText === '') {
            return null;
        }

        $acceptedAt = is_string($consentPayload['consentedAt'] ?? null)
            ? (string) $consentPayload['consentedAt']
            : now()->toIso8601String();

        $versionRaw = $consentPayload['version'] ?? '1';
        $version = is_numeric($versionRaw) ? (int) $versionRaw : 1;

        $ipHash = hash_hmac(
            'sha256',
            $remoteIp !== null && $remoteIp !== '' ? $remoteIp : '0.0.0.0',
            (string) config('app.key'),
        );

        try {
            $row = SubscriptionConsentLog::query()->create([
                'user_id'         => $user->id,
                'subscription_id' => null,
                'consent_text'    => $consentText,
                'consent_version' => $version,
                'shown_at'        => $acceptedAt,
                'accepted_at'     => $acceptedAt,
                'ip_hash'         => $ipHash,
                'user_agent'      => null,
            ]);

            return (int) $row->id;
        } catch (Throwable $e) {
            Log::warning('cards.deposit.consent_log_failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Compensation for failed deposit creation: clear the price_quote's
     * consumed_at so the user can retry with the same quote (if still in TTL)
     * or — more commonly — issue a fresh quote.
     */
    private function unredeemQuote(string $quoteId): void
    {
        try {
            DB::transaction(function () use ($quoteId): void {
                /** @var PriceQuote|null $quote */
                $quote = PriceQuote::query()
                    ->where('id', $quoteId)
                    ->lockForUpdate()
                    ->first();
                if ($quote === null) {
                    return;
                }
                $quote->update([
                    'consumed_at' => null,
                    'consumed_by' => null,
                ]);
            });
        } catch (Throwable $e) {
            Log::error('cards.deposit.unredeem_quote_failed', [
                'quote_id' => $quoteId,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    private function expireStripeSessionSafely(string $sessionId): void
    {
        try {
            $this->stripe()->checkout->sessions->expire($sessionId);
        } catch (Throwable $e) {
            Log::warning('cards.deposit.expire_session_failed', [
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Inspect Stripe charge.refunded data for the latest refund id. Stripe's
     * shape exposes refunds via `refunds.data` (newest-first) or — for older
     * single-refund charges — directly on the `refund` field.
     *
     * @param  array<string, mixed>  $charge
     */
    private function extractRefundIdFromCharge(array $charge): ?string
    {
        if (isset($charge['refunds']) && is_array($charge['refunds'])) {
            /** @var array<int, array<string, mixed>> $data */
            $data = (array) ($charge['refunds']['data'] ?? []);
            foreach ($data as $entry) {
                $id = $entry['id'] ?? null;
                if (is_string($id) && $id !== '') {
                    return $id;
                }
            }
        }

        $refund = $charge['refund'] ?? null;
        if (is_string($refund) && $refund !== '') {
            return $refund;
        }

        return null;
    }

    /**
     * INSERT INTO revenue_outbox_events idempotent on (source_type, source_event_id).
     *
     * MUST be called inside the same DB::transaction() that wrote the
     * processed_webhook_events dedup row — so a redelivery does not
     * double-fire.
     *
     * @param  array<string, mixed>  $payload
     */
    private function enqueueOutbox(string $eventId, string $eventKind, array $payload): void
    {
        RevenueOutboxEvent::query()->updateOrCreate(
            [
                'source_type'     => self::OUTBOX_SOURCE,
                'source_event_id' => $eventId,
            ],
            [
                'event_kind' => $eventKind,
                'payload'    => $payload,
                'status'     => RevenueOutboxEvent::STATUS_PENDING,
            ],
        );
    }
}
