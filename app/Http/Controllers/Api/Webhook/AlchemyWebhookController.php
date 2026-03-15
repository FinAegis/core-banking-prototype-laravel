<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhook;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Wallet\Events\Broadcast\WalletBalanceUpdated;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handle Alchemy Address Activity Webhooks.
 *
 * When a tracked wallet address receives or sends tokens, Alchemy pushes
 * an event here. We identify the user by address and broadcast a
 * WalletBalanceUpdated event so the mobile app invalidates its cache.
 *
 * This eliminates per-user polling and gives near-instant balance updates.
 *
 * @see https://docs.alchemy.com/reference/address-activity-webhook
 */
class AlchemyWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // Verify webhook signature
        if (! $this->verifySignature($request)) {
            Log::warning('Alchemy webhook signature verification failed', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        $webhookType = $payload['type'] ?? null;

        if ($webhookType !== 'ADDRESS_ACTIVITY') {
            return response()->json(['status' => 'ignored']);
        }

        $activities = $payload['event']['activity'] ?? [];
        $network = $this->resolveNetwork($payload['event']['network'] ?? '');

        $notifiedUsers = [];

        foreach ($activities as $activity) {
            $addresses = array_filter([
                $activity['fromAddress'] ?? null,
                $activity['toAddress'] ?? null,
            ]);

            foreach ($addresses as $address) {
                $address = strtolower($address);

                // Find the user who owns this address
                $blockchainAddress = BlockchainAddress::where('address', $address)->first();
                if ($blockchainAddress === null || $blockchainAddress->user === null) {
                    continue;
                }

                $userId = $blockchainAddress->user->id;

                // Deduplicate: only notify each user once per webhook batch
                if (isset($notifiedUsers[$userId])) {
                    continue;
                }
                $notifiedUsers[$userId] = true;

                broadcast(new WalletBalanceUpdated($userId, $network));

                Log::info('Alchemy webhook: balance update broadcast', [
                    'user_id'  => $userId,
                    'address'  => $address,
                    'network'  => $network,
                    'category' => $activity['category'] ?? 'unknown',
                ]);
            }
        }

        return response()->json([
            'status'         => 'processed',
            'users_notified' => count($notifiedUsers),
        ]);
    }

    /**
     * Verify the Alchemy webhook signature using HMAC-SHA256.
     */
    private function verifySignature(Request $request): bool
    {
        $signingKey = config('relayer.alchemy_webhook_signing_key');

        // Skip verification if no signing key configured (dev/testing)
        if (empty($signingKey)) {
            return true;
        }

        $signature = $request->header('X-Alchemy-Signature');
        if ($signature === null) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $request->getContent(), $signingKey);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Map Alchemy network names to our chain_id format.
     */
    private function resolveNetwork(string $alchemyNetwork): ?string
    {
        return match (strtolower($alchemyNetwork)) {
            'eth-mainnet', 'eth_mainnet' => 'ethereum',
            'polygon-mainnet', 'matic_mainnet' => 'polygon',
            'arb-mainnet', 'arb_mainnet' => 'arbitrum',
            'base-mainnet', 'base_mainnet' => 'base',
            'opt-mainnet', 'opt_mainnet' => 'optimism',
            default => null,
        };
    }
}
