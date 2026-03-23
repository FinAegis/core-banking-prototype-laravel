<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhook;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Wallet\Events\Broadcast\WalletBalanceUpdated;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Handle Helius Solana webhooks for address activity monitoring.
 *
 * Helius sends Enhanced Transaction webhooks for Solana token transfers.
 * We check if any involved address belongs to a user and broadcast
 * a balance update via WebSocket.
 *
 * Setup in Helius Dashboard (https://dev.helius.xyz/dashboard):
 *   1. Create webhook → Enhanced Transactions
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

        $processed = 0;

        foreach ($transactions as $tx) {
            if (! is_array($tx)) {
                continue;
            }

            $processed += $this->processTransaction($tx);
        }

        Log::info('Helius webhook processed', [
            'transaction_count' => count($transactions),
            'matched'           => $processed,
        ]);

        return response()->json(['processed' => $processed]);
    }

    /**
     * Process a single Helius enhanced transaction.
     *
     * @param array<string, mixed> $tx
     */
    private function processTransaction(array $tx): int
    {
        $matched = 0;

        // Skip transactions with no token or native transfers
        if (empty($tx['tokenTransfers']) && empty($tx['nativeTransfers'])) {
            return 0;
        }

        // Check token transfers for known addresses
        /** @var array<int, array<string, mixed>> $tokenTransfers */
        $tokenTransfers = $tx['tokenTransfers'] ?? [];

        foreach ($tokenTransfers as $transfer) {
            $from = $transfer['fromUserAccount'] ?? '';
            $to = $transfer['toUserAccount'] ?? '';

            foreach ([$from, $to] as $address) {
                if (! is_string($address) || $address === '') {
                    continue;
                }

                if ($this->isKnownAddress($address)) {
                    $this->broadcastBalanceUpdate($address, $tx);
                    $matched++;
                }
            }
        }

        // Also check native SOL transfers
        /** @var array<int, array<string, mixed>> $nativeTransfers */
        $nativeTransfers = $tx['nativeTransfers'] ?? [];

        foreach ($nativeTransfers as $transfer) {
            $from = $transfer['fromUserAccount'] ?? '';
            $to = $transfer['toUserAccount'] ?? '';

            foreach ([$from, $to] as $address) {
                if (! is_string($address) || $address === '') {
                    continue;
                }

                if ($this->isKnownAddress($address)) {
                    $this->broadcastBalanceUpdate($address, $tx);
                    $matched++;
                }
            }
        }

        return $matched;
    }

    /**
     * Check if a Solana address belongs to a known user.
     */
    private function isKnownAddress(string $address): bool
    {
        $cacheKey = "solana_known_addr:{$address}";

        return Cache::remember($cacheKey, 300, function () use ($address): bool {
            return BlockchainAddress::where('address', $address)
                ->where('chain', 'solana')
                ->exists();
        });
    }

    /**
     * Broadcast a balance update for a matched address.
     *
     * @param array<string, mixed> $tx
     */
    private function broadcastBalanceUpdate(string $address, array $tx): void
    {
        $blockchainAddress = BlockchainAddress::where('address', $address)
            ->where('chain', 'solana')
            ->first();

        if ($blockchainAddress === null) {
            return;
        }

        // Look up user ID from blockchain address
        $user = \App\Models\User::where('uuid', $blockchainAddress->user_uuid)->first();

        if ($user === null) {
            return;
        }

        WalletBalanceUpdated::dispatch($user->id, 'solana');

        // Invalidate balance cache
        Cache::forget("solana_balance:{$address}");
        Cache::forget("solana_known_addr:{$address}");

        Log::info('Helius: Solana balance update broadcast', [
            'address'   => $address,
            'user_id'   => $user->id,
            'tx_type'   => $tx['type'] ?? 'unknown',
            'signature' => $tx['signature'] ?? null,
        ]);
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
            // No secret configured — accept in non-production
            return ! app()->environment('production');
        }

        $authHeader = $request->header('Authorization', '');

        return is_string($authHeader) && hash_equals($secret, $authHeader);
    }
}
