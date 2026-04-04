<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhook;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Mobile\Services\PushNotificationService;
use App\Domain\Wallet\Constants\SolanaCacheKeys;
use App\Domain\Wallet\Events\Broadcast\WalletBalanceUpdated;
use App\Domain\Wallet\Factories\BlockchainConnectorFactory;
use App\Domain\Wallet\Services\HeliusTransactionProcessor;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handle Helius Solana webhooks for address activity monitoring.
 *
 * Helius sends Enhanced Transaction webhooks for Solana token transfers.
 * We check if any involved address belongs to a user and broadcast
 * a balance update via WebSocket.
 *
 * Setup in Helius Dashboard (https://dev.helius.xyz/dashboard):
 *   1. Create webhook -> Enhanced Transactions
 *   2. Webhook URL: https://zelta.app/api/webhooks/helius/solana
 *   3. Add authorization header with your HELIUS_WEBHOOK_SECRET
 *   4. Select token accounts to monitor (USDC: EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v)
 */
class HeliusWebhookController extends Controller
{
    public function __construct(
        private readonly HeliusTransactionProcessor $processor,
        private readonly PushNotificationService $pushService,
    ) {
    }

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
        $processedAddresses = [];

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

                if (in_array($address, $processedAddresses, true)) {
                    continue;
                }

                if ($this->isKnownAddress($address)) {
                    $this->broadcastBalanceUpdate($address, $tx);
                    $processedAddresses[] = $address;
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

                if (in_array($address, $processedAddresses, true)) {
                    continue;
                }

                if ($this->isKnownAddress($address)) {
                    $this->broadcastBalanceUpdate($address, $tx);
                    $processedAddresses[] = $address;
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
        $cacheKey = SolanaCacheKeys::knownAddr($address);

        return Cache::remember($cacheKey, 300, function () use ($address): bool {
            return BlockchainAddress::where('address', $address)
                ->where('chain', 'solana')
                ->exists();
        });
    }

    /**
     * Broadcast a balance update and store transaction for a matched address.
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

        $user = \App\Models\User::where('uuid', $blockchainAddress->user_uuid)->first();

        if ($user === null) {
            return;
        }

        $signature = $tx['signature'] ?? '';

        // Store blockchain transaction + activity feed item via shared processor
        if ($signature !== '') {
            $this->processor->processTransaction($address, $blockchainAddress, $user->id, $tx);
        }

        WalletBalanceUpdated::dispatch($user->id, 'solana');

        // Invalidate and pre-warm balance cache so next mobile request hits warm cache
        Cache::forget(SolanaCacheKeys::balance($address));
        Cache::forget(SolanaCacheKeys::balances($address));

        try {
            $connector = BlockchainConnectorFactory::create('solana');
            $balanceData = $connector->getBalance($address);
            Cache::put(SolanaCacheKeys::balance($address), $balanceData->balance, 300);
        } catch (Throwable) {
            // Pre-warm is best-effort -- next request will query fresh
        }

        $isIncoming = $this->processor->isIncoming($address, $tx);
        $token = $this->processor->resolveToken($tx);
        $amount = $this->processor->resolveAmount($tx);
        $fromAddr = $this->processor->resolveFromAddress($tx);
        $toAddr = $this->processor->resolveToAddress($tx);

        // Send FCM push notification
        try {
            $counterpartyAddr = $isIncoming ? ($fromAddr ?? 'unknown') : ($toAddr ?? 'unknown');
            $truncatedAddr = substr($counterpartyAddr, 0, 4) . '...' . substr($counterpartyAddr, -4);

            if ($isIncoming) {
                $this->pushService->sendTransactionReceived($user, $amount, $token, $truncatedAddr);
            } else {
                $this->pushService->sendTransactionSent($user, $amount, $token, $truncatedAddr);
            }
        } catch (Throwable $e) {
            Log::warning('Helius: Push notification failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        Log::info('Helius: Solana transaction stored and balance update broadcast', [
            'address'   => $address,
            'user_id'   => $user->id,
            'tx_type'   => $isIncoming ? 'receive' : 'send',
            'token'     => $token,
            'amount'    => $amount,
            'signature' => $signature,
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
