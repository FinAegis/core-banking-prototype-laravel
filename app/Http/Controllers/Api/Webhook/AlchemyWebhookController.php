<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhook;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Relayer\Contracts\WalletBalanceProviderInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Wallet\Events\Broadcast\WalletBalanceUpdated;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        private readonly WalletBalanceProviderInterface $balanceProvider,
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
        $webhookType = $payload['type'] ?? null;

        if ($webhookType !== 'ADDRESS_ACTIVITY') {
            return response()->json(['status' => 'ignored']);
        }

        $activities = $payload['event']['activity'] ?? [];
        $network = $this->resolveNetwork($payload['event']['network'] ?? '');

        $notifiedUsers = [];

        foreach ($activities as $activity) {
            // Only process ERC-20 token transfers (not native ETH/MATIC)
            $category = $activity['category'] ?? '';
            if (! in_array($category, ['token', 'erc20'], true)) {
                continue;
            }

            $addresses = array_filter([
                $activity['fromAddress'] ?? null,
                $activity['toAddress'] ?? null,
            ]);

            foreach ($addresses as $address) {
                $address = strtolower($address);

                // Fast lookup: check cached address→userId map first
                $userId = $this->resolveUserId($address);
                if ($userId === null) {
                    continue;
                }

                // Deduplicate within this webhook batch
                if (isset($notifiedUsers[$userId])) {
                    continue;
                }
                $notifiedUsers[$userId] = true;

                // Invalidate the cached balance so next fetch gets fresh data
                $this->invalidateBalanceCache($address, $network);

                broadcast(new WalletBalanceUpdated($userId, $network));

                Log::info('Alchemy webhook: balance update broadcast', [
                    'user_id' => $userId,
                    'address' => $address,
                    'network' => $network,
                    'asset'   => $activity['asset'] ?? 'unknown',
                ]);
            }
        }

        return response()->json([
            'status'         => 'processed',
            'users_notified' => count($notifiedUsers),
        ]);
    }

    /**
     * Resolve wallet address to user ID with caching.
     *
     * Caches the address→userId mapping for 1 hour to avoid DB lookups
     * on every webhook call (token contracts fire for ALL transfers, not just ours).
     */
    private function resolveUserId(string $address): ?int
    {
        $cacheKey = "webhook:addr_to_user:{$address}";

        // Cache null results too (as 0) to avoid repeated DB misses
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached === 0 ? null : (int) $cached;
        }

        $blockchainAddress = BlockchainAddress::where('address', $address)->first();
        $userId = $blockchainAddress?->user?->id;

        // Positive cache: 1 hour. Negative cache: 5 min (new users get detected faster).
        $ttl = $userId !== null ? 3600 : 300;
        Cache::put($cacheKey, $userId ?? 0, $ttl);

        return $userId;
    }

    /**
     * Invalidate the WalletBalanceService cache for this address.
     */
    private function invalidateBalanceCache(string $address, ?string $network): void
    {
        if ($network === null) {
            return;
        }

        $supportedNetwork = SupportedNetwork::tryFrom($network);
        if ($supportedNetwork === null) {
            return;
        }

        /** @var array<string, mixed> $tokenConfig */
        $tokenConfig = config('relayer.balance_checking.tokens', ['USDC' => [], 'USDT' => []]);
        foreach (array_keys($tokenConfig) as $token) {
            $this->balanceProvider->invalidateCache($address, (string) $token, $supportedNetwork);
        }
    }

    /**
     * Verify the Alchemy webhook signature using HMAC-SHA256.
     */
    private function verifySignature(Request $request): bool
    {
        $signingKey = config('relayer.alchemy_webhook_signing_key');

        // Reject all requests if signing key is not configured (secure by default)
        if (empty($signingKey)) {
            Log::critical('Alchemy webhook rejected: ALCHEMY_WEBHOOK_SIGNING_KEY not configured');

            return app()->environment('local', 'testing');
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
