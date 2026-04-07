<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Account\Models\BlockchainTransaction;
use App\Domain\MobilePayment\Enums\ActivityItemType;
use App\Domain\MobilePayment\Models\ActivityFeedItem;
use App\Domain\Wallet\Constants\SolanaTokens;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Shared Helius transaction parsing and persistence logic.
 *
 * Used by both the HeliusWebhookController (real-time) and the
 * SolanaTransactionBackfillCommand (historical backfill) to avoid
 * duplicating parsing, storage, and dedup logic.
 */
class HeliusTransactionProcessor
{
    /**
     * Allowed metadata keys stored alongside blockchain transactions.
     *
     * Only these fields are persisted — raw Helius payloads may contain
     * PII or debug data that should not be stored.
     */
    private const array METADATA_WHITELIST = [
        'signature',
        'timestamp',
        'fee',
        'type',
        'source',
        'tokenTransfers',
        'nativeTransfers',
    ];

    /**
     * Process a single Helius transaction and persist to both tables.
     *
     * @param array<string, mixed> $tx        Helius enhanced transaction payload
     * @param string|null          $occurredAt ISO datetime for historical backfill (null = now)
     *
     * @return bool true if a new BlockchainTransaction was created, false if it already existed
     */
    public function processTransaction(
        string $address,
        BlockchainAddress $blockchainAddress,
        int $userId,
        array $tx,
        ?string $occurredAt = null,
    ): bool {
        $signature = (string) ($tx['signature'] ?? '');

        if ($signature === '') {
            return false;
        }

        $isIncoming = $this->isIncoming($address, $tx);
        $amount = $this->resolveAmount($tx);
        $token = $this->resolveToken($tx);
        $fromAddr = $this->resolveFromAddress($tx);
        $toAddr = $this->resolveToAddress($tx);
        $refId = self::signatureToUuid($signature);

        $feeRaw = (string) ($tx['fee'] ?? 0);
        $fee = bcadd(is_numeric($feeRaw) ? $feeRaw : '0', '0', 18);

        $metadata = array_intersect_key($tx, array_flip(self::METADATA_WHITELIST));

        $wasCreated = false;

        DB::transaction(function () use (
            $signature,
            $blockchainAddress,
            $isIncoming,
            $amount,
            $fee,
            $fromAddr,
            $toAddr,
            $metadata,
            $refId,
            $userId,
            $token,
            $occurredAt,
            &$wasCreated,
        ): void {
            try {
                $btx = BlockchainTransaction::firstOrCreate(
                    ['tx_hash' => $signature, 'chain' => 'solana'],
                    [
                        'address_uuid' => $blockchainAddress->uuid,
                        'type'         => $isIncoming ? 'receive' : 'send',
                        'amount'       => $amount,
                        'fee'          => $fee,
                        'from_address' => $fromAddr ?? '',
                        'to_address'   => $toAddr ?? '',
                        'status'       => 'confirmed',
                        'metadata'     => $metadata,
                    ]
                );
            } catch (QueryException $e) {
                if ($e->getCode() === '23000') {
                    // Duplicate entry — race condition on concurrent webhook retries.
                    // The unique constraint on (tx_hash, chain) fired; fetch the winner.
                    $btx = BlockchainTransaction::where('tx_hash', $signature)
                        ->where('chain', 'solana')
                        ->first();

                    if ($btx === null) {
                        Log::warning('Duplicate tx_hash but record not found', ['signature' => $signature]);

                        return;
                    }

                    // Transaction already persisted — nothing more to do.
                    return;
                }

                throw $e;
            }

            ActivityFeedItem::firstOrCreate(
                ['reference_type' => 'solana_tx', 'reference_id' => $refId],
                [
                    'user_id'       => $userId,
                    'activity_type' => $isIncoming ? ActivityItemType::TRANSFER_IN : ActivityItemType::TRANSFER_OUT,
                    'amount'        => $isIncoming ? $amount : '-' . $amount,
                    'asset'         => $token,
                    'network'       => 'solana',
                    'status'        => 'confirmed',
                    'protected'     => false,
                    'from_address'  => $fromAddr,
                    'to_address'    => $toAddr,
                    'occurred_at'   => $occurredAt ?? now(),
                    'metadata'      => ['signature' => $signature],
                ]
            );

            $wasCreated = $btx->wasRecentlyCreated;
        });

        return $wasCreated;
    }

    /**
     * Determine if the transaction is incoming relative to the matched address.
     *
     * @param array<string, mixed> $tx
     */
    public function isIncoming(string $address, array $tx): bool
    {
        /** @var array<int, array<string, mixed>> $tokenTransfers */
        $tokenTransfers = $tx['tokenTransfers'] ?? [];

        foreach ($tokenTransfers as $transfer) {
            if (($transfer['toUserAccount'] ?? '') === $address) {
                return true;
            }
            if (($transfer['fromUserAccount'] ?? '') === $address) {
                return false;
            }
        }

        /** @var array<int, array<string, mixed>> $nativeTransfers */
        $nativeTransfers = $tx['nativeTransfers'] ?? [];

        foreach ($nativeTransfers as $transfer) {
            if (($transfer['toUserAccount'] ?? '') === $address) {
                return true;
            }
            if (($transfer['fromUserAccount'] ?? '') === $address) {
                return false;
            }
        }

        return true; // Default to incoming if direction unclear
    }

    /**
     * Resolve the human-readable amount from a Helius transaction.
     *
     * @param array<string, mixed> $tx
     */
    public function resolveAmount(array $tx): string
    {
        /** @var array<int, array<string, mixed>> $tokenTransfers */
        $tokenTransfers = $tx['tokenTransfers'] ?? [];

        if (! empty($tokenTransfers)) {
            $raw = (string) ($tokenTransfers[0]['tokenAmount'] ?? '0');

            // Normalize to numeric-string for bcmath
            return bcadd(is_numeric($raw) ? $raw : '0', '0', 8);
        }

        /** @var array<int, array<string, mixed>> $nativeTransfers */
        $nativeTransfers = $tx['nativeTransfers'] ?? [];

        if (! empty($nativeTransfers)) {
            // Native SOL: amount is in lamports (1 SOL = 1e9 lamports)
            $lamports = (string) ($nativeTransfers[0]['amount'] ?? '0');

            if (! is_numeric($lamports)) {
                return '0';
            }

            return bcdiv($lamports, '1000000000', 9);
        }

        return '0';
    }

    /**
     * Resolve the token symbol from a Helius transaction.
     *
     * @param array<string, mixed> $tx
     */
    public function resolveToken(array $tx): string
    {
        /** @var array<int, array<string, mixed>> $tokenTransfers */
        $tokenTransfers = $tx['tokenTransfers'] ?? [];

        if (! empty($tokenTransfers)) {
            $mint = $tokenTransfers[0]['mint'] ?? '';

            return SolanaTokens::resolveSymbol($mint);
        }

        return 'SOL';
    }

    /**
     * @param array<string, mixed> $tx
     */
    public function resolveFromAddress(array $tx): ?string
    {
        $transfers = ! empty($tx['tokenTransfers']) ? $tx['tokenTransfers'] : ($tx['nativeTransfers'] ?? []);

        return $transfers[0]['fromUserAccount'] ?? null;
    }

    /**
     * @param array<string, mixed> $tx
     */
    public function resolveToAddress(array $tx): ?string
    {
        $transfers = ! empty($tx['tokenTransfers']) ? $tx['tokenTransfers'] : ($tx['nativeTransfers'] ?? []);

        return $transfers[0]['toUserAccount'] ?? null;
    }

    /**
     * Convert a Solana transaction signature to a deterministic UUID.
     *
     * The activity_feed_items.reference_id column is UUID type, but Solana
     * signatures are ~88 char base58 strings. Hash into UUID v4-like format
     * using SHA-256 for collision resistance.
     */
    public static function signatureToUuid(string $signature): string
    {
        $hash = substr(hash('sha256', "solana_tx:{$signature}"), 0, 32);

        // Set version 4 (byte 7, high nibble) and variant 10xx (byte 9, high bits)
        // to produce a valid RFC 4122 UUID that MariaDB accepts.
        $hash[12] = '4';
        $hash[16] = dechex(0x8 | (hexdec($hash[16]) & 0x3));

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );
    }
}
