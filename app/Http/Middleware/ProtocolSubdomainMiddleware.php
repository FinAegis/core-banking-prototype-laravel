<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Detects x402 or MPP protocol-specific subdomains and automatically
 * applies the corresponding payment gate middleware.
 *
 * Supports subdomains like:
 * - x402.api.zelta.app  → applies X402PaymentGateMiddleware
 * - mpp.api.zelta.app   → applies MppPaymentGateMiddleware
 * - x402.zelta.app      → same (api. prefix optional)
 * - mpp.zelta.app       → same
 */
class ProtocolSubdomainMiddleware
{
    public function __construct(
        private readonly X402PaymentGateMiddleware $x402Gate,
        private readonly MppPaymentGateMiddleware $mppGate,
    ) {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $x402Prefix = (string) config('x402.subdomain', 'x402');
        $mppPrefix = (string) config('machinepay.subdomain', 'mpp');

        if ($this->matchesProtocolSubdomain($host, $x402Prefix)) {
            $request->attributes->set('payment_protocol', 'x402');

            return $this->x402Gate->handle($request, $next);
        }

        if ($this->matchesProtocolSubdomain($host, $mppPrefix)) {
            $request->attributes->set('payment_protocol', 'mpp');

            return $this->mppGate->handle($request, $next);
        }

        return $next($request);
    }

    /**
     * Check if the host matches a protocol subdomain pattern.
     *
     * Matches "proto.api.domain.com" and "proto.domain.com" but excludes
     * the plain "api." subdomain to avoid false positives.
     */
    private function matchesProtocolSubdomain(string $host, string $prefix): bool
    {
        return str_starts_with($host, "{$prefix}.api.")
            || (str_starts_with($host, "{$prefix}.") && ! str_starts_with($host, 'api.'));
    }
}
