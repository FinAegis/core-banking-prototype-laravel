<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Mobile\Services\PushNotificationService;
use App\Domain\Relayer\Contracts\WalletBalanceProviderInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\Wallet\Events\Broadcast\WalletBalanceUpdated;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Process Alchemy EVM webhook payloads asynchronously.
 *
 * Moved out of the controller so the webhook endpoint responds 200 immediately.
 * Includes spam token filtering (USDC/USDT only) and block reorg detection.
 */
class ProcessAlchemyWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retrying (exponential backoff).
     *
     * @var array<int, int>
     */
    public array $backoff = [5, 30, 120];

    /**
     * Allowed token assets (spam filter).
     *
     * @var array<int, string>
     */
    private const ALLOWED_ASSETS = ['USDC', 'USDT'];

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly array $payload,
    ) {
    }

    public function handle(
        WalletBalanceProviderInterface $balanceProvider,
        PushNotificationService $pushService,
    ): void {
        $network = $this->resolveNetwork((string) ($this->payload['event']['network'] ?? ''));
        $activities = $this->payload['event']['activity'] ?? [];

        /** @var array<int, true> $notifiedUsers */
        $notifiedUsers = [];

        foreach ($activities as $activity) {
            // Reorg check: skip removed transactions
            if (($activity['removed'] ?? false) === true) {
                Log::debug('Alchemy webhook: skipping removed (reorged) activity', [
                    'hash' => $activity['hash'] ?? 'unknown',
                ]);

                continue;
            }

            // Per-transaction deduplication: skip if we've already seen this hash (10 min TTL)
            $hash = (string) ($activity['hash'] ?? '');
            if ($hash !== '' && ! Cache::add("webhook:alchemy:seen:{$hash}", true, 600)) {
                Log::debug('Alchemy webhook: skipping duplicate activity', [
                    'hash' => $hash,
                ]);

                continue;
            }

            // Spam filter: only process token/erc20 categories with USDC or USDT
            $category = $activity['category'] ?? '';
            if (! in_array($category, ['token', 'erc20'], true)) {
                continue;
            }

            $asset = strtoupper((string) ($activity['asset'] ?? ''));
            if (! in_array($asset, self::ALLOWED_ASSETS, true)) {
                continue;
            }

            $addresses = array_filter([
                $activity['fromAddress'] ?? null,
                $activity['toAddress'] ?? null,
            ]);

            foreach ($addresses as $address) {
                $address = strtolower($address);

                $userId = $this->resolveUserId($address);
                if ($userId === null) {
                    continue;
                }

                // Deduplicate within this webhook batch
                if (isset($notifiedUsers[$userId])) {
                    continue;
                }
                $notifiedUsers[$userId] = true;

                $this->invalidateBalanceCache($address, $network, $balanceProvider);

                broadcast(new WalletBalanceUpdated($userId, $network));

                $this->sendEvmPushNotification($userId, $activity, $address, $pushService);

                Log::info('Alchemy webhook: balance update broadcast', [
                    'user_id' => $userId,
                    'address' => $address,
                    'network' => $network,
                    'asset'   => $activity['asset'] ?? 'unknown',
                ]);
            }
        }
    }

    /**
     * Resolve wallet address to user ID with caching.
     *
     * Checks both blockchain_addresses and smart_accounts tables.
     * Caches the mapping for 1 hour (positive) or 5 minutes (negative).
     */
    private function resolveUserId(string $address): ?int
    {
        $cacheKey = "webhook:addr_to_user:{$address}";

        // Cache null results too (as 0) to avoid repeated DB misses
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached === 0 ? null : (int) $cached;
        }

        // Check blockchain_addresses first (legacy EOA addresses)
        $blockchainAddress = BlockchainAddress::where('address', $address)->first();
        $userId = $blockchainAddress?->user?->id;

        // Check smart_accounts if not found (ERC-4337 accounts)
        if ($userId === null) {
            $smartAccount = SmartAccount::where('account_address', $address)->first();
            $userId = $smartAccount?->user_id;
        }

        // Positive cache: 1 hour. Negative cache: 5 min (new users get detected faster).
        $ttl = $userId !== null ? 3600 : 300;
        Cache::put($cacheKey, $userId ?? 0, $ttl);

        return $userId;
    }

    /**
     * Invalidate the WalletBalanceService cache for this address.
     */
    private function invalidateBalanceCache(
        string $address,
        ?string $network,
        WalletBalanceProviderInterface $balanceProvider,
    ): void {
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
            $balanceProvider->invalidateCache($address, (string) $token, $supportedNetwork);
        }
    }

    /**
     * Send FCM push notification for an EVM token transfer (best-effort).
     *
     * @param array<string, mixed> $activity
     */
    private function sendEvmPushNotification(
        int $userId,
        array $activity,
        string $matchedAddress,
        PushNotificationService $pushService,
    ): void {
        try {
            $user = User::find($userId);
            if ($user === null) {
                return;
            }

            $fromAddress = strtolower((string) ($activity['fromAddress'] ?? ''));
            $toAddress = strtolower((string) ($activity['toAddress'] ?? ''));
            $isIncoming = strtolower($matchedAddress) === $toAddress;

            $asset = (string) ($activity['asset'] ?? 'unknown');
            $amount = (string) ($activity['value'] ?? '0');

            $counterpartyAddr = $isIncoming ? ($fromAddress ?: 'unknown') : ($toAddress ?: 'unknown');
            $truncatedAddr = substr($counterpartyAddr, 0, 6) . '...' . substr($counterpartyAddr, -4);

            if ($isIncoming) {
                $pushService->sendTransactionReceived($user, $amount, $asset, $truncatedAddr);
            } else {
                $pushService->sendTransactionSent($user, $amount, $asset, $truncatedAddr);
            }
        } catch (Throwable $e) {
            Log::warning('Alchemy EVM: Push notification failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Map Alchemy network names to our chain_id format.
     */
    private function resolveNetwork(string $alchemyNetwork): ?string
    {
        return match (strtolower($alchemyNetwork)) {
            'eth-mainnet', 'eth_mainnet'       => 'ethereum',
            'polygon-mainnet', 'matic_mainnet' => 'polygon',
            'arb-mainnet', 'arb_mainnet'       => 'arbitrum',
            'base-mainnet', 'base_mainnet'     => 'base',
            default                            => null,
        };
    }
}
