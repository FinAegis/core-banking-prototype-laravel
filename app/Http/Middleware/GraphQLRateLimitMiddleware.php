<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\AccountProvisioning\Enums\BypassType;
use App\Domain\AccountProvisioning\Services\AccountFlagsService;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GraphQLRateLimitMiddleware
{
    /**
     * GraphQL-specific rate limits (separate from REST API).
     */
    private const GUEST_LIMIT = 30;

    private const AUTH_LIMIT = 120;

    private const DECAY_SECONDS = 60;

    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly AccountFlagsService $flags,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Short-circuit for review accounts with bypass_rate_limit=true.
        $user = $request->user();
        if ($user !== null && $this->flags->hasReviewBypass($user, BypassType::RATE_LIMIT)) {
            return $next($request);
        }

        $key = $this->resolveKey($request);
        $maxAttempts = $this->resolveMaxAttempts($request);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);

            return new JsonResponse([
                'errors' => [
                    [
                        'message'    => 'GraphQL rate limit exceeded. Try again later.',
                        'extensions' => [
                            'category'    => 'rate_limit',
                            'retry_after' => $retryAfter,
                        ],
                    ],
                ],
            ], 429, [
                'Retry-After'           => (string) $retryAfter,
                'X-RateLimit-Limit'     => (string) $maxAttempts,
                'X-RateLimit-Remaining' => '0',
            ]);
        }

        $this->limiter->hit($key, self::DECAY_SECONDS);

        $response = $next($request);

        if ($response instanceof \Illuminate\Http\Response || $response instanceof JsonResponse) {
            $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
            $response->headers->set('X-RateLimit-Remaining', (string) $this->limiter->remaining($key, $maxAttempts));
        }

        return $response;
    }

    private function resolveKey(Request $request): string
    {
        $user = $request->user();
        $identifier = $user !== null ? $user->id : $request->ip();

        return "graphql_rate_limit:{$identifier}";
    }

    private function resolveMaxAttempts(Request $request): int
    {
        if (! $request->user()) {
            return (int) config('lighthouse.rate_limiting.guest_limit', self::GUEST_LIMIT);
        }

        return (int) config('lighthouse.rate_limiting.auth_limit', self::AUTH_LIMIT);
    }
}
