<?php

/**
 * Route contract for @finaegis/cli (packages/zelta-cli).
 *
 * Every API path the CLI calls must keep resolving in the monorepo router,
 * otherwise the published CLI 404s against production (the v0.2.x drift).
 *
 * The CLI talks to the `api.*` subdomain, where routes/api.php is mounted
 * WITHOUT the `/api` prefix (see bootstrap/app.php). In this test suite we
 * run on the main domain, so each CLI-relative path is asserted as
 * `/api` + path.
 *
 * Maintained by hand next to the CLI: when a command's endpoint changes,
 * update BOTH the command and this list.
 *
 * @see packages/zelta-cli/app/Commands — one entry per ApiClient call
 */

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * [method, CLI-relative path (sample values substituted for wildcards), source command].
 *
 * @return list<array{0: string, 1: string, 2: string}>
 */
function cliRouteContract(): array
{
    return [
        // Auth (server-side token verification)
        ['GET', '/auth/user', 'Auth/LoginCommand + Auth/StatusCommand + WhoamiCommand'],

        // Pay (x402 history — pay:send's 402 retry is header-based via the
        // payment SDK against the target URL itself, not a fixed endpoint)
        ['GET', '/v1/x402/payments', 'Pay/ListCommand'],
        ['GET', '/v1/x402/payments/stats', 'Pay/StatsCommand'],
        ['GET', '/v1/x402/payments/sample-payment-id', 'Pay/StatusCommand'],

        // SMS
        ['POST', '/v1/sms/send', 'Sms/SendCommand'],
        ['GET', '/v1/sms/rates', 'Sms/RatesCommand'],
        ['GET', '/v1/sms/status/sample-message-id', 'Sms/StatusCommand'],

        // Wallet (read-only — sends are non-custodial, signed on-device)
        ['GET', '/v1/wallet/balances', 'Wallet/BalanceCommand'],
        ['GET', '/v1/wallet/tokens', 'Wallet/TokensCommand'],
        ['GET', '/v1/wallet/transactions', 'Wallet/TransactionsCommand'],
        ['GET', '/v1/wallet/transactions/by-intent/sample-intent-id', 'Wallet/IntentCommand'],

        // Ramp / Bridge.xyz setup (KYC + virtual account)
        ['GET', '/v1/user/bridge-setup-status', 'Ramp/StatusCommand'],
        ['POST', '/v1/user/bridge-kyc-link', 'Ramp/KycLinkCommand'],

        // Subscription (read-only tier/status surface)
        ['GET', '/v1/subscription/me', 'Subscription/StatusCommand'],

        // Spending limits
        ['GET', '/v1/x402/spending-limits', 'Limits/ListCommand'],
        ['POST', '/v1/x402/spending-limits', 'Limits/SetCommand'],
        ['DELETE', '/v1/x402/spending-limits/sample-agent-id', 'Limits/RemoveCommand'],

        // Agents (mounted at /agent-protocol — no /v1 segment)
        ['GET', '/agent-protocol/agents/discover', 'Agents/DiscoverCommand'],
        ['POST', '/agent-protocol/agents/register', 'Agents/RegisterCommand'],

        // SDK generation (partner surface)
        ['POST', '/partner/v1/sdk/generate', 'Sdk/GenerateCommand'],

        // Endpoints
        ['GET', '/v1/x402/endpoints', 'Endpoints/ListCommand'],
    ];
}

it('resolves every API route the CLI calls', function (): void {
    $failures = [];

    foreach (cliRouteContract() as [$method, $cliPath, $source]) {
        $mainDomainPath = '/api' . $cliPath;

        try {
            Route::getRoutes()->match(Request::create($mainDomainPath, $method));
        } catch (Throwable $e) {
            $failures[] = sprintf(
                '%s %s (%s) -> %s: %s',
                $method,
                $mainDomainPath,
                $source,
                $e::class,
                $e->getMessage(),
            );
        }
    }

    expect($failures)->toBe(
        [],
        'CLI endpoint drift detected — these paths no longer resolve: ' . implode(' | ', $failures),
    );
});
