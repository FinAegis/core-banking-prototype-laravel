<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services\Send;

use App\Domain\Relayer\Contracts\BundlerInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\ValueObjects\UserOperation;
use App\Domain\Wallet\Exceptions\InvalidSendStateException;
use App\Domain\Wallet\Exceptions\InvalidSignatureException;
use App\Domain\Wallet\Models\WalletSendRecord;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Step 2 of the non-custodial EVM (ERC-4337) send flow.
 *
 * Reads a previously-prepared UserOperation from the {@see WalletSendRecord}
 * metadata, attaches the client-supplied signature blob (Privy's smart-wallet
 * signer produces a WebAuthn-formatted blob the smart account contract
 * verifies natively — we don't validate its shape), and submits to the
 * Pimlico bundler. The record advances to `submitted`. Confirmation flips
 * (`submitted` → `confirmed` | `failed`) is owned by
 * {@see \App\Domain\Wallet\Jobs\PollEvmWalletSendConfirmations}.
 */
class EvmUserOpSubmitter
{
    public function __construct(
        private readonly BundlerInterface $bundler,
    ) {
    }

    /**
     * Attach the signature and broadcast the UserOperation.
     *
     * @param  string  $signature  0x-prefixed hex signature blob from the client.
     */
    public function submit(WalletSendRecord $record, string $signature): WalletSendRecord
    {
        // Idempotent re-entry: anything past pending is returned as-is.
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

        $this->assertSignatureFormat($signature);

        $metadata = $record->metadata ?? [];
        if (! is_array($metadata['user_op'] ?? null) || $metadata['user_op'] === []) {
            throw new InvalidSendStateException(
                'wallet_send_record metadata is missing user_op; cannot reconstruct UserOperation.',
            );
        }

        $network = $this->resolveNetwork((string) $record->network);
        $userOp = $this->hydrateUserOp($metadata['user_op'], $signature);

        try {
            $bundlerHash = $this->bundler->submitUserOperation($userOp, $network);
        } catch (Throwable $e) {
            $record->status = WalletSendRecord::STATUS_FAILED;
            $record->error_code = 'BUNDLER_REJECTED';
            $record->error_message = $e->getMessage();
            $record->failed_at = now();
            $record->save();

            return $record;
        }

        // Sanity check the bundler-returned hash matches our locally computed one.
        // Mismatch is a soft warning — bundler is canonical for confirmation
        // lookup, so we don't fail the request, but we record both for
        // postmortem.
        $expected = (string) ($record->user_op_hash ?? '');
        if ($expected !== '' && strtolower($bundlerHash) !== strtolower($expected)) {
            Log::warning('EvmUserOpSubmitter: bundler-reported userOpHash mismatch', [
                'record_id'    => $record->id,
                'expected'     => $expected,
                'bundler_hash' => $bundlerHash,
                'network'      => $record->network,
            ]);

            // Update metadata with the bundler-reported hash so the polling
            // job (which queries by user_op_hash) finds the receipt.
            $newMetadata = $metadata;
            $newMetadata['expected_user_op_hash'] = $expected;
            $newMetadata['bundler_user_op_hash'] = $bundlerHash;
            $record->metadata = $newMetadata;
            $record->user_op_hash = $bundlerHash;
        }

        $record->status = WalletSendRecord::STATUS_SUBMITTED;
        $record->submitted_at = now();
        $record->save();

        return $record;
    }

    private function assertSignatureFormat(string $signature): void
    {
        if (! str_starts_with($signature, '0x') && ! str_starts_with($signature, '0X')) {
            throw new InvalidSignatureException(
                'EVM UserOperation signature must be 0x-prefixed hex.',
            );
        }

        $hex = substr($signature, 2);
        if ($hex !== '' && preg_match('/^[0-9a-fA-F]+$/', $hex) !== 1) {
            throw new InvalidSignatureException(
                'EVM UserOperation signature is not valid hex.',
            );
        }
    }

    private function resolveNetwork(string $networkKey): SupportedNetwork
    {
        $network = SupportedNetwork::tryFrom(strtolower($networkKey));

        if ($network === null) {
            throw new InvalidSendStateException(
                "wallet_send_record.network '{$networkKey}' is not a SupportedNetwork enum case.",
            );
        }

        return $network;
    }

    /**
     * Reconstruct a UserOperation VO from the stored hex-encoded shape and
     * attach the supplied signature.
     *
     * @param  array<string, mixed>  $userOpArray
     */
    private function hydrateUserOp(array $userOpArray, string $signature): UserOperation
    {
        return new UserOperation(
            sender: (string) ($userOpArray['sender'] ?? ''),
            nonce: self::hexOrIntToInt($userOpArray['nonce'] ?? 0),
            initCode: (string) ($userOpArray['initCode'] ?? '0x'),
            callData: (string) ($userOpArray['callData'] ?? '0x'),
            callGasLimit: self::hexOrIntToInt($userOpArray['callGasLimit'] ?? 0),
            verificationGasLimit: self::hexOrIntToInt($userOpArray['verificationGasLimit'] ?? 0),
            preVerificationGas: self::hexOrIntToInt($userOpArray['preVerificationGas'] ?? 0),
            maxFeePerGas: self::hexOrIntToInt($userOpArray['maxFeePerGas'] ?? 0),
            maxPriorityFeePerGas: self::hexOrIntToInt($userOpArray['maxPriorityFeePerGas'] ?? 0),
            paymasterAndData: (string) ($userOpArray['paymasterAndData'] ?? '0x'),
            signature: $signature,
        );
    }

    private static function hexOrIntToInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            if ($value === '' || $value === '0x') {
                return 0;
            }

            if (str_starts_with($value, '0x') || str_starts_with($value, '0X')) {
                return (int) hexdec(substr($value, 2));
            }

            if (preg_match('/^\d+$/', $value) === 1) {
                return (int) $value;
            }
        }

        return 0;
    }
}
