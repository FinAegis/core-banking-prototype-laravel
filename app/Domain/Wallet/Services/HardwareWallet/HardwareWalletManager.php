<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services\HardwareWallet;

use App\Domain\Wallet\Contracts\ExternalSignerInterface;
use App\Domain\Wallet\Events\HardwareWalletConnected;
use App\Domain\Wallet\Events\HardwareWalletSigningCompleted;
use App\Domain\Wallet\Events\HardwareWalletSigningRequested;
use App\Domain\Wallet\Models\HardwareWalletAssociation;
use App\Domain\Wallet\Models\PendingSigningRequest;
use App\Domain\Wallet\ValueObjects\HardwareWalletDevice;
use App\Domain\Wallet\ValueObjects\PendingSigningRequest as PendingSigningRequestVO;
use App\Domain\Wallet\ValueObjects\SignedTransaction;
use App\Domain\Wallet\ValueObjects\TransactionData;
use Exception;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Hardware Wallet Manager Service.
 *
 * Coordinates hardware wallet operations including device registration,
 * signing request lifecycle management, and signature validation.
 *
 * SECURITY NOTICE: This implementation is designed for educational/prototype use.
 * For production deployment, the following should be implemented:
 * - Proper ECDSA signature verification using a cryptography library
 * - Hardware security module (HSM) integration for key operations
 * - Formal security audit of all cryptographic operations
 * - Rate limiting at multiple layers (endpoint, user, device, IP)
 *
 * @see https://github.com/FinAegis/core-banking-prototype-laravel/docs/security
 */
class HardwareWalletManager
{
    /**
     * @var array<string, ExternalSignerInterface>
     */
    private array $signers;

    public function __construct(
        private readonly LedgerSignerService $ledgerSigner,
        private readonly TrezorSignerService $trezorSigner
    ) {
        $this->signers = [
            'hardware_ledger' => $this->ledgerSigner,
            'hardware_trezor' => $this->trezorSigner,
        ];
    }

    /**
     * Register a hardware wallet device for a user.
     */
    public function registerDevice(
        int $userId,
        HardwareWalletDevice $device,
        string $chain,
        string $derivationPath
    ): HardwareWalletAssociation {
        $this->validateDeviceForChain($device, $chain);

        // Check max associations limit
        $existingCount = HardwareWalletAssociation::where('user_id', $userId)
            ->where('is_active', true)
            ->count();

        $maxAssociations = (int) config('blockchain.hardware_wallets.security.max_associations_per_user', 10);
        if ($existingCount >= $maxAssociations) {
            throw new InvalidArgumentException("Maximum device limit ({$maxAssociations}) reached");
        }

        // Create association
        $association = new HardwareWalletAssociation([
            'user_id'          => $userId,
            'device_type'      => $device->type,
            'device_id'        => $device->deviceId,
            'device_label'     => $device->label,
            'firmware_version' => $device->firmwareVersion,
            'public_key'       => $device->publicKey ?? '',
            'address'          => $device->address,
            'chain'            => $chain,
            'derivation_path'  => $derivationPath,
            'supported_chains' => $device->supportedChains,
            'metadata'         => $device->metadata,
            'is_active'        => true,
            'is_verified'      => false,
        ]);

        $association->save();

        // Record event
        event(new HardwareWalletConnected(
            associationId: $association->id,
            userId: (string) $userId,
            deviceType: $device->type,
            deviceId: $device->deviceId,
            publicKey: $device->publicKey ?? '',
            address: $device->address ?? '',
            chain: $chain,
            derivationPath: $derivationPath,
            supportedChains: $device->supportedChains,
            deviceLabel: $device->label,
            firmwareVersion: $device->firmwareVersion,
            metadata: $device->metadata
        ));

        return $association;
    }

    /**
     * Create a signing request for a hardware wallet transaction.
     *
     * Uses database transaction with locking to prevent race conditions.
     */
    public function createSigningRequest(
        HardwareWalletAssociation $association,
        TransactionData $transaction
    ): PendingSigningRequest {
        return DB::transaction(function () use ($association, $transaction) {
            // Check pending request limit with row locking to prevent race conditions
            $pendingCount = PendingSigningRequest::where('user_id', $association->user_id)
                ->whereIn('status', [
                    PendingSigningRequestVO::STATUS_PENDING,
                    PendingSigningRequestVO::STATUS_AWAITING_DEVICE,
                ])
                ->lockForUpdate()
                ->count();

            $maxPending = (int) config('blockchain.hardware_wallets.security.max_pending_requests', 5);
            if ($pendingCount >= $maxPending) {
                throw new InvalidArgumentException("Maximum pending requests ({$maxPending}) reached");
            }

            // Get signer for device type
            $signer = $this->getSignerForDevice($association);

            // Prepare transaction for signing
            $preparedData = $signer->prepareForSigning($transaction);

            // Get TTL from config
            $ttlSeconds = (int) config('blockchain.hardware_wallets.signing_request.ttl_seconds', 300);

            // Create signing request
            $request = new PendingSigningRequest([
                'user_id'          => $association->user_id,
                'association_id'   => $association->id,
                'status'           => PendingSigningRequestVO::STATUS_PENDING,
                'transaction_data' => $transaction->toArray(),
                'raw_data_to_sign' => $preparedData['raw_data'],
                'chain'            => $transaction->chain,
                'metadata'         => [
                    'encoding'     => $preparedData['encoding'],
                    'display_data' => $preparedData['display_data'],
                    'device_type'  => $association->device_type,
                ],
                'expires_at' => now()->addSeconds($ttlSeconds),
            ]);

            $request->save();

            // Record event
            event(new HardwareWalletSigningRequested(
                requestId: $request->id,
                associationId: $association->id,
                userId: (string) $association->user_id,
                chain: $transaction->chain,
                transactionData: $transaction->toArray(),
                rawDataToSign: $preparedData['raw_data'],
                expiresAt: $request->expires_at->toIso8601String(),
                metadata: $request->metadata ?? []
            ));

            return $request;
        });
    }

    /**
     * Submit a signature for a pending signing request.
     */
    public function submitSignature(
        PendingSigningRequest $request,
        string $signature,
        string $publicKey
    ): SignedTransaction {
        // Validate request can be processed
        if (! $request->canBeProcessed()) {
            $reason = $request->isExpired() ? 'expired' : 'invalid status';
            throw new InvalidArgumentException("Signing request cannot be processed: {$reason}");
        }

        // Mark as signing
        $request->markAsSigning();

        try {
            // Get association
            $association = $request->association;
            if (! $association) {
                throw new InvalidArgumentException('Hardware wallet association not found');
            }

            // Get signer
            $signer = $this->getSignerForDevice($association);

            // Get transaction data
            $transactionData = $request->getTransactionDataVO();

            // Validate signature
            if (! $signer->validateSignature($transactionData, $signature, $publicKey)) {
                $request->markAsFailed('Signature validation failed');
                throw new InvalidArgumentException('Invalid signature');
            }

            // Construct signed transaction
            $signedTransaction = $signer->constructSignedTransaction(
                $transactionData,
                $signature,
                $publicKey
            );

            // Mark request as completed
            $request->markAsCompleted($signature, $publicKey, $signedTransaction->hash);

            // Update association last used timestamp
            $association->touchLastUsed();

            // Record event
            event(new HardwareWalletSigningCompleted(
                requestId: $request->id,
                associationId: $association->id,
                userId: (string) $association->user_id,
                signature: $signature,
                publicKey: $publicKey,
                transactionHash: $signedTransaction->hash,
                chain: $transactionData->chain,
                success: true
            ));

            return $signedTransaction;
        } catch (Exception $e) {
            $request->markAsFailed($e->getMessage());

            $association = $request->association;
            if ($association) {
                event(new HardwareWalletSigningCompleted(
                    requestId: $request->id,
                    associationId: $association->id,
                    userId: (string) $association->user_id,
                    signature: '',
                    publicKey: '',
                    transactionHash: '',
                    chain: $request->chain,
                    success: false,
                    errorMessage: $e->getMessage()
                ));
            }

            throw $e;
        }
    }

    /**
     * Cancel a pending signing request.
     *
     * Silently does nothing for completed or failed requests.
     */
    public function cancelSigningRequest(PendingSigningRequest $request): void
    {
        if ($request->isCompleted() || $request->isFailed()) {
            return; // Silently ignore - cannot cancel completed/failed requests
        }

        $request->markAsCancelled();
    }

    /**
     * Get a user's hardware wallet associations.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, HardwareWalletAssociation>
     */
    public function getUserAssociations(int $userId, ?string $chain = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = HardwareWalletAssociation::where('user_id', $userId)
            ->where('is_active', true);

        if ($chain !== null) {
            $query->where('chain', $chain);
        }

        return $query->orderBy('last_used_at', 'desc')->get();
    }

    /**
     * Find an association by address and chain.
     */
    public function findAssociationByAddress(string $address, string $chain): ?HardwareWalletAssociation
    {
        return HardwareWalletAssociation::where('address', $address)
            ->where('chain', $chain)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Verify a hardware wallet device.
     */
    public function verifyDevice(HardwareWalletAssociation $association): void
    {
        // In a real implementation, this would involve:
        // 1. Sending a challenge to the device
        // 2. Having the user sign it
        // 3. Verifying the signature matches the stored public key

        $association->markAsVerified();
    }

    /**
     * Remove a hardware wallet association.
     */
    public function removeAssociation(HardwareWalletAssociation $association): void
    {
        // Cancel any pending signing requests
        PendingSigningRequest::where('association_id', $association->id)
            ->whereIn('status', [
                PendingSigningRequestVO::STATUS_PENDING,
                PendingSigningRequestVO::STATUS_AWAITING_DEVICE,
            ])
            ->each(fn (PendingSigningRequest $req) => $req->markAsCancelled());

        // Soft delete the association
        $association->deactivate();
    }

    /**
     * Expire old signing requests.
     */
    public function expireOldRequests(): int
    {
        $expired = PendingSigningRequest::where('expires_at', '<=', now())
            ->whereIn('status', [
                PendingSigningRequestVO::STATUS_PENDING,
                PendingSigningRequestVO::STATUS_AWAITING_DEVICE,
            ])
            ->get();

        foreach ($expired as $request) {
            $request->markAsExpired();
        }

        return $expired->count();
    }

    /**
     * Get supported chains for a device type.
     *
     * @return array<string>
     */
    public function getSupportedChains(string $deviceType): array
    {
        try {
            $signerType = $this->mapDeviceTypeToSignerType($deviceType);
        } catch (InvalidArgumentException) {
            return [];
        }

        if (! isset($this->signers[$signerType])) {
            return [];
        }

        return $this->signers[$signerType]->getSupportedChains();
    }

    /**
     * Get signer for a device.
     */
    private function getSignerForDevice(HardwareWalletAssociation $association): ExternalSignerInterface
    {
        $signerType = $this->mapDeviceTypeToSignerType($association->device_type);

        if (! isset($this->signers[$signerType])) {
            throw new InvalidArgumentException("No signer available for device type: {$association->device_type}");
        }

        return $this->signers[$signerType];
    }

    /**
     * Map device type to signer type.
     */
    private function mapDeviceTypeToSignerType(string $deviceType): string
    {
        return match ($deviceType) {
            HardwareWalletDevice::TYPE_LEDGER_NANO_S,
            HardwareWalletDevice::TYPE_LEDGER_NANO_X => 'hardware_ledger',
            HardwareWalletDevice::TYPE_TREZOR_ONE,
            HardwareWalletDevice::TYPE_TREZOR_MODEL_T => 'hardware_trezor',
            default                                   => throw new InvalidArgumentException("Unknown device type: {$deviceType}")
        };
    }

    /**
     * Validate device supports the specified chain.
     */
    private function validateDeviceForChain(HardwareWalletDevice $device, string $chain): void
    {
        if (! $device->supportsChain($chain)) {
            throw new InvalidArgumentException(
                "Device {$device->type} does not support chain: {$chain}"
            );
        }
    }
}
