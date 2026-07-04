<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hard-disables the CUSTODIAL RAILGUN privacy endpoints in production.
 *
 * The custodial shield/unshield/transfer/viewing-key path derives a user's
 * privacy spending seed server-side from app.key — textbook custody, and the
 * opposite of the non-custodial product model. Privacy moves on-device (the
 * RAILGUN engine + native prover, bootstrapped via GET /privacy/engine-config).
 * These endpoints must never run in production, regardless of ZK_PROVIDER, so
 * this returns 501. Non-production keeps them reachable so tests/local can
 * exercise the inert demo path.
 *
 * Enforcement is at runtime (not just ops:verify-env) because production deploys
 * here are a manual `git pull` and the env gate is not guaranteed to run.
 *
 * See docs/superpowers/specs/2026-07-04-wave-0-production-readiness-blockers-design.md (0B).
 */
class RejectCustodialPrivacyInProduction
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production')) {
            return response()->json([
                'success' => false,
                'error'   => 'CUSTODIAL_PRIVACY_DISABLED',
                'message' => 'Custodial privacy operations are disabled. Privacy is non-custodial and moves on-device via the RAILGUN engine (GET /api/v1/privacy/engine-config).',
            ], Response::HTTP_NOT_IMPLEMENTED);
        }

        return $next($request);
    }
}
