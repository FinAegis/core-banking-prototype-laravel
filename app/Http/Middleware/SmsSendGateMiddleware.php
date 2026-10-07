<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\MachinePay\Services\MppPricingService;
use App\Support\ErrorResponse;
use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
 * - Unpaid path (MPP off, or a valid Sanctum bearer token): Sanctum
 *   authentication (401 without it), the per-user `sms-send` rate limiter
 *   and a global daily cap on unpaid sends (`sms.global_daily_limit`,
 *   SMS_SEND_GLOBAL_PER_DAY; 429 ERR_SMS_001 once reached). Only sends that
 *   succeed count against the cap.
 * - A request already paid through the gate of an x402.* / mpp.* protocol
 *   subdomain (request attribute `x402_payment` / `mpp_payment`) is let
 *   through like any other paid request.
 */
class SmsSendGateMiddleware
{
    public function __construct(
        private readonly Container $container,
        private readonly MppPricingService $mppPricing,
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
        // subdomain (ProtocolSubdomainMiddleware): the payment authorizes it.
        if ($request->attributes->has('mpp_payment') || $request->attributes->has('x402_payment')) {
            return $next($request);
        }

        if ($this->mppGateApplies($request)) {
            $hasPaymentCredential = str_starts_with((string) $request->header('Authorization', ''), 'Payment ');

            if ($hasPaymentCredential || Auth::guard('sanctum')->user() === null) {
                return $this->paid($request, $next);
            }
        }

        return $this->unpaid($request, $next);
    }

    /**
     * Cache key of today's (UTC) count of unpaid sends.
     */
    public static function globalUnpaidCountCacheKey(): string
    {
        return 'sms:send:unpaid:global:' . now()->utc()->format('Y-m-d');
    }

    private function mppGateApplies(Request $request): bool
    {
        if (! (bool) config('machinepay.enabled', false)) {
            return false;
        }

        // Without a monetized resource the MPP gate would let the request
        // through unpaid, so the unpaid (authenticated) path applies.
        return $this->mppPricing->getRouteConfig($request) !== null;
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
            ->then($next);

        return $response;
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
            ->then(fn (Request $request): Response => $this->withinGlobalDailyCap($request, $next));

        return $response;
    }

    /**
     * Reserve one unit of the global daily cap, and release it again unless
     * the send succeeded (validation errors and provider failures do not
     * count).
     *
     * @param Closure(Request): (Response) $next
     */
    private function withinGlobalDailyCap(Request $request, Closure $next): Response
    {
        $limit = max(0, (int) config('sms.global_daily_limit', 500));
        $key = self::globalUnpaidCountCacheKey();

        // Cache::add + increment — never read-then-write (concurrency-safe).
        Cache::add($key, 0, now()->addDays(2));
        $count = (int) Cache::increment($key);

        if ($count > $limit) {
            Cache::decrement($key);

            Log::warning('sms.send global daily cap reached', [
                'count' => $count - 1,
                'limit' => $limit,
            ]);

            $secondsUntilReset = max(1, (int) now()->utc()->diffInSeconds(now()->utc()->addDay()->startOfDay()));

            return ErrorResponse::make('ERR_SMS_001')
                ->withHeaders(['Retry-After' => (string) $secondsUntilReset]);
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            Cache::decrement($key);

            throw $e;
        }

        if (! $response->isSuccessful()) {
            Cache::decrement($key);
        }

        return $response;
    }
}
