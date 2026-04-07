<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhook;

use App\Domain\Wallet\Services\AlchemyWebhookManager;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessAlchemyWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handle Alchemy Token Contract Activity Webhooks (Option A).
 *
 * Instead of registering per-user address webhooks (doesn't scale to thousands
 * of users), we monitor a fixed set of token contracts (USDC, USDT per chain).
 * Alchemy fires this webhook for every ERC-20 transfer on the monitored contract.
 * We check if the from/to address belongs to a user and broadcast a balance update.
 *
 * Setup: In Alchemy Dashboard → Notify → Address Activity:
 *   - Create one webhook per chain (Polygon, Arbitrum, Ethereum)
 *   - Add the USDC + USDT contract addresses for that chain
 *   - Point to: https://zelta.app/api/webhooks/alchemy/address-activity
 *
 * This gives ~6 fixed contract addresses total (not per-user), handling
 * unlimited users with near-instant balance notifications.
 *
 * @see https://docs.alchemy.com/reference/address-activity-webhook
 */
class AlchemyWebhookController extends Controller
{
    public function __construct(
        private readonly AlchemyWebhookManager $webhookManager,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            Log::warning('Alchemy webhook signature verification failed', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();

        if (($payload['type'] ?? null) !== 'ADDRESS_ACTIVITY') {
            return response()->json(['status' => 'ignored']);
        }

        $network = strtolower((string) ($payload['event']['network'] ?? ''));
        if (str_contains($network, 'sol')) {
            return response()->json(['status' => 'ignored', 'reason' => 'solana handled by helius']);
        }

        ProcessAlchemyWebhookJob::dispatch($payload);

        return response()->json(['status' => 'queued']);
    }

    /**
     * Verify the Alchemy webhook signature using HMAC-SHA256.
     *
     * Signing keys are loaded from the webhook_endpoints table (managed by
     * AlchemyWebhookManager). We try all active keys and accept if any match.
     */
    private function verifySignature(Request $request): bool
    {
        /** @var array<string> $signingKeys */
        $signingKeys = $this->webhookManager->getSigningKeys();

        if ($signingKeys === []) {
            Log::critical('Alchemy webhook rejected: no signing keys in database');

            return app()->environment('local', 'testing');
        }

        $signature = $request->header('X-Alchemy-Signature');
        if ($signature === null) {
            return false;
        }

        $payload = $request->getContent();

        foreach ($signingKeys as $key) {
            if (hash_equals(hash_hmac('sha256', $payload, $key), $signature)) {
                return true;
            }
        }

        return false;
    }
}
