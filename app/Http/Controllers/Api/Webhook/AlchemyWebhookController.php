<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhook;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Mobile\Services\PushNotificationService;
use App\Domain\Relayer\Contracts\WalletBalanceProviderInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Wallet\Constants\SolanaCacheKeys;
use App\Domain\Wallet\Events\Broadcast\WalletBalanceUpdated;
use App\Domain\Wallet\Factories\BlockchainConnectorFactory;
use App\Domain\Wallet\Services\HeliusTransactionProcessor;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        private readonly HeliusTransactionProcessor $transactionProcessor,
        private readonly PushNotificationService $pushService,
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

        // Split processing: Solana uses HeliusTransactionProcessor, EVM uses balance-only path
        if ($network === 'solana') {
            $usersNotified = $this->processSolanaActivities($activities, $network);

            return response()->json([
                'status'         => 'processed',
                'users_notified' => $usersNotified,
            ]);
        }

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
     * Process Solana activities from Alchemy webhook.
     *
     * Alchemy sends Solana transfers in the same ADDRESS_ACTIVITY format as EVM.
     * We convert the Alchemy payload to the Helius format expected by HeliusTransactionProcessor.
     *
     * @param array<int, array<string, mixed>> $activities
     *
     * @return int number of users notified
     */
    private function processSolanaActivities(array $activities, string $network): int
    {
        $notifiedUsers = [];

        foreach ($activities as $activity) {
            // Accept token, external (native SOL), and transfer categories
            $category = $activity['category'] ?? '';
            if (! in_array($category, ['token', 'external', 'transfer'], true)) {
                continue;
            }

            $fromAddress = $activity['fromAddress'] ?? null;
            $toAddress = $activity['toAddress'] ?? null;
            $addresses = array_filter([$fromAddress, $toAddress]);

            foreach ($addresses as $address) {
                // Solana addresses are case-sensitive — do NOT lowercase
                $userId = $this->resolveUserId($address);
                if ($userId === null) {
                    continue;
                }

                $blockchainAddress = BlockchainAddress::where('address', $address)
                    ->where('chain', 'solana')
                    ->first();

                if ($blockchainAddress === null) {
                    continue;
                }

                $user = User::where('uuid', $blockchainAddress->user_uuid)->first();
                if ($user === null) {
                    continue;
                }

                // Convert to Helius-compatible format and persist via shared processor
                $heliusTx = $this->convertToHeliusFormat($activity);
                $signature = $heliusTx['signature'] ?? '';

                if ($signature !== '') {
                    $this->transactionProcessor->processTransaction(
                        $address,
                        $blockchainAddress,
                        $user->id,
                        $heliusTx,
                    );
                }

                // Broadcast balance update (deduplicate per user within batch)
                if (! isset($notifiedUsers[$userId])) {
                    WalletBalanceUpdated::dispatch($userId, $network);
                    $notifiedUsers[$userId] = true;
                }

                // Invalidate Solana balance caches
                Cache::forget(SolanaCacheKeys::balance($address));
                Cache::forget(SolanaCacheKeys::balances($address));

                // Pre-warm balance cache (best-effort)
                try {
                    $connector = BlockchainConnectorFactory::create('solana');
                    $balanceData = $connector->getBalance($address);
                    Cache::put(SolanaCacheKeys::balance($address), $balanceData->balance, 300);
                } catch (Throwable) {
                    // Pre-warm is best-effort — next request will query fresh
                }

                // Send FCM push notification
                $this->sendSolanaPushNotification($address, $heliusTx, $user);

                Log::info('Alchemy webhook: Solana transaction stored and balance update broadcast', [
                    'address'   => $address,
                    'user_id'   => $userId,
                    'network'   => $network,
                    'signature' => $signature,
                    'asset'     => $activity['asset'] ?? 'unknown',
                ]);
            }
        }

        return count($notifiedUsers);
    }

    /**
     * Convert an Alchemy activity payload to Helius-compatible format.
     *
     * Maps Alchemy ADDRESS_ACTIVITY fields to the structure expected by
     * HeliusTransactionProcessor (tokenTransfers / nativeTransfers).
     *
     * @param array<string, mixed> $activity
     *
     * @return array<string, mixed>
     */
    private function convertToHeliusFormat(array $activity): array
    {
        $signature = (string) ($activity['hash'] ?? '');
        $category = $activity['category'] ?? '';
        $fromAddress = (string) ($activity['fromAddress'] ?? '');
        $toAddress = (string) ($activity['toAddress'] ?? '');

        $tokenTransfers = [];
        $nativeTransfers = [];

        if ($category === 'external') {
            // Native SOL transfer — Alchemy gives value in SOL (decimal), convert to lamports
            $solValue = (string) ($activity['value'] ?? '0');
            $lamports = is_numeric($solValue)
                ? (string) (int) bcmul($solValue, '1000000000', 0)
                : '0';

            $nativeTransfers[] = [
                'fromUserAccount' => $fromAddress,
                'toUserAccount'   => $toAddress,
                'amount'          => (int) $lamports,
            ];
        } else {
            // SPL token transfer
            $mint = (string) ($activity['rawContract']['address'] ?? '');
            $value = (string) ($activity['value'] ?? '0');

            $tokenTransfers[] = [
                'fromUserAccount' => $fromAddress,
                'toUserAccount'   => $toAddress,
                'tokenAmount'     => $value,
                'mint'            => $mint,
            ];
        }

        return [
            'signature'       => $signature,
            'type'            => 'TRANSFER',
            'fee'             => 0,
            'tokenTransfers'  => $tokenTransfers,
            'nativeTransfers' => $nativeTransfers,
        ];
    }

    /**
     * Send FCM push notification for a Solana transaction.
     *
     * @param array<string, mixed> $heliusTx
     */
    private function sendSolanaPushNotification(string $address, array $heliusTx, User $user): void
    {
        try {
            $isIncoming = $this->transactionProcessor->isIncoming($address, $heliusTx);
            $token = $this->transactionProcessor->resolveToken($heliusTx);
            $amount = $this->transactionProcessor->resolveAmount($heliusTx);
            $fromAddr = $this->transactionProcessor->resolveFromAddress($heliusTx);
            $toAddr = $this->transactionProcessor->resolveToAddress($heliusTx);

            $counterpartyAddr = $isIncoming ? ($fromAddr ?? 'unknown') : ($toAddr ?? 'unknown');
            $truncatedAddr = substr($counterpartyAddr, 0, 4) . '...' . substr($counterpartyAddr, -4);

            if ($isIncoming) {
                $this->pushService->sendTransactionReceived($user, $amount, $token, $truncatedAddr);
            } else {
                $this->pushService->sendTransactionSent($user, $amount, $token, $truncatedAddr);
            }
        } catch (Throwable $e) {
            Log::warning('Alchemy Solana: Push notification failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
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
     *
     * Each chain webhook has its own signing key. We try all configured
     * keys and accept if any one matches (same endpoint for all chains).
     */
    private function verifySignature(Request $request): bool
    {
        /** @var array<string> $signingKeys */
        $signingKeys = config('relayer.alchemy_webhook_signing_keys', []);

        if ($signingKeys === []) {
            Log::critical('Alchemy webhook rejected: no signing keys configured');

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
            'sol-mainnet', 'solana_mainnet', 'solana-mainnet' => 'solana',
            default => null,
        };
    }
}
