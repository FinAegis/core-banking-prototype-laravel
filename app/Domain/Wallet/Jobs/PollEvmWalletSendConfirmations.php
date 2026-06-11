<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Jobs;

use App\Domain\Relayer\Contracts\BundlerInterface;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Domain\Wallet\Services\SponsorshipCostTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Polls the bundler for receipt of submitted EVM wallet sends and flips
 * matching wallet_send_records to confirmed (or failed) once the on-chain
 * receipt is observed.
 *
 * Runs on a short interval — every minute is plenty for ERC-4337 typical
 * confirmation latency. Only touches rows where status='submitted' and
 * network is an EVM key.
 *
 * Solana confirmations are handled in HeliusTransactionProcessor (webhook
 * path), not here.
 */
class PollEvmWalletSendConfirmations implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function handle(BundlerInterface $bundler, SponsorshipCostTracker $costTracker): void
    {
        $records = WalletSendRecord::query()
            ->where('status', WalletSendRecord::STATUS_SUBMITTED)
            ->whereNotNull('user_op_hash')
            ->whereIn('network', ['polygon', 'base', 'arbitrum', 'ethereum'])
            ->where('submitted_at', '>=', now()->subHours(2))
            ->limit(100)
            ->get();

        foreach ($records as $record) {
            $this->reconcile($bundler, $costTracker, $record);
        }
    }

    private function reconcile(
        BundlerInterface $bundler,
        SponsorshipCostTracker $costTracker,
        WalletSendRecord $record,
    ): void {
        if ($record->user_op_hash === null) {
            return;
        }

        try {
            $status = $bundler->getUserOperationStatus((string) $record->user_op_hash);
        } catch (Throwable $e) {
            Log::warning('PollEvmWalletSendConfirmations: bundler error', [
                'record_id'    => $record->id,
                'user_op_hash' => $record->user_op_hash,
                'network'      => $record->network,
                'error'        => $e->getMessage(),
            ]);

            return;
        }

        if (! isset($status['status'])) {
            return;
        }

        $reportedStatus = (string) $status['status'];
        $txHash = isset($status['tx_hash']) && is_string($status['tx_hash']) ? $status['tx_hash'] : null;

        if ($reportedStatus === 'confirmed') {
            // Sponsorship cost tracking: every userOp send goes through the
            // Pimlico paymaster, so the gas for a confirmed EVM send was
            // platform-sponsored — record what it actually cost (wei).
            $feeWei = $this->resolveSponsoredFeeWei(
                isset($status['receipt']) && is_array($status['receipt']) ? $status['receipt'] : null
            );

            $update = [
                'status'       => WalletSendRecord::STATUS_CONFIRMED,
                'tx_hash'      => $txHash,
                'confirmed_at' => now(),
            ];

            if ($feeWei !== null) {
                $update['sponsored_fee_raw'] = $feeWei;
                $update['sponsored_fee_asset'] = SponsorshipCostTracker::feeAssetForNetwork($record->network);
            }

            $record->update($update);

            if ($feeWei !== null) {
                $costTracker->recordSpendUsd($record->network, $feeWei);
            }

            return;
        }

        if ($reportedStatus === 'failed') {
            $record->update([
                'status'        => WalletSendRecord::STATUS_FAILED,
                'tx_hash'       => $txHash,
                'failed_at'     => now(),
                'error_code'    => 'BUNDLER_REVERTED',
                'error_message' => 'UserOperation reverted on-chain. See bundler receipt for details.',
            ]);
        }

        // pending / unknown → leave the row in submitted; we'll poll again next tick.
    }

    /**
     * Total sponsored gas cost in wei for a confirmed userOp.
     *
     * Prefers the bundler receipt's `actualGasCost` (ERC-4337 field — the
     * exact wei the paymaster was charged for this op). Falls back to
     * gasUsed * effectiveGasPrice from the inner tx receipt; when the bundle
     * carries multiple ops that is the whole bundle's cost, i.e. an upper
     * bound — acceptable as a conservative estimate. All hex→dec conversion
     * is bcmath-based: 256-bit values overflow hexdec()/PHP ints.
     *
     * @param array<string, mixed>|null $receipt
     *
     * @return numeric-string|null
     */
    private function resolveSponsoredFeeWei(?array $receipt): ?string
    {
        if ($receipt === null) {
            return null;
        }

        $actualGasCost = $receipt['actualGasCost'] ?? null;

        if (is_string($actualGasCost) && $actualGasCost !== '') {
            $wei = SponsorshipCostTracker::hexToDecimalString($actualGasCost);

            if (bccomp($wei, '0', 0) > 0) {
                return $wei;
            }
        }

        $gasUsed = $receipt['txGasUsed'] ?? null;
        $gasPrice = $receipt['effectiveGasPrice'] ?? null;

        if (is_string($gasUsed) && $gasUsed !== '' && is_string($gasPrice) && $gasPrice !== '') {
            $wei = bcmul(
                SponsorshipCostTracker::hexToDecimalString($gasUsed),
                SponsorshipCostTracker::hexToDecimalString($gasPrice),
                0,
            );

            if (bccomp($wei, '0', 0) > 0) {
                return $wei;
            }
        }

        return null;
    }
}
