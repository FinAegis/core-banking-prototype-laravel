<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services\Send;

use App\Domain\Wallet\Exceptions\InvalidSendStateException;
use App\Domain\Wallet\Exceptions\InvalidSignatureException;
use App\Domain\Wallet\Exceptions\SolanaRpcException;
use App\Domain\Wallet\Helpers\Crypto\Base58;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Support\SecretRedactor;
use Illuminate\Support\Facades\Log;

/**
 * Step 2 of the non-custodial Solana send flow.
 *
 * Reconstructs the wire-format Solana transaction from a previously prepared
 * unsigned message + a Privy-supplied 64-byte ed25519 signature, then
 * broadcasts via Helius. Updates the record's status (`submitted` on success,
 * `failed` on RPC rejection). Confirmation is observed asynchronously by
 * {@see \App\Domain\Wallet\Services\HeliusTransactionProcessor} via the
 * Helius webhook — this submitter is fire-and-forget for the user.
 *
 * For a sponsored send (record metadata `sponsored = true`) the platform
 * fee-payer signature is produced server-side here and prepended to the
 * device signature, since the fee payer is account index 0.
 */
class SolanaSendSubmitter
{
    public function __construct(
        private readonly HeliusRpcClient $rpc,
        private readonly SolanaTransferBuilder $builder,
        private readonly SolanaSponsorSigner $sponsor,
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

        $deviceSignature = $this->decodeSignature($signatureBase64);
        $messageBytes = $this->decodeStoredMessage($record);

        $orderedSignatures = $this->buildOrderedSignatures($record, $messageBytes, $deviceSignature);

        $wireTransaction = $this->builder->serializeWithSignatures($messageBytes, $orderedSignatures);
        $base64Tx = base64_encode($wireTransaction);

        try {
            $signature = $this->rpc->sendTransaction($base64Tx);
        } catch (SolanaRpcException $e) {
            if ($e->isOutcomeUnknown()) {
                return $this->keepForConfirmation($record, Base58::encode($orderedSignatures[0]), $e);
            }

            $record->status = WalletSendRecord::STATUS_FAILED;
            $record->error_code = 'HELIUS_REJECTED';
            $record->error_message = SecretRedactor::message($e);
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

    /**
     * The node may have the transaction although we saw an error. Calling it
     * failed could lead the user to send again and pay twice. A Solana
     * transaction's id is its first signature, known before broadcast, so the
     * send stays submitted under it and the confirmation poller settles it:
     * confirmed, failed, or dropped once its blockhash expires.
     */
    private function keepForConfirmation(WalletSendRecord $record, string $transactionId, SolanaRpcException $e): WalletSendRecord
    {
        $metadata = $record->metadata ?? [];
        $metadata['submit_outcome'] = WalletSendRecord::SUBMIT_OUTCOME_UNKNOWN;
        $metadata['submit_error'] = SecretRedactor::message($e);

        $record->metadata = $metadata;
        $record->status = WalletSendRecord::STATUS_SUBMITTED;
        $record->tx_hash = $transactionId;
        $record->submitted_at = now();
        $record->save();

        Log::warning('SolanaSendSubmitter: broadcast outcome unknown, left for the confirmation poller', [
            'record_id' => $record->id,
            'signature' => $transactionId,
        ]);

        return $record;
    }

    /**
     * Assemble the signature list in account-key order.
     *
     * Solana verifies signature[i] against account-key[i]. For a sponsored
     * send the fee payer is account index 0, so the sponsor signature comes
     * first, followed by the device (sender) signature. A legacy send carries
     * only the device signature.
     *
     * @return array<int, string>
     */
    private function buildOrderedSignatures(
        WalletSendRecord $record,
        string $messageBytes,
        string $deviceSignature,
    ): array {
        $metadata = $record->metadata ?? [];
        $sponsored = (bool) ($metadata['sponsored'] ?? false);

        if (! $sponsored) {
            return [$deviceSignature];
        }

        if (! $this->sponsor->isEnabled()) {
            throw new InvalidSendStateException(
                'wallet_send_record was prepared as a sponsored send but the Solana '
                . 'sponsor key is no longer configured; cannot produce the fee-payer signature.',
            );
        }

        // Account order: [fee payer (sponsor), sender] — sponsor signature first.
        return [$this->sponsor->sign($messageBytes), $deviceSignature];
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
