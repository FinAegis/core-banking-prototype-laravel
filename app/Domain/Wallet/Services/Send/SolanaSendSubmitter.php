<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services\Send;

use App\Domain\Wallet\Exceptions\InvalidSendStateException;
use App\Domain\Wallet\Exceptions\InvalidSignatureException;
use App\Domain\Wallet\Exceptions\SolanaRpcException;
use App\Domain\Wallet\Models\WalletSendRecord;

/**
 * Step 2 of the non-custodial Solana send flow.
 *
 * Reconstructs the wire-format Solana transaction from a previously prepared
 * unsigned message + a Privy-supplied 64-byte ed25519 signature, then
 * broadcasts via Helius. Updates the record's status (`submitted` on success,
 * `failed` on RPC rejection). Confirmation is observed asynchronously by
 * {@see \App\Domain\Wallet\Services\HeliusTransactionProcessor} via the
 * Helius webhook — this submitter is fire-and-forget for the user.
 */
class SolanaSendSubmitter
{
    public function __construct(
        private readonly HeliusRpcClient $rpc,
        private readonly SolanaTransferBuilder $builder,
    ) {
    }

    /**
     * Broadcast a previously prepared Solana transaction.
     *
     * @param  string $signatureBase64 64-byte ed25519 signature, base64-encoded
     */
    public function submit(WalletSendRecord $record, string $signatureBase64): WalletSendRecord
    {
        // Idempotent re-entry: if the record already advanced past pending
        // we return as-is rather than double-submit.
        if (
            $record->status === WalletSendRecord::STATUS_SUBMITTED
            || $record->status === WalletSendRecord::STATUS_CONFIRMED
            || $record->status === WalletSendRecord::STATUS_FAILED
        ) {
            return $record;
        }

        if ($record->status !== WalletSendRecord::STATUS_PENDING) {
            throw new InvalidSendStateException(
                "Cannot submit wallet_send_record in status '{$record->status}' — expected 'pending'.",
            );
        }

        $signatureBytes = $this->decodeSignature($signatureBase64);
        $messageBytes = $this->decodeStoredMessage($record);

        $wireTransaction = $this->builder->serializeSignedTransaction($messageBytes, $signatureBytes);
        $base64Tx = base64_encode($wireTransaction);

        try {
            $signature = $this->rpc->sendTransaction($base64Tx);
        } catch (SolanaRpcException $e) {
            $record->status = WalletSendRecord::STATUS_FAILED;
            $record->error_code = 'HELIUS_REJECTED';
            $record->error_message = $e->getMessage();
            $record->failed_at = now();
            $record->save();

            return $record;
        }

        $record->status = WalletSendRecord::STATUS_SUBMITTED;
        $record->tx_hash = $signature;
        $record->submitted_at = now();
        $record->save();

        return $record;
    }

    private function decodeSignature(string $signatureBase64): string
    {
        $decoded = base64_decode($signatureBase64, true);
        if ($decoded === false) {
            throw new InvalidSignatureException('Signature is not valid base64.');
        }
        if (strlen($decoded) !== 64) {
            throw new InvalidSignatureException(
                'Solana signature must be exactly 64 bytes; got ' . strlen($decoded) . '.',
            );
        }

        return $decoded;
    }

    private function decodeStoredMessage(WalletSendRecord $record): string
    {
        $metadata = $record->metadata ?? [];
        $messageBase64 = (string) ($metadata['message_bytes_base64'] ?? '');

        if ($messageBase64 === '') {
            throw new InvalidSendStateException(
                'wallet_send_record metadata is missing message_bytes_base64; cannot reconstruct transaction.',
            );
        }

        $decoded = base64_decode($messageBase64, true);
        if ($decoded === false || $decoded === '') {
            throw new InvalidSendStateException(
                'wallet_send_record metadata.message_bytes_base64 is not valid base64.',
            );
        }

        return $decoded;
    }
}
