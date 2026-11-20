<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\MachinePay\Services\MppPricingService;
use App\Domain\SMS\Services\SmsSendBudget;
use App\Support\ErrorResponse;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Access rules for POST /api/v1/sms/send.
 *
 * - SMS_ENABLED (`sms.enabled`) is the master switch: 503 while off, before
 *   any other check.
 * - Paid path: when the MPP payment gate is on (`machinepay.enabled`) and
 *   the route is an active MPP monetized resource, a request carrying an
 *   `Authorization: Payment …` credential goes through
 *   {@see MppPaymentGateMiddleware}; the settled payment is its own
 *   authorization. A request with neither a credential nor a valid Sanctum
 *   token gets the MPP 402 challenge.
 * - A request already paid through the gate of an x402.* / mpp.* protocol
 *   subdomain (request attribute `x402_payment` / `mpp_payment`) is let
 *   through like any other paid request.
 * - A payment counts only when the settled amount is at least the minimum
 *   paid price (`sms.min_paid_price_usdc`, SMS_MIN_PAID_PRICE_USDC, atomic
 *   USDC). A smaller payment gets the unpaid rules below; an MPP resource
 *   priced below the minimum is not offered (no payment is taken).
 * - A paid send is still bounded: a daily ceiling of billed parts per user,
 *   or per payer (x402 payer address, otherwise client IP) when the paid
 *   request has no Sanctum user (SMS_PAID_SEND_PER_USER_PER_DAY; 429
 *   ERR_SMS_001). An MPP request at the ceiling is refused before its
 *   payment is settled.
 * - Unpaid path (MPP off, a valid Sanctum bearer token, or a payment below
 *   the minimum paid price): Sanctum authentication (401 without it), the
 *   per-user `sms-send` rate limiter and the daily unpaid-send limits shared
 *   with the MCP sms.send tool ({@see SmsSendBudget}: per user and all
 *   users together, SMS_SEND_PER_USER_PER_DAY / SMS_SEND_GLOBAL_PER_DAY,
 *   counted in billed SMS parts; 429 ERR_SMS_001 once reached).
 * Only sends that succeed are counted.
 */
class SmsSendGateMiddleware
{
    /**
     * Atomic USDC units per US cent (USDC has 6 decimals).
     */
    private const ATOMIC_USDC_PER_CENT = '10000';

    /** Same rule as the SmsController `message` validation (max:1600). */
    private const MAX_MESSAGE_LENGTH = 1600;

    public function __construct(
        private readonly Container $container,
        private readonly MppPricingService $mppPricing,
        private readonly SmsSendBudget $budget,
    ) {
    }

    /**
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('sms.enabled', false)) {
            return response()->json(['error' => 'SMS service is not enabled'], 503);
        }

        // Already settled by the payment gate of an x402.* / mpp.* protocol
        // subdomain (ProtocolSubdomainMiddleware). The payment is never
        // settled a second time here: below the minimum paid price the
        // unpaid rules apply.
        if ($request->attributes->has('mpp_payment') || $request->attributes->has('x402_payment')) {
            return $this->meetsMinimumPaidPrice($request)
                ? $this->withinPaidCeiling($request, $next)
                : $this->unpaid($request, $next);
        }

        if ($this->mppGateApplies($request)) {
            $hasPaymentCredential = str_starts_with((string) $request->header('Authorization', ''), 'Payment ');

            // A payer already at today's paid ceiling is refused before the
            // payment is settled, so it is not charged for a refused send.
            if ($hasPaymentCredential && $this->budget->paidCeilingReached($this->payerKey($request), $this->billedParts($request))) {
                return $this->limitReached();
            }

            if ($hasPaymentCredential || Auth::guard('sanctum')->user() === null) {
                return $this->paid($request, $next);
            }
        }

        return $this->unpaid($request, $next);
    }

    /**
     * Cache key of today's (UTC) count of unpaid sends, all users together.
     */
    public static function globalUnpaidCountCacheKey(): string
    {
        return SmsSendBudget::globalCountCacheKey();
    }

    private function mppGateApplies(Request $request): bool
    {
        if (! (bool) config('machinepay.enabled', false)) {
            return false;
        }

        // Without a monetized resource the MPP gate would let the request
        // through unpaid, so the unpaid (authenticated) path applies. A
        // resource priced below the minimum paid price is not offered, so no
        // payment is taken for a send the unpaid rules would refuse.
        $routeConfig = $this->mppPricing->getRouteConfig($request);

        if ($routeConfig === null) {
            return false;
        }

        if ($this->meetsMinimumPrice($this->mppAtomicUsdc($routeConfig->amountCents, $routeConfig->currency))) {
            return true;
        }

        Log::warning('sms.send MPP resource priced below the minimum paid price; unpaid rules apply', [
            'amount_cents' => $routeConfig->amountCents,
            'currency'     => $routeConfig->currency,
        ]);

        return false;
    }

    /**
     * @param Closure(Request): (Response) $next
     */
    private function paid(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = (new Pipeline($this->container))
            ->send($request)
            ->through([MppPaymentGateMiddleware::class])
            ->then(fn (Request $request): Response => $this->meetsMinimumPaidPrice($request)
                ? $this->withinPaidCeiling($request, $next)
                : $this->unpaid($request, $next));

        return $response;
    }

    /**
     * Reserve the message's billed parts against the payer's daily paid
     * ceiling, and release them again unless the send succeeded.
     *
     * @param Closure(Request): (Response) $next
     */
    private function withinPaidCeiling(Request $request, Closure $next): Response
    {
        $payer = $this->payerKey($request);
        $parts = $this->billedParts($request);
        $day = SmsSendBudget::today();

        if (! $this->budget->reservePaid($payer, $parts, $day)) {
            return $this->limitReached();
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->budget->releasePaid($payer, $parts, $day);

            throw $e;
        }

        if (! $response->isSuccessful()) {
            $this->budget->releasePaid($payer, $parts, $day);
        }

        return $response;
    }

    /**
     * Who a paid send is counted against: the Sanctum user, else the payer
     * address the x402 facilitator settled for, else the client IP (an MPP
     * credential names no verified payer).
     */
    private function payerKey(Request $request): string
    {
        $user = Auth::guard('sanctum')->user();

        if ($user !== null) {
            $id = $user->getAuthIdentifier();

            return 'user:' . (is_int($id) || is_string($id) ? $id : (string) json_encode($id));
        }

        $x402 = $request->attributes->get('x402_payment');

        if (is_array($x402) && is_string($x402['payer'] ?? null) && $x402['payer'] !== '') {
            return 'payer:' . strtolower($x402['payer']);
        }

        return 'ip:' . (string) $request->ip();
    }

    /**
     * Billed SMS parts of the request's message (1 when it is missing or
     * longer than validation allows; the send is then refused anyway).
     */
    private function billedParts(Request $request): int
    {
        $message = $request->input('message');

        if (! is_string($message) || $message === '' || mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            return 1;
        }

        return SmsSendBudget::billedParts($message);
    }

    private function limitReached(): Response
    {
        return ErrorResponse::make('ERR_SMS_001')
            ->withHeaders(['Retry-After' => (string) SmsSendBudget::secondsUntilReset()]);
    }

    /**
     * @param Closure(Request): (Response) $next
     */
    private function unpaid(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = (new Pipeline($this->container))
            ->send($request)
            ->through([
                Authenticate::class . ':sanctum',
                ThrottleRequests::class . ':sms-send',
            ])
            ->then(fn (Request $request): Response => $this->withinUnpaidBudget($request, $next));

        return $response;
    }

    /**
     * Reserve the message's billed parts from the daily unpaid-send limits,
     * and release them again unless the send succeeded (validation errors
     * and provider failures do not count).
     *
     * @param Closure(Request): (Response) $next
     */
    private function withinUnpaidBudget(Request $request, Closure $next): Response
    {
        // Authenticate:sanctum has run, so the sanctum guard holds the user.
        $user = Auth::guard('sanctum')->user();

        if ($user === null) {
            throw new AuthenticationException('Unauthenticated.', ['sanctum']);
        }

        $userId = $user->getAuthIdentifier();
        $userId = is_int($userId) || is_string($userId) ? $userId : (string) json_encode($userId);

        // Release against the day the unit was reserved from, even after
        // midnight.
        $day = SmsSendBudget::today();
        $parts = $this->billedParts($request);

        if ($this->budget->reserve($userId, 'rest', $day, $parts) !== null) {
            return $this->limitReached();
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->budget->release($userId, $day, $parts);

            throw $e;
        }

        if (! $response->isSuccessful()) {
            $this->budget->release($userId, $day, $parts);
        }

        return $response;
    }

    /**
     * Whether a settled payment on the request is at least the minimum paid
     * price.
     */
    private function meetsMinimumPaidPrice(Request $request): bool
    {
        $paidAtomic = $this->settledAmountAtomicUsdc($request);

        if ($this->meetsMinimumPrice($paidAtomic)) {
            return true;
        }

        Log::warning('sms.send payment below the minimum paid price; unpaid rules apply', [
            'paid_usdc'    => $paidAtomic,
            'minimum_usdc' => $this->minimumPaidPriceAtomicUsdc(),
            'protocol'     => $request->attributes->has('x402_payment') ? 'x402' : 'mpp',
        ]);

        return false;
    }

    /**
     * @param  numeric-string|null  $atomicUsdc
     */
    private function meetsMinimumPrice(?string $atomicUsdc): bool
    {
        return $atomicUsdc !== null && bccomp($atomicUsdc, $this->minimumPaidPriceAtomicUsdc(), 0) >= 0;
    }

    /**
     * @return numeric-string
     */
    private function minimumPaidPriceAtomicUsdc(): string
    {
        return (string) max(1, (int) config('sms.min_paid_price_usdc', 10000));
    }

    /**
     * Settled amount in atomic USDC, or null when the request carries no
     * payment in a currency that can be compared.
     *
     * @return numeric-string|null
     */
    private function settledAmountAtomicUsdc(Request $request): ?string
    {
        $x402 = $request->attributes->get('x402_payment');

        if (is_array($x402)) {
            // X402PaymentGateMiddleware records the route price in USD.
            $amount = $x402['amount'] ?? null;

            if (! is_string($amount) || ! is_numeric($amount) || preg_match('/^\d+(\.\d+)?$/', $amount) !== 1) {
                return null;
            }

            return bcmul($amount, '1000000', 0);
        }

        $mpp = $request->attributes->get('mpp_payment');

        if (! is_array($mpp) || ! is_int($mpp['amount_cents'] ?? null)) {
            return null;
        }

        return $this->mppAtomicUsdc($mpp['amount_cents'], (string) ($mpp['currency'] ?? ''));
    }

    /**
     * An MPP amount (smallest currency unit) in atomic USDC, or null for a
     * currency that cannot be compared.
     *
     * @return numeric-string|null
     */
    private function mppAtomicUsdc(int $amountCents, string $currency): ?string
    {
        if ($amountCents < 0) {
            return null;
        }

        $atomicUsd = bcmul((string) $amountCents, self::ATOMIC_USDC_PER_CENT, 0);

        return match (strtoupper($currency)) {
            'USD', 'USDC' => $atomicUsd,
            'EUR'         => bcmul($atomicUsd, number_format(max(0.5, (float) config('sms.pricing.eur_usd_rate', 1.08)), 6, '.', ''), 0),
            default       => null,
        };
    }
}
