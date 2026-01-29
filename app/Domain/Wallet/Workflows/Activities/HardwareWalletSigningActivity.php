<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Workflows\Activities;

use App\Domain\Wallet\Models\HardwareWalletAssociation;
use App\Domain\Wallet\Models\PendingSigningRequest;
use App\Domain\Wallet\Services\HardwareWallet\HardwareWalletManager;
use App\Domain\Wallet\ValueObjects\PendingSigningRequest as PendingSigningRequestVO;
use App\Domain\Wallet\ValueObjects\TransactionData;
use DB;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Hardware Wallet Signing Activity.
 *
 * Workflow activity for hardware wallet signing operations.
 * Handles creating signing requests and waiting for signatures from hardware devices.
 */
class HardwareWalletSigningActivity
{
    public function __construct(
        private readonly HardwareWalletManager $hardwareWalletManager
    ) {
    }

    /**
     * Check if a wallet uses hardware signing.
     */
    public function isHardwareWallet(string $walletId): bool
    {
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $walletId)
            ->first();

        if (! $wallet) {
            return false;
        }

        $signerType = $wallet->signer_type ?? 'internal';

        return in_array($signerType, ['hardware_ledger', 'hardware_trezor'], true);
    }

    /**
     * Get hardware wallet association for a wallet.
     */
    public function getHardwareWalletAssociation(string $walletId, string $chain): ?HardwareWalletAssociation
    {
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $walletId)
            ->first();

        if (! $wallet) {
            return null;
        }

        // Find association by address and chain
        return HardwareWalletAssociation::where('address', $wallet->address)
            ->where('chain', $chain)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Create a signing request for a hardware wallet transaction.
     *
     * @return array{request_id: string, raw_data_to_sign: string, expires_at: string}
     */
    public function createSigningRequest(
        string $associationId,
        string $from,
        string $to,
        string $value,
        string $chain,
        ?string $data = null,
        ?string $gasLimit = null,
        ?string $gasPrice = null,
        ?int $nonce = null
    ): array {
        $association = HardwareWalletAssociation::find($associationId);
        if (! $association) {
            throw new Exception('Hardware wallet association not found');
        }

        $transaction = new TransactionData(
            from: $from,
            to: $to,
            value: $value,
            chain: $chain,
            data: $data,
            gasLimit: $gasLimit,
            gasPrice: $gasPrice,
            nonce: $nonce
        );

        $signingRequest = $this->hardwareWalletManager->createSigningRequest($association, $transaction);

        return [
            'request_id'       => $signingRequest->id,
            'raw_data_to_sign' => $signingRequest->raw_data_to_sign,
            'expires_at'       => $signingRequest->expires_at->toIso8601String(),
        ];
    }

    /**
     * Wait for a signing request to be completed.
     *
     * This method polls the database for the signing request status.
     * In a production system, this would use webhooks or push notifications.
     *
     * @return array{signature: string, public_key: string, transaction_hash: string}
     */
    public function waitForSigning(string $requestId, int $maxWaitSeconds = 300): array
    {
        $pollIntervalMs = (int) config('blockchain.hardware_wallets.signing_request.poll_interval_ms', 1000);
        $pollIntervalSeconds = max(1, (int) ($pollIntervalMs / 1000));
        $startTime = time();

        Log::info('Waiting for hardware wallet signing', [
            'request_id'       => $requestId,
            'max_wait_seconds' => $maxWaitSeconds,
        ]);

        while ((time() - $startTime) < $maxWaitSeconds) {
            $request = PendingSigningRequest::find($requestId);

            if (! $request) {
                throw new Exception('Signing request not found');
            }

            // Check if completed
            if ($request->status === PendingSigningRequestVO::STATUS_COMPLETED) {
                Log::info('Hardware wallet signing completed', [
                    'request_id'       => $requestId,
                    'transaction_hash' => $request->signed_transaction_hash,
                ]);

                return [
                    'signature'        => $request->signature ?? '',
                    'public_key'       => $request->public_key ?? '',
                    'transaction_hash' => $request->signed_transaction_hash ?? '',
                ];
            }

            // Check if failed
            if ($request->status === PendingSigningRequestVO::STATUS_FAILED) {
                throw new Exception('Signing failed: ' . ($request->error_message ?? 'Unknown error'));
            }

            // Check if expired
            if ($request->status === PendingSigningRequestVO::STATUS_EXPIRED || $request->isExpired()) {
                throw new Exception('Signing request expired');
            }

            // Check if cancelled
            if ($request->status === PendingSigningRequestVO::STATUS_CANCELLED) {
                throw new Exception('Signing request was cancelled');
            }

            // Wait before next poll
            sleep($pollIntervalSeconds);
        }

        // Timeout reached - mark request as expired
        $request = PendingSigningRequest::find($requestId);
        if ($request && $request->canBeProcessed()) {
            $request->markAsExpired();
        }

        throw new Exception('Signing request timed out waiting for device signature');
    }

    /**
     * Get signing request status.
     *
     * @return array{status: string, is_completed: bool, is_expired: bool, signature: ?string, error: ?string}
     */
    public function getSigningRequestStatus(string $requestId): array
    {
        $request = PendingSigningRequest::find($requestId);

        if (! $request) {
            return [
                'status'       => 'not_found',
                'is_completed' => false,
                'is_expired'   => false,
                'signature'    => null,
                'error'        => 'Request not found',
            ];
        }

        return [
            'status'       => $request->status,
            'is_completed' => $request->isCompleted(),
            'is_expired'   => $request->isExpired(),
            'signature'    => $request->signature,
            'error'        => $request->error_message,
        ];
    }

    /**
     * Cancel a pending signing request.
     */
    public function cancelSigningRequest(string $requestId): void
    {
        $request = PendingSigningRequest::find($requestId);

        if ($request && $request->canBeProcessed()) {
            $this->hardwareWalletManager->cancelSigningRequest($request);
        }
    }

    /**
     * Prepare transaction data for hardware wallet signing.
     * This is used instead of the regular prepareTransaction when hardware wallet is detected.
     *
     * @return array{association_id: string, transaction: array<string, mixed>}
     */
    public function prepareHardwareWalletTransaction(
        string $walletId,
        string $chain,
        string $toAddress,
        string $cryptoAmount,
        string $asset,
        ?string $tokenAddress
    ): array {
        $wallet = DB::table('blockchain_wallets')
            ->where('wallet_id', $walletId)
            ->first();

        if (! $wallet) {
            throw new Exception('Wallet not found');
        }

        $association = $this->getHardwareWalletAssociation($walletId, $chain);
        if (! $association) {
            throw new Exception('Hardware wallet association not found for this wallet');
        }

        // Estimate gas
        $gasPrice = $this->estimateGasPrice($chain);
        $gasLimit = $tokenAddress ? '100000' : '21000';

        // Get nonce
        $nonce = $this->getNextNonce($wallet->address, $chain);

        return [
            'association_id' => $association->id,
            'transaction'    => [
                'from'      => $wallet->address,
                'to'        => $toAddress,
                'value'     => $cryptoAmount,
                'chain'     => $chain,
                'gas_limit' => $gasLimit,
                'gas_price' => $gasPrice,
                'nonce'     => $nonce,
                'data'      => $tokenAddress ? $this->encodeTokenTransfer($toAddress, $cryptoAmount) : null,
            ],
        ];
    }

    /**
     * Estimate gas price for a chain.
     */
    private function estimateGasPrice(string $chain): string
    {
        return match ($chain) {
            'ethereum' => '50000000000', // 50 Gwei
            'polygon'  => '30000000000', // 30 Gwei
            'bsc'      => '5000000000', // 5 Gwei
            default    => '1000000000', // 1 Gwei
        };
    }

    /**
     * Get next nonce for an address.
     */
    private function getNextNonce(string $address, string $chain): int
    {
        // In production, query the blockchain node
        // For now, check local pending transactions
        $pendingCount = DB::table('blockchain_transactions')
            ->where('from_address', $address)
            ->where('chain', $chain)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        return $pendingCount;
    }

    /**
     * Encode ERC20 token transfer data.
     */
    private function encodeTokenTransfer(string $to, string $amount): string
    {
        // ERC20 transfer function signature: transfer(address,uint256)
        $functionSignature = 'a9059cbb';
        $paddedTo = str_pad(ltrim($to, '0x'), 64, '0', STR_PAD_LEFT);
        $paddedAmount = str_pad(dechex((int) $amount), 64, '0', STR_PAD_LEFT);

        return '0x' . $functionSignature . $paddedTo . $paddedAmount;
    }
}
