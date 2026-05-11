<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Http\Controllers;

use App\Domain\Pricing\Exceptions\FeeResolverException;
use App\Domain\Pricing\Exceptions\QuoteRedemptionException;
use App\Domain\Pricing\Http\Requests\CreateQuoteRequest;
use App\Domain\Pricing\Services\QuoteService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * PricingController — Plan B Slice 3 endpoints.
 *
 * POST  /api/v1/pricing/quote              — issue a price quote (idempotency.required)
 * GET   /api/v1/pricing/quote/{quoteId}    — read back a quote (auth:sanctum, read ability)
 *
 * Rate limiting:
 *   POST applies per-user (60/min) and per-IP (600/min) Redis sliding window.
 *   Uses Cache::add() + Cache::increment() per CLAUDE.md — never read-then-write.
 *
 * Currency gate:
 *   POST validates currency == "EUR" before hitting the service (ERR_CUR_001).
 *
 * Dry-run:
 *   POST ?dryRun=true assembles without persisting and returns quoteId: null.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-3-pricing-design.md §5.1, §5.2
 */
final class PricingController extends Controller
{
    public function __construct(
        private readonly QuoteService $quoteService,
    ) {
    }

    /**
     * POST /api/v1/pricing/quote.
     *
     * Issues a new price quote (or replays a live deduplicated one via entity-key).
     * Applies the idempotency.required middleware at the route level (OD-1).
     */
    public function quote(CreateQuoteRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['error' => ['code' => 'UNAUTHENTICATED']], 401);
        }

        // Rate limiting (60/min/user, 600/min/IP) using Redis sliding window.
        // Per CLAUDE.md: Cache::add() + Cache::increment() — never read-then-write.
        $dryRun = $request->isDryRun();

        if (! $dryRun) {
            $rateLimitResponse = $this->checkRateLimit($user, $request);
            if ($rateLimitResponse !== null) {
                return $rateLimitResponse;
            }
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        // Currency gate: v1.3.0 is EUR-only.
        if (($validated['currency'] ?? '') !== 'EUR') {
            return ErrorResponse::make('ERR_CUR_001');
        }

        try {
            $quote = $this->quoteService->create($user, $validated, $dryRun);
        } catch (FeeResolverException) {
            return ErrorResponse::make('ERR_FEE_001');
        }

        $responseBody = $quote->jsonSerialize();

        // Dry-run override: quoteId and expiresAt are null per spec §5.3.
        if ($dryRun) {
            $responseBody['quoteId'] = null;
            $responseBody['expiresAt'] = null;
        }

        return response()->json($responseBody);
    }

    /**
     * GET /api/v1/pricing/quote/{quoteId}.
     *
     * Returns a quote by ID for the authenticated user. Per spec §5.2 (OD-3):
     * only the originating user can read their own quote (ERR_QUO_001 otherwise).
     */
    public function show(Request $request, string $quoteId): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['error' => ['code' => 'UNAUTHENTICATED']], 401);
        }

        try {
            $quote = $this->quoteService->retrieve($quoteId, $user);
        } catch (QuoteRedemptionException $e) {
            return ErrorResponse::make($e->errorCode);
        }

        return response()->json($quote->jsonSerialize());
    }

    // ──────────────────────────────────────────────────────────────────────
    // Rate limiting
    // ──────────────────────────────────────────────────────────────────────

    private function checkRateLimit(User $user, Request $request): ?JsonResponse
    {
        /** @var array<string, int> $rateLimit */
        $rateLimit = config('pricing.rate_limit', []);
        $perUserLimit = (int) ($rateLimit['quotes_per_minute_per_user'] ?? 60);
        $perIpLimit = (int) ($rateLimit['quotes_per_minute_per_ip'] ?? 600);
        $ttl = 60; // 1 minute window.

        // Per-user rate limit.
        $userKey = 'quote_rate:user:' . $user->id;
        Cache::add($userKey, 0, $ttl);
        $userCount = Cache::increment($userKey);

        if ($userCount > $perUserLimit) {
            return ErrorResponse::make('ERR_QUO_005')
                ->withHeaders(['Retry-After' => '60']);
        }

        // Per-IP rate limit.
        $ip = (string) ($request->ip() ?? '0.0.0.0');
        $ipKey = 'quote_rate:ip:' . hash('sha256', $ip);
        Cache::add($ipKey, 0, $ttl);
        $ipCount = Cache::increment($ipKey);

        if ($ipCount > $perIpLimit) {
            return ErrorResponse::make('ERR_QUO_005')
                ->withHeaders(['Retry-After' => '60']);
        }

        return null;
    }
}
