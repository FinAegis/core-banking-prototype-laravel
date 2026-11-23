<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Jobs;

use App\Domain\Wallet\Models\WalletSendRecord;
use App\Domain\Wallet\Services\Send\HeliusRpcClient;
use App\Support\SecretRedactor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Polls Solana RPC for the outcome of submitted Solana wallet sends and flips
 * matching wallet_send_records to confirmed or failed.
 *
 * The Helius webhook ({@see \App\Domain\Wallet\Services\HeliusTransactionProcessor})
 * is the primary confirmation path, but it does not reliably deliver *failed*
 * transactions — and a send the RPC accepted that then failed on-chain (e.g.
 * insufficient SOL for the network fee) would otherwise sit in `submitted`
 * forever, invisible as a failure to the user. This job is the safety net that
 * gives every Solana send a terminal state.
 *
 * Runs every minute. Only touches rows where status='submitted',
 * network='solana', within a 2-hour window. The EVM side uses
 * {@see PollEvmWalletSendConfirmations} instead.
 */
class PollSolanaWalletSendConfirmations implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    /**
     * A submitted send still unknown to the cluster may be marked dropped only
     * after this many minutes. When the send records the last block height
     * its blockhash is valid for, the chain must also be past that height:
     * a slow or halted cluster can take longer than five minutes to get there,
     * and until then the transaction can still land.
     */
    private const int DROPPED_AFTER_MINUTES = 5;

    /** Sends with an unknown broadcast outcome are watched this long instead of the usual two hours. */
    private const int UNKNOWN_OUTCOME_WATCH_DAYS = 7;

    public function handle(HeliusRpcClient $rpc): void
    {
        $records = WalletSendRecord::query()
            ->where('status', WalletSendRecord::STATUS_SUBMITTED)
            ->where('network', 'solana')
            ->whereNotNull('tx_hash')
            ->where(function ($query): void {
                $query->where('submitted_at', '>=', now()->subHours(2))
                    ->orWhere(function ($uncertain): void {
                        // @phpstan-ignore argument.type (a JSON path, which larastan does not accept as a model column)
                        $uncertain->where('metadata->submit_outcome', WalletSendRecord::SUBMIT_OUTCOME_UNKNOWN)
                            ->where('submitted_at', '>=', now()->subDays(self::UNKNOWN_OUTCOME_WATCH_DAYS));
                    });
            })
            ->limit(100)
            ->get();

        foreach ($records as $record) {
            $this->reconcile($rpc, $record);
        }
    }

    private function reconcile(HeliusRpcClient $rpc, WalletSendRecord $record): void
    {
        $signature = (string) $record->tx_hash;
        if ($signature === '') {
            return;
        }

        $oldEnoughToDrop = $record->submitted_at !== null
            && $record->submitted_at->lt(now()->subMinutes(self::DROPPED_AFTER_MINUTES));
        $lastValidBlockHeight = $this->lastValidBlockHeight($record);

        // The height is read before the status: once the chain is past the
        // last valid height, a transaction the cluster does not know can never
        // land, and reading in this order leaves no gap for it to land unseen.
        $blockHeight = null;
        if ($oldEnoughToDrop && $lastValidBlockHeight !== null) {
            try {
                $blockHeight = $rpc->getBlockHeight();
            } catch (Throwable $e) {
                Log::warning('PollSolanaWalletSendConfirmations: could not read the block height', [
                    'record_id' => $record->id,
                    'error'     => SecretRedactor::message($e),
                ]);
            }
        }

        try {
            $statuses = $rpc->getSignatureStatuses([$signature]);
        } catch (Throwable $e) {
            Log::warning('PollSolanaWalletSendConfirmations: RPC error', [
                'record_id' => $record->id,
                'signature' => $signature,
                'error'     => SecretRedactor::message($e),
            ]);

            return;
        }

        $entry = $statuses[$signature] ?? null;

        if ($entry === null) {
            // Unknown to the cluster. Once the blockhash can no longer be used,
            // the transaction can never land, so mark it failed and the user
            // sees the send did not go through. Older sends without a recorded
            // last valid height fall back to the age rule alone.
            $blockhashExpired = $lastValidBlockHeight === null
                || ($blockHeight !== null && $blockHeight > $lastValidBlockHeight);

            if ($oldEnoughToDrop && $blockhashExpired) {
                $record->update([
                    'status'        => WalletSendRecord::STATUS_FAILED,
                    'failed_at'     => now(),
                    'error_code'    => 'SOLANA_TX_DROPPED',
                    'error_message' => 'This transfer expired before it was confirmed on the Solana network. No funds were moved.',
                ]);
            }

            return;
        }

        if ($entry['err'] !== null) {
            $record->update([
                'status'        => WalletSendRecord::STATUS_FAILED,
                'failed_at'     => now(),
                'error_code'    => 'SOLANA_TX_FAILED',
                'error_message' => 'This transfer could not be completed on the Solana network.',
            ]);

            return;
        }

        if (in_array($entry['confirmationStatus'], ['confirmed', 'finalized'], true)) {
            $record->update([
                'status'       => WalletSendRecord::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ]);
        }

        // 'processed' or '' → still in flight; poll again next tick.
    }

    private function lastValidBlockHeight(WalletSendRecord $record): ?int
    {
        $value = ($record->metadata ?? [])['last_valid_block_height'] ?? null;

        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }
}
