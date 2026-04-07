<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Mobile\Services\PushNotificationService;
use App\Domain\Wallet\Constants\SolanaCacheKeys;
use App\Domain\Wallet\Events\Broadcast\WalletBalanceUpdated;
use App\Domain\Wallet\Factories\BlockchainConnectorFactory;
use App\Domain\Wallet\Services\HeliusTransactionProcessor;
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
 * Process Helius Solana webhook payloads asynchronously.
 *
 * Moved out of the controller so the webhook endpoint responds 200 immediately.
 * Includes per-transaction deduplication via Cache to prevent duplicate FCM pushes.
 */
class ProcessHeliusWebhookJob implements ShouldQueue
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
     * @param array<int, array<string, mixed>> $transactions
     */
    public function __construct(
        public readonly array $transactions,
    ) {
    }

    public function handle(
        HeliusTransactionProcessor $processor,
        PushNotificationService $pushService,
    ): void {
        $processed = 0;

        foreach ($this->transactions as $tx) {
            if (! is_array($tx)) {
                continue;
            }

            $processed += $this->processTransaction($tx, $processor, $pushService);
        }

        Log::info('Helius webhook job processed', [
            'transaction_count' => count($this->transactions),
            'matched'           => $processed,
        ]);
    }

    /**
     * Process a single Helius enhanced transaction.
     *
     * @param array<string, mixed> $tx
     */
    private function processTransaction(
        array $tx,
        HeliusTransactionProcessor $processor,
        PushNotificationService $pushService,
    ): int {
        $matched = 0;

        /** @var array<int, string> $processedAddresses */
        $processedAddresses = [];

        // Skip transactions with no token or native transfers
        if (empty($tx['tokenTransfers']) && empty($tx['nativeTransfers'])) {
            return 0;
        }

        // Per-transaction deduplication: skip if we've already seen this signature
        $signature = (string) ($tx['signature'] ?? '');
        if ($signature !== '' && ! Cache::add("webhook:helius:seen:{$signature}", true, 600)) {
            Log::debug('Helius webhook: skipping duplicate transaction', [
                'signature' => $signature,
            ]);

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
                    $this->broadcastBalanceUpdate($address, $tx, $processor, $pushService);
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
                    $this->broadcastBalanceUpdate($address, $tx, $processor, $pushService);
                    $processedAddresses[] = $address;
                    $matched++;
                }
            }
        }

        return $matched;
    }

    /**
     * Check if a Solana address belongs to a known user.
     *
     * Note: Solana addresses are case-sensitive — never strtolower().
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
    private function broadcastBalanceUpdate(
        string $address,
        array $tx,
        HeliusTransactionProcessor $processor,
        PushNotificationService $pushService,
    ): void {
        $blockchainAddress = BlockchainAddress::where('address', $address)
            ->where('chain', 'solana')
            ->first();

        if ($blockchainAddress === null) {
            return;
        }

        $user = User::where('uuid', $blockchainAddress->user_uuid)->first();

        if ($user === null) {
            return;
        }

        $signature = $tx['signature'] ?? '';

        // Store blockchain transaction + activity feed item via shared processor
        if ($signature !== '') {
            $processor->processTransaction($address, $blockchainAddress, $user->id, $tx);
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

        $isIncoming = $processor->isIncoming($address, $tx);
        $token = $processor->resolveToken($tx);
        $amount = $processor->resolveAmount($tx);
        $fromAddr = $processor->resolveFromAddress($tx);
        $toAddr = $processor->resolveToAddress($tx);

        // Send FCM push notification
        try {
            $counterpartyAddr = $isIncoming ? ($fromAddr ?? 'unknown') : ($toAddr ?? 'unknown');
            $truncatedAddr = substr($counterpartyAddr, 0, 4) . '...' . substr($counterpartyAddr, -4);

            if ($isIncoming) {
                $pushService->sendTransactionReceived($user, $amount, $token, $truncatedAddr);
            } else {
                $pushService->sendTransactionSent($user, $amount, $token, $truncatedAddr);
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
}
