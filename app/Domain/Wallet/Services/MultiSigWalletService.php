<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Events\MultiSigWalletCreated;
use App\Domain\Wallet\Models\HardwareWalletAssociation;
use App\Domain\Wallet\Models\MultiSigWallet;
use App\Domain\Wallet\Models\MultiSigWalletSigner;
use App\Domain\Wallet\ValueObjects\MultiSigConfiguration;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Service for managing multi-signature wallets.
 */
class MultiSigWalletService
{
    /**
     * Create a new multi-signature wallet.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function createWallet(
        User $owner,
        MultiSigConfiguration $config,
        array $metadata = [],
    ): MultiSigWallet {
        if (! $this->isMultiSigEnabled()) {
            throw new RuntimeException('Multi-signature wallets are not enabled');
        }

        if (! $this->isSupportedChain($config->chain)) {
            throw new InvalidArgumentException("Unsupported chain: {$config->chain}");
        }

        return DB::transaction(function () use ($owner, $config, $metadata) {
            $wallet = MultiSigWallet::create([
                'user_id'             => $owner->id,
                'name'                => $config->name,
                'chain'               => $config->chain,
                'required_signatures' => $config->requiredSignatures,
                'total_signers'       => $config->totalSigners,
                'status'              => MultiSigWallet::STATUS_AWAITING_SIGNERS,
                'metadata'            => $metadata,
            ]);

            event(new MultiSigWalletCreated(
                walletId: $wallet->id,
                userId: $owner->id,
                name: $config->name,
                chain: $config->chain,
                requiredSignatures: $config->requiredSignatures,
                totalSigners: $config->totalSigners,
                metadata: $metadata,
            ));

            return $wallet;
        });
    }

    /**
     * Add a signer to a multi-sig wallet.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function addSigner(
        MultiSigWallet $wallet,
        string $signerType,
        string $publicKey,
        ?string $address = null,
        ?User $user = null,
        ?HardwareWalletAssociation $hardwareWallet = null,
        ?string $label = null,
        array $metadata = [],
    ): MultiSigWalletSigner {
        $this->validateAddSigner($wallet, $signerType, $hardwareWallet);

        $signerOrder = $wallet->signers()->count() + 1;

        return DB::transaction(function () use (
            $wallet,
            $signerType,
            $publicKey,
            $address,
            $user,
            $hardwareWallet,
            $label,
            $signerOrder,
            $metadata
        ) {
            $signer = MultiSigWalletSigner::create([
                'multi_sig_wallet_id'            => $wallet->id,
                'user_id'                        => $user?->id,
                'hardware_wallet_association_id' => $hardwareWallet?->id,
                'signer_type'                    => $signerType,
                'public_key'                     => $publicKey,
                'address'                        => $address ?? $hardwareWallet?->address,
                'label'                          => $label,
                'signer_order'                   => $signerOrder,
                'is_active'                      => true,
                'metadata'                       => $metadata,
            ]);

            // Check if wallet is now fully set up
            if ($wallet->refresh()->isFullySetUp()) {
                $this->generateWalletAddress($wallet);
                $wallet->activate();
            }

            return $signer;
        });
    }

    /**
     * Add a hardware wallet signer.
     */
    public function addHardwareWalletSigner(
        MultiSigWallet $wallet,
        HardwareWalletAssociation $association,
        ?string $label = null,
    ): MultiSigWalletSigner {
        // Verify the hardware wallet is on the same chain
        if ($association->chain !== $wallet->chain) {
            throw new InvalidArgumentException(
                "Hardware wallet chain ({$association->chain}) does not match wallet chain ({$wallet->chain})"
            );
        }

        $signerType = $association->isLedger()
            ? MultiSigWalletSigner::TYPE_HARDWARE_LEDGER
            : MultiSigWalletSigner::TYPE_HARDWARE_TREZOR;

        return $this->addSigner(
            wallet: $wallet,
            signerType: $signerType,
            publicKey: $association->public_key,
            address: $association->address,
            user: $association->user,
            hardwareWallet: $association,
            label: $label ?? $association->device_label,
        );
    }

    /**
     * Remove a signer from a wallet (deactivate).
     */
    public function removeSigner(MultiSigWallet $wallet, MultiSigWalletSigner $signer): void
    {
        if ($signer->multi_sig_wallet_id !== $wallet->id) {
            throw new InvalidArgumentException('Signer does not belong to this wallet');
        }

        $activeSignersCount = $wallet->activeSigners()->count();

        // Don't allow removal if it would leave fewer than required signatures
        if ($activeSignersCount - 1 < $wallet->required_signatures) {
            throw new RuntimeException(
                "Cannot remove signer: wallet requires at least {$wallet->required_signatures} active signers"
            );
        }

        $signer->deactivate();
    }

    /**
     * Get wallets for a user (owned or signer).
     *
     * @return Collection<int, MultiSigWallet>
     */
    public function getUserWallets(User $user, ?string $chain = null): Collection
    {
        $query = MultiSigWallet::query()
            ->with(['signers.user'])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereHas('signers', function ($sq) use ($user) {
                        // @phpstan-ignore-next-line (valid Eloquent column names)
                        $sq->where('user_id', $user->id)->where('is_active', true);
                    });
            });

        if ($chain !== null) {
            $query->forChain($chain);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get wallets owned by a user.
     *
     * @return Collection<int, MultiSigWallet>
     */
    public function getOwnedWallets(User $user): Collection
    {
        return MultiSigWallet::with(['signers.user'])
            ->ownedBy($user->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get wallets where user is a signer but not owner.
     *
     * @return Collection<int, MultiSigWallet>
     */
    public function getSignerWallets(User $user): Collection
    {
        return MultiSigWallet::with(['signers.user', 'user'])
            ->whereUserIsSigner($user->id)
            ->where('user_id', '!=', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get a specific wallet with full details.
     */
    public function getWalletWithDetails(string $walletId): ?MultiSigWallet
    {
        return MultiSigWallet::with([
            'user',
            'signers.user',
            'signers.hardwareWalletAssociation',
            'pendingApprovalRequests',
        ])->find($walletId);
    }

    /**
     * Suspend a wallet.
     */
    public function suspendWallet(MultiSigWallet $wallet): void
    {
        $wallet->suspend();
    }

    /**
     * Archive a wallet.
     */
    public function archiveWallet(MultiSigWallet $wallet): void
    {
        // Cancel any pending approval requests
        $wallet->pendingApprovalRequests()->each(function ($request) {
            $request->markAsCancelled();
        });

        $wallet->archive();
    }

    /**
     * Check if multi-sig is enabled.
     */
    public function isMultiSigEnabled(): bool
    {
        return (bool) config('blockchain.multi_sig.enabled', true);
    }

    /**
     * Get supported signature schemes.
     *
     * @return array<int, string>
     */
    public function getSupportedSchemes(): array
    {
        return config('blockchain.multi_sig.supported_schemes', ['2-of-3', '3-of-5', '2-of-2', '3-of-4']);
    }

    /**
     * Check if a chain is supported for multi-sig.
     */
    public function isSupportedChain(string $chain): bool
    {
        $supportedChains = array_keys(config('blockchain.hardware_wallets.supported_chains', []));

        return in_array($chain, $supportedChains, true);
    }

    /**
     * Get configuration limits.
     *
     * @return array<string, int>
     */
    public function getConfigurationLimits(): array
    {
        return [
            'max_signers'          => config('blockchain.multi_sig.max_signers', 10),
            'min_signers'          => config('blockchain.multi_sig.min_signers', 2),
            'approval_ttl_seconds' => config('blockchain.multi_sig.approval_ttl_seconds', 86400),
        ];
    }

    /**
     * Validate adding a signer to a wallet.
     */
    private function validateAddSigner(
        MultiSigWallet $wallet,
        string $signerType,
        ?HardwareWalletAssociation $hardwareWallet,
    ): void {
        if ($wallet->isFullySetUp()) {
            throw new RuntimeException('Wallet already has all signers');
        }

        if (
            ! in_array($signerType, [
            MultiSigWalletSigner::TYPE_HARDWARE_LEDGER,
            MultiSigWalletSigner::TYPE_HARDWARE_TREZOR,
            MultiSigWalletSigner::TYPE_INTERNAL,
            MultiSigWalletSigner::TYPE_EXTERNAL,
            ], true)
        ) {
            throw new InvalidArgumentException("Invalid signer type: {$signerType}");
        }

        // Validate hardware wallet association
        if ($hardwareWallet !== null) {
            if ($hardwareWallet->chain !== $wallet->chain) {
                throw new InvalidArgumentException('Hardware wallet chain does not match wallet chain');
            }

            // Check if this hardware wallet is already a signer
            $existingSigner = $wallet->signers()
                ->where('hardware_wallet_association_id', $hardwareWallet->id)
                ->where('is_active', true)
                ->first();

            if ($existingSigner !== null) {
                throw new InvalidArgumentException('This hardware wallet is already a signer on this wallet');
            }
        }
    }

    /**
     * Generate the multi-sig wallet address based on signers.
     * In a real implementation, this would derive the address from the public keys.
     */
    private function generateWalletAddress(MultiSigWallet $wallet): void
    {
        // For now, we generate a placeholder address
        // In production, this would use the actual multi-sig address derivation
        // based on the chain (e.g., P2SH for Bitcoin, Gnosis Safe for Ethereum)
        $publicKeys = $wallet->activeSigners()->pluck('public_key')->toArray();
        sort($publicKeys);

        // Simple deterministic address generation (placeholder)
        $addressHash = hash('sha256', implode(':', [
            $wallet->chain,
            $wallet->required_signatures,
            implode(',', $publicKeys),
        ]));

        $prefix = match ($wallet->chain) {
            'bitcoin'                    => 'bc1q',
            'ethereum', 'polygon', 'bsc' => '0x',
            default                      => 'ms',
        };

        $wallet->setAddress($prefix . substr($addressHash, 0, 40));
    }
}
