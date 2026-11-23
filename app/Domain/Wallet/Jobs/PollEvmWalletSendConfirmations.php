<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Jobs;

use App\Domain\Relayer\Contracts\BundlerInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Services\EntryPointNonces;
use App\Domain\Relayer\Services\PimlicoPaymasterService;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Domain\Wallet\Services\SponsorshipCostTracker;
use App\Support\SecretRedactor;
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

    /** Slack on top of the sponsorship expiry for clock differences between this server and the chain. */
    private const int EXPIRY_MARGIN_SECONDS = 300;

    /** Sends with an unknown broadcast outcome are watched this long instead of the usual two hours. */
    private const int UNKNOWN_OUTCOME_WATCH_DAYS = 7;

    public function handle(
        BundlerInterface $bundler,
        SponsorshipCostTracker $costTracker,
        ?EntryPointNonces $nonces = null,
    ): void {
        $records = WalletSendRecord::query()
            ->where('status', WalletSendRecord::STATUS_SUBMITTED)
            ->whereNotNull('user_op_hash')
            ->whereIn('network', ['polygon', 'base', 'arbitrum', 'ethereum'])
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
            $this->reconcile($bundler, $costTracker, $record, $nonces);
        }
    }

    private function reconcile(
        BundlerInterface $bundler,
        SponsorshipCostTracker $costTracker,
        WalletSendRecord $record,
        ?EntryPointNonces $nonces,
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
                'error'        => SecretRedactor::message($e),
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

        if ($reportedStatus === 'pending') {
            $this->settleUnknownSubmit($record, $nonces);
        }

        // pending / unknown → leave the row in submitted; we'll poll again next tick.
    }

    /**
     * A send whose broadcast outcome was unknown (see EvmUserOpSubmitter) is
     * failed only when it provably can no longer land: its paymaster
     * sponsorship has expired, so the EntryPoint would refuse it, and the
     * account's on-chain nonce shows that it never ran. Anything less certain
     * keeps waiting. The bundler's own lookups are no proof here: they search
     * mined logs only, over a limited block range.
     */
    private function settleUnknownSubmit(WalletSendRecord $record, ?EntryPointNonces $nonces): void
    {
        $metadata = $record->metadata ?? [];
        if (($metadata['submit_outcome'] ?? null) !== WalletSendRecord::SUBMIT_OUTCOME_UNKNOWN || isset($metadata['submit_review'])) {
            return;
        }

        /** @var array<string, mixed> $userOp */
        $userOp = is_array($metadata['user_op'] ?? null) ? $metadata['user_op'] : [];

        $deadline = $this->sponsorshipDeadline($record, $userOp);
        if ($deadline === null || now()->getTimestamp() <= $deadline + self::EXPIRY_MARGIN_SECONDS) {
            return;
        }

        $network = SupportedNetwork::tryFrom((string) $record->network);
        $sender = is_string($userOp['sender'] ?? null) ? $userOp['sender'] : '';
        $nonceHex = is_string($userOp['nonce'] ?? null) ? $userOp['nonce'] : '';
        $opNonce = preg_match('/^0x0*([0-9a-fA-F]{1,15})$/', $nonceHex, $digits) === 1 ? (int) hexdec($digits[1]) : null;
        if ($network === null || $sender === '' || $opNonce === null) {
            return;
        }

        try {
            $nextNonce = ($nonces ?? app(EntryPointNonces::class))->sequence($sender, $network);
        } catch (Throwable $e) {
            Log::warning('PollEvmWalletSendConfirmations: could not read the account nonce', [
                'record_id' => $record->id,
                'network'   => $record->network,
                'error'     => SecretRedactor::message($e),
            ]);

            return;
        }

        if ($nextNonce > $opNonce) {
            // The nonce was used, by this operation (whose receipt the bundler
            // could not find) or by another one. Only a person can tell which.
            $metadata['submit_review'] = 'nonce_used_without_receipt';
            $record->update(['metadata' => $metadata]);

            Log::error('PollEvmWalletSendConfirmations: uncertain send needs review, its nonce was used but no receipt was found', [
                'record_id'    => $record->id,
                'user_op_hash' => $record->user_op_hash,
                'network'      => $record->network,
                'op_nonce'     => $opNonce,
                'next_nonce'   => $nextNonce,
            ]);

            return;
        }

        $record->update([
            'status'        => WalletSendRecord::STATUS_FAILED,
            'failed_at'     => now(),
            'error_code'    => 'BUNDLER_NOT_ACCEPTED',
            'error_message' => 'This transfer did not reach the network. No funds were moved.',
        ]);
    }

    /**
     * Unix time after which the operation's sponsorship can no longer be used,
     * or null when it never expires (no paymaster, or validUntil 0). The
     * sponsorship is requested at prepare, so the record's creation time plus
     * Pimlico's default validity is never earlier than the real expiry; a
     * longer validity written in the paymaster data wins.
     *
     * @param array<string, mixed> $userOp
     */
    private function sponsorshipDeadline(WalletSendRecord $record, array $userOp): ?int
    {
        $paymasterAndData = is_string($userOp['paymasterAndData'] ?? null) ? $userOp['paymasterAndData'] : '';
        if ($paymasterAndData === '' || $paymasterAndData === '0x') {
            return null;
        }

        $written = PimlicoPaymasterService::sponsorshipValidUntil($paymasterAndData);
        if ($written === PHP_INT_MAX) {
            return null;
        }

        $default = $record->created_at->getTimestamp() + PimlicoPaymasterService::SPONSORSHIP_VALID_SECONDS;

        return max($default, $written ?? $default);
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
