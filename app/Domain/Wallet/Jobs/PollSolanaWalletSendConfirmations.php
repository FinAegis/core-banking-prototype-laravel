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
     * A submitted send still unknown to the cluster after this many minutes
     * can never land — its blockhash validity window (~60-90s) has long since
     * expired — so it is marked failed.
     */
    private const int DROPPED_AFTER_MINUTES = 5;

    public function handle(HeliusRpcClient $rpc): void
    {
        $records = WalletSendRecord::query()
            ->where('status', WalletSendRecord::STATUS_SUBMITTED)
            ->where('network', 'solana')
            ->whereNotNull('tx_hash')
            ->where('submitted_at', '>=', now()->subHours(2))
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
            // Unknown to the cluster. Once the blockhash-validity window has
            // long passed, the transaction can never land — mark it failed so
            // the user sees the send did not go through.
            if (
                $record->submitted_at !== null
                && $record->submitted_at->lt(now()->subMinutes(self::DROPPED_AFTER_MINUTES))
            ) {
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
}
