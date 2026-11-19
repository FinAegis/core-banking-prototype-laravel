<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Asset\Models\Asset;
use App\Domain\CardIssuance\Contracts\CardIssuerInterface;
use App\Domain\CardIssuance\Enums\AuthorizationDecision;
use App\Domain\CardIssuance\Events\AuthorizationApproved;
use App\Domain\CardIssuance\Events\AuthorizationDeclined;
use App\Domain\CardIssuance\ValueObjects\AuthorizationRequest;
use App\Infrastructure\Monitoring\MetricsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Throwable;

/**
 * Just-in-Time Funding Service for real-time card authorization.
 *
 * This service handles incoming authorization requests from the card issuer
 * and decides whether to approve based on the user's stablecoin balance.
 *
 * Demo mode applies only when the configured issuer
 * (`cardissuance.default_issuer`, env CARD_ISSUER) is `demo`. Under
 * APP_ENV=production the demo issuer declines every authorization and never
 * issues a hold; demo approvals remain in local, testing and demo. For any
 * real issuer the decision uses the cardholder's ledger balance for the
 * funding token, minus the funds already held by earlier approvals, and
 * fails closed: a missing account, asset, balance or hold store declines the
 * authorization.
 *
 * CRITICAL: Latency budget is < 2000ms for the entire authorization flow.
 */
class JitFundingService
{
    /**
     * Default stablecoin to use for funding.
     */
    private const DEFAULT_FUNDING_TOKEN = 'USDC';

    /**
     * Balance assumed for every cardholder in demo mode.
     */
    private const DEMO_BALANCE = 1000.00;

    public function __construct(
        private readonly CardIssuerInterface $cardIssuer,
        private readonly SpendLimitEnforcementService $spendLimitService,
        private readonly MetricsService $metrics,
    ) {
    }

    /**
     * Process an authorization request.
     *
     * @return array{approved: bool, decision: AuthorizationDecision, hold_id: ?string}
     */
    public function authorize(AuthorizationRequest $request): array
    {
        $startTime = microtime(true);

        // The demo issuer has no funding source: in production it declines
        // everything, before any card lookup, limit check or hold.
        if ($this->isDemoMode() && app()->environment('production')) {
            Log::warning('JIT Funding: demo card issuer in production, declining', [
                'authorization_id' => $request->authorizationId,
            ]);

            return $this->decline($request, AuthorizationDecision::DECLINED_INSUFFICIENT_FUNDS);
        }

        // 0. Per-card rate limiting — prevent a compromised card flooding the authorization pipeline
        $rateLimitKey = 'jit_auth:' . $request->cardToken;
        $maxAttempts = (int) config('cardissuance.jit_funding.max_auth_per_minute', 10);

        if (! RateLimiter::attempt($rateLimitKey, $maxAttempts, fn () => true, 60)) {
            Log::warning('JIT auth rate limit exceeded', [
                'card_token_suffix' => substr($request->cardToken, -4),
            ]);

            return $this->decline($request, AuthorizationDecision::DECLINED_CARD_CANCELLED);
        }

        Log::info('JIT Funding: Processing authorization', [
            'authorization_id' => $request->authorizationId,
            'amount'           => $request->getAmountDecimal(),
            'currency'         => $request->currency,
            'merchant'         => $request->merchantName,
        ]);

        // 1. Get card and user
        $card = $this->cardIssuer->getCard($request->cardToken);
        if ($card === null) {
            return $this->decline($request, AuthorizationDecision::DECLINED_CARD_CANCELLED);
        }

        if (! $card->isUsable()) {
            $decision = $card->status->value === 'frozen'
                ? AuthorizationDecision::DECLINED_CARD_FROZEN
                : AuthorizationDecision::DECLINED_CARD_CANCELLED;

            return $this->decline($request, $decision);
        }

        // 2. Check balance + create hold atomically to prevent TOCTOU race condition.
        //    Without a transaction lock, two concurrent authorizations could both pass
        //    the balance check before either creates a hold, leading to double-spending.
        //    The transaction runs on the Account model's own (tenant) connection:
        //    a row lock taken on that connection inside a default-connection
        //    transaction would be released at once. No database row is written
        //    inside (holds and spend totals live in the cache).
        $userId = (string) ($card->metadata['user_id'] ?? '');
        $requiredAmount = (float) $request->getAmountDecimal();

        $declineReason = null;
        $holdId = '';

        DB::connection((new Account())->getConnectionName())->transaction(function () use ($request, $userId, $requiredAmount, &$declineReason, &$holdId): void {
            if ($this->isDemoMode()) {
                // Demo issuer: no real account rows, fixed balance.
                if (self::DEMO_BALANCE < $requiredAmount) {
                    $declineReason = AuthorizationDecision::DECLINED_INSUFFICIENT_FUNDS;

                    return;
                }

                if (! $this->spendLimitService->checkLimit($request->cardToken, $requiredAmount)) {
                    $declineReason = AuthorizationDecision::DECLINED_LIMIT_EXCEEDED;

                    return;
                }

                $this->spendLimitService->recordSpend($request->cardToken, $requiredAmount);
                $holdId = 'hold_' . bin2hex(random_bytes(16));

                return;
            }

            // Live issuer. Acquire a row lock on the account to serialize
            // concurrent authorizations for the same cardholder.
            $account = $userId === ''
                ? null
                : Account::where('user_uuid', $userId)->lockForUpdate()->first();

            if (! $account instanceof Account) {
                Log::warning('JIT Funding: no funding account for cardholder, declining', [
                    'authorization_id' => $request->authorizationId,
                ]);
                $declineReason = AuthorizationDecision::DECLINED_INSUFFICIENT_FUNDS;

                return;
            }

            $requiredMinor = $this->toTokenMinorUnits($request, self::DEFAULT_FUNDING_TOKEN);
            if ($requiredMinor === null) {
                $declineReason = AuthorizationDecision::DECLINED_INSUFFICIENT_FUNDS;

                return;
            }

            // Check spend limits inside the lock to prevent limit bypass
            if (! $this->spendLimitService->checkLimit($request->cardToken, $requiredAmount)) {
                $declineReason = AuthorizationDecision::DECLINED_LIMIT_EXCEEDED;

                return;
            }

            // Create the hold while the lock is held — guarantees no concurrent double-spend
            $hold = $this->placeHold(
                $request,
                $userId,
                self::DEFAULT_FUNDING_TOKEN,
                $account->getBalance(self::DEFAULT_FUNDING_TOKEN),
                $requiredMinor,
            );

            if ($hold === null) {
                $declineReason = AuthorizationDecision::DECLINED_INSUFFICIENT_FUNDS;

                return;
            }

            // Record the spend while the lock is still held, so a concurrent
            // authorization on the same card cannot pass checkLimit() against
            // the total from before this one.
            $this->spendLimitService->recordSpend($request->cardToken, $requiredAmount);

            $holdId = $hold;
        });

        if ($declineReason !== null) {
            return $this->decline($request, $declineReason);
        }

        // 4. Approve transaction
        $latencyMs = (microtime(true) - $startTime) * 1000;

        $this->metrics->timing('jit_funding_latency', $latencyMs);
        $this->metrics->increment('jit_funding_approved');

        Log::info('JIT Funding: Authorization approved', [
            'authorization_id' => $request->authorizationId,
            'hold_id'          => $holdId,
            'latency_ms'       => round($latencyMs, 2),
        ]);

        Event::dispatch(new AuthorizationApproved(
            authorizationId: $request->authorizationId,
            cardToken: $request->cardToken,
            amount: $requiredAmount,
            currency: $request->currency,
            holdId: $holdId,
            merchantName: $request->merchantName,
        ));

        return [
            'approved' => true,
            'decision' => AuthorizationDecision::APPROVED,
            'hold_id'  => $holdId,
        ];
    }

    /**
     * Decline an authorization request.
     *
     * @return array{approved: bool, decision: AuthorizationDecision, hold_id: null}
     */
    private function decline(
        AuthorizationRequest $request,
        AuthorizationDecision $decision
    ): array {
        $this->metrics->increment('jit_funding_declined', 1, ['reason' => $decision->value]);

        Log::warning('JIT Funding: Authorization declined', [
            'authorization_id' => $request->authorizationId,
            'reason'           => $decision->value,
        ]);

        Event::dispatch(new AuthorizationDeclined(
            authorizationId: $request->authorizationId,
            cardToken: $request->cardToken,
            amount: (float) $request->getAmountDecimal(),
            currency: $request->currency,
            reason: $decision,
            merchantName: $request->merchantName,
        ));

        return [
            'approved' => false,
            'decision' => $decision,
            'hold_id'  => null,
        ];
    }

    /**
     * Convert the authorization amount (cents) into minor units of the
     * funding token, using the token's precision from the assets table.
     * Returns null (decline) when the asset is not configured.
     */
    private function toTokenMinorUnits(AuthorizationRequest $request, string $token): ?int
    {
        try {
            $precision = Asset::where('code', $token)->value('precision');
        } catch (Throwable $e) {
            Log::error('JIT Funding: asset lookup failed, declining', [
                'authorization_id' => $request->authorizationId,
                'error'            => $e->getMessage(),
            ]);

            return null;
        }

        // Precision outside [2, 12] cannot be represented with the integer
        // balances used here; treat it as a configuration error.
        if ($precision === null || (int) $precision < 2 || (int) $precision > 12) {
            Log::error('JIT Funding: funding asset not configured, declining', [
                'authorization_id' => $request->authorizationId,
                'token'            => $token,
            ]);

            return null;
        }

        // amountCents has 2 decimals; scale to the token's precision.
        return $request->amountCents * (10 ** ((int) $precision - 2));
    }

    /**
     * Reserve funds for an approved authorization.
     *
     * Holds are tracked per cardholder and token as a running total in the
     * cache (`card_jit_holds:{user}:{token}`, expiring after
     * `cardissuance.jit_funding.hold_ttl_seconds`), so each approval reduces
     * what later authorizations can draw on. The total is updated atomically
     * (increment, then roll back if it exceeds the balance). Any cache
     * failure declines the authorization.
     *
     * @return string|null the hold id, or null to decline
     */
    private function placeHold(
        AuthorizationRequest $request,
        string $userId,
        string $token,
        int $balanceMinor,
        int $amountMinor,
    ): ?string {
        $key = "card_jit_holds:{$userId}:{$token}";
        $ttl = max(60, (int) config('cardissuance.jit_funding.hold_ttl_seconds', 604800));

        try {
            Cache::add($key, 0, $ttl);
            $held = Cache::increment($key, $amountMinor);

            if ($held === false) {
                throw new RuntimeException('Hold store did not return a running total.');
            }

            if ((int) $held > $balanceMinor) {
                Cache::decrement($key, $amountMinor);

                Log::info('JIT Funding: insufficient available balance', [
                    'authorization_id' => $request->authorizationId,
                ]);

                return null;
            }
        } catch (Throwable $e) {
            Log::error('JIT Funding: hold store unavailable, declining', [
                'authorization_id' => $request->authorizationId,
                'error'            => $e->getMessage(),
            ]);

            return null;
        }

        $holdId = 'hold_' . bin2hex(random_bytes(16));

        Log::info('JIT Funding: Hold created', [
            'hold_id'      => $holdId,
            'user_id'      => $userId,
            'amount_minor' => $amountMinor,
            'token'        => $token,
        ]);

        return $holdId;
    }

    /**
     * Demo mode only when the configured card issuer is the demo issuer.
     */
    private function isDemoMode(): bool
    {
        return (string) config('cardissuance.default_issuer', 'demo') === 'demo';
    }
}
