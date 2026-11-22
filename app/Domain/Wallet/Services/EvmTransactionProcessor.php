<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Account\Models\BlockchainTransaction;
use App\Domain\MobilePayment\Enums\ActivityItemType;
use App\Domain\MobilePayment\Models\ActivityFeedItem;
use App\Domain\Wallet\Constants\EvmTokens;
use App\Domain\Wallet\Models\WalletSendRecord;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors EVM (Polygon/Base/Arbitrum/Ethereum) ERC-20 transfers into our
 * database — the EVM counterpart of {@see HeliusTransactionProcessor}.
 *
 * Alchemy's Address Activity webhook fires for every USDC/USDT transfer that
 * touches a monitored token contract; {@see \App\Jobs\ProcessAlchemyWebhookJob}
 * matches the from/to address to a user and hands the activity here. We persist:
 *  - `blockchain_address_transactions` — per-chain raw transaction audit row
 *  - `activity_feed_items`             — the mobile transaction-history read model
 *
 * Before this, inbound EVM deposits triggered only a balance broadcast + push
 * and were never stored, so they never appeared in the in-app history. This
 * closes that gap and keeps EVM symmetric with Solana.
 */
class EvmTransactionProcessor
{
    /**
     * Activity keys persisted alongside the transaction. Alchemy payloads may
     * carry debug fields we have no reason to store.
     */
    private const array METADATA_WHITELIST = [
        'hash',
        'fromAddress',
        'toAddress',
        'value',
        'asset',
        'category',
        'blockNum',
        'rawContract',
    ];

    /**
     * Process a single Alchemy address-activity entry for one matched address.
     *
     * @param array<string, mixed> $activity   Alchemy address-activity item
     * @param string|null          $occurredAt ISO datetime (null = now; set by backfill)
     *
     * @return bool true if a new BlockchainTransaction row was created
     */
    public function processActivity(
        string $matchedAddress,
        BlockchainAddress $blockchainAddress,
        int $userId,
        string $chain,
        array $activity,
        ?string $occurredAt = null,
    ): bool {
        $hash = strtolower((string) ($activity['hash'] ?? ''));

        if ($hash === '') {
            return false;
        }

        $fromAddr = strtolower((string) ($activity['fromAddress'] ?? ''));
        $toAddr = strtolower((string) ($activity['toAddress'] ?? ''));
        $isIncoming = strtolower($matchedAddress) === $toAddr;

        $asset = strtoupper((string) ($activity['asset'] ?? ''));
        $amount = $this->resolveAmount($activity, $asset);

        $metadata = array_intersect_key($activity, array_flip(self::METADATA_WHITELIST));

        // An outbound send that originated from our prepare/submit flow already
        // has a `wallet_send` activity-feed item (projected by
        // WalletSendRecordObserver). Skip the duplicate feed row for those —
        // but still record the audit transaction.
        $walletSend = $isIncoming
            ? null
            : WalletSendRecord::where('tx_hash', $hash)
                ->where('network', $chain)
                ->first();

        $wasCreated = false;

        DB::transaction(function () use (
            $hash,
            $blockchainAddress,
            $isIncoming,
            $amount,
            $fromAddr,
            $toAddr,
            $metadata,
            $userId,
            $asset,
            $chain,
            $occurredAt,
            $walletSend,
            &$wasCreated,
        ): void {
            try {
                $btx = BlockchainTransaction::firstOrCreate(
                    ['tx_hash' => $hash, 'chain' => $chain],
                    [
                        'address_uuid' => $blockchainAddress->uuid,
                        'type'         => $isIncoming ? 'receive' : 'send',
                        'amount'       => $amount,
                        'fee'          => '0',
                        'from_address' => $fromAddr,
                        'to_address'   => $toAddr,
                        'status'       => 'confirmed',
                        'metadata'     => $metadata,
                    ]
                );
            } catch (QueryException $e) {
                if ($e->getCode() === '23000') {
                    // Concurrent webhook retry lost the (tx_hash, chain) race —
                    // the transaction is already persisted, nothing more to do.
                    return;
                }

                throw $e;
            }

            // Keyed per user: each platform party to a transfer gets its own
            // feed row, so a counterparty that registered the other side's
            // address cannot take this user's entry.
            if ($walletSend === null) {
                ActivityFeedItem::firstOrCreate(
                    ['reference_type' => 'evm_tx', 'reference_id' => self::txHashToReferenceId($chain, $hash), 'user_id' => $userId],
                    [
                        'activity_type' => $isIncoming ? ActivityItemType::TRANSFER_IN : ActivityItemType::TRANSFER_OUT,
                        'amount'        => $isIncoming ? $amount : '-' . $amount,
                        'asset'         => $asset,
                        'network'       => $chain,
                        'status'        => 'confirmed',
                        'protected'     => false,
                        'from_address'  => $fromAddr,
                        'to_address'    => $toAddr,
                        'occurred_at'   => $occurredAt ?? now(),
                        'metadata'      => ['tx_hash' => $hash],
                    ]
                );
            }

            $wasCreated = $btx->wasRecentlyCreated;
        });

        return $wasCreated;
    }

    /**
     * Resolve the human-readable token amount from an Alchemy activity entry.
     *
     * The integer `rawContract.rawValue` is preferred: the decimal `value`
     * field arrives as a JSON float and loses precision (or renders in
     * scientific notation) for small amounts.
     *
     * @param array<string, mixed> $activity
     */
    private function resolveAmount(array $activity, string $asset): string
    {
        $decimals = EvmTokens::DECIMALS[$asset] ?? 6;

        $rawContract = $activity['rawContract'] ?? null;
        $rawValue = is_array($rawContract) ? ($rawContract['rawValue'] ?? null) : null;

        if (is_string($rawValue) && $rawValue !== '') {
            $units = $this->hexToDecimalString($rawValue);

            return bcdiv($units, bcpow('10', (string) $decimals), 8);
        }

        $value = $activity['value'] ?? null;

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            // sprintf expands any scientific notation to a plain decimal string.
            return bcadd(sprintf('%.8f', (float) $value), '0', 8);
        }

        return '0';
    }

    /**
     * Convert a hex quantity (`0x…`) to a base-10 string without precision loss.
     */
    private function hexToDecimalString(string $hex): string
    {
        $hex = (string) preg_replace('/^0x/i', '', trim($hex));

        if ($hex === '' || ! ctype_xdigit($hex)) {
            return '0';
        }

        $decimal = '0';

        foreach (str_split($hex) as $nibble) {
            $decimal = bcadd(bcmul($decimal, '16'), (string) intval($nibble, 16));
        }

        return $decimal;
    }

    /**
     * Derive a deterministic RFC 4122 UUID for an EVM transaction's feed item.
     *
     * `activity_feed_items.reference_id` is a UUID column, but EVM tx hashes are
     * 66-char hex strings. The chain is folded into the hash input so the same
     * hash on two chains maps to distinct ids.
     */
    public static function txHashToReferenceId(string $chain, string $hash): string
    {
        $digest = substr(hash('sha256', "evm_tx:{$chain}:" . strtolower($hash)), 0, 32);

        // Force version 4 + variant 10xx so MariaDB accepts it as a valid UUID.
        $digest[12] = '4';
        $digest[16] = dechex(0x8 | (hexdec($digest[16]) & 0x3));

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($digest, 0, 8),
            substr($digest, 8, 4),
            substr($digest, 12, 4),
            substr($digest, 16, 4),
            substr($digest, 20, 12)
        );
    }
}
