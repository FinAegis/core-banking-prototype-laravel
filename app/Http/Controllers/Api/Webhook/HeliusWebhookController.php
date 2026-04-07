<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessHeliusWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handle Helius Solana webhooks for address activity monitoring.
 *
 * Helius sends Enhanced Transaction webhooks for Solana token transfers.
 * The controller validates the webhook secret and dispatches a queued job
 * for async processing, returning 200 immediately.
 *
 * Setup in Helius Dashboard (https://dev.helius.xyz/dashboard):
 *   1. Create webhook -> Enhanced Transactions
 *   2. Webhook URL: https://zelta.app/api/webhooks/helius/solana
 *   3. Add authorization header with your HELIUS_WEBHOOK_SECRET
 *   4. Select token accounts to monitor (USDC: EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v)
 */
class HeliusWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        if (! $this->verifySecret($request)) {
            Log::warning('Helius webhook secret verification failed', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid authorization'], 401);
        }

        /** @var array<int, array<string, mixed>> $transactions */
        $transactions = $request->input('0') !== null ? $request->all() : [$request->all()];

        ProcessHeliusWebhookJob::dispatch($transactions);

        return response()->json(['status' => 'queued']);
    }

    /**
     * Verify the webhook secret from the Authorization header.
     *
     * Helius sends the secret in the Authorization header.
     */
    private function verifySecret(Request $request): bool
    {
        $secret = (string) config('services.helius.webhook_secret', '');

        if ($secret === '') {
            if (app()->environment('production')) {
                Log::error('Helius: HELIUS_WEBHOOK_SECRET not set in production');

                return false;
            }

            return app()->environment('local', 'testing');
        }

        // Helius sends the authHeader value exactly as configured in the dashboard
        $authHeader = $request->header('Authorization', '');

        if (! is_string($authHeader) || $authHeader === '') {
            return false;
        }

        return hash_equals($secret, $authHeader);
    }
}
