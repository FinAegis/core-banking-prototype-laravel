<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Services;

use App\Domain\Relayer\Contracts\SmartAccountFactoryInterface;
use App\Domain\Relayer\Exceptions\SmartAccountException;
use App\Domain\Relayer\Models\SmartAccount;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing ERC-4337 smart accounts.
 *
 * Handles account creation, deployment tracking, and nonce management.
 */
class SmartAccountService
{
    public function __construct(
        private readonly SmartAccountFactoryInterface $factory,
    ) {
    }

    /**
     * Create or retrieve a smart account for a user.
     *
     * If the account already exists, returns it. Otherwise creates a new one.
     *
     * @throws SmartAccountException If network is invalid
     */
    public function getOrCreateAccount(User $user, string $ownerAddress, string $network): SmartAccount
    {
        $this->validateNetwork($network);
        $normalizedOwner = strtolower($ownerAddress);

        // Check for existing account
        $existing = SmartAccount::where('owner_address', $normalizedOwner)
            ->where('network', $network)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Compute counterfactual address
        $accountAddress = $this->factory->computeAddress($ownerAddress, $network);

        // Create the account record
        $account = SmartAccount::create([
            'user_id'         => $user->id,
            'owner_address'   => $normalizedOwner,
            'account_address' => strtolower($accountAddress),
            'network'         => $network,
            'deployed'        => false,
            'nonce'           => 0,
            'pending_ops'     => 0,
        ]);

        Log::info('Smart account created', [
            'user_id'         => $user->id,
            'owner_address'   => $normalizedOwner,
            'account_address' => $accountAddress,
            'network'         => $network,
        ]);

        return $account;
    }

    /**
     * Get a smart account by owner address and network.
     */
    public function getAccount(string $ownerAddress, string $network): ?SmartAccount
    {
        return SmartAccount::where('owner_address', strtolower($ownerAddress))
            ->where('network', $network)
            ->first();
    }

    /**
     * Get a smart account by account address.
     */
    public function getByAccountAddress(string $accountAddress, string $network): ?SmartAccount
    {
        return SmartAccount::where('account_address', strtolower($accountAddress))
            ->where('network', $network)
            ->first();
    }

    /**
     * Get all accounts for a user.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SmartAccount>
     */
    public function getUserAccounts(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return SmartAccount::where('user_id', $user->id)->get();
    }

    /**
     * Get the nonce and pending operations for an account.
     *
     * @return array{nonce: int, pending_ops: int, deployed: bool}
     * @throws SmartAccountException If account not found
     */
    public function getNonceInfo(string $ownerAddress, string $network): array
    {
        $account = $this->getAccount($ownerAddress, $network);

        if (! $account) {
            throw SmartAccountException::accountNotFound($ownerAddress, $network);
        }

        return [
            'nonce'       => $account->nonce,
            'pending_ops' => $account->pending_ops,
            'deployed'    => $account->deployed,
        ];
    }

    /**
     * Get the init code for an account deployment.
     *
     * Returns empty string if account is already deployed.
     */
    public function getInitCode(string $ownerAddress, string $network): string
    {
        $account = $this->getAccount($ownerAddress, $network);

        // If deployed, no init code needed
        if ($account && $account->deployed) {
            return '';
        }

        return $this->factory->getInitCode($ownerAddress, $network);
    }

    /**
     * Check if init code is needed for a transaction.
     */
    public function needsInitCode(string $ownerAddress, string $network): bool
    {
        $account = $this->getAccount($ownerAddress, $network);

        return ! $account || ! $account->deployed;
    }

    /**
     * Mark an account as deployed after successful deployment.
     */
    public function markDeployed(string $ownerAddress, string $network, string $txHash): void
    {
        $account = $this->getAccount($ownerAddress, $network);

        if ($account) {
            $account->markAsDeployed($txHash);

            Log::info('Smart account marked as deployed', [
                'account_address' => $account->account_address,
                'network'         => $network,
                'tx_hash'         => $txHash,
            ]);
        }
    }

    /**
     * Increment the pending operations counter.
     */
    public function incrementPendingOps(string $ownerAddress, string $network): void
    {
        $account = $this->getAccount($ownerAddress, $network);
        $account?->incrementPendingOps();
    }

    /**
     * Process a completed operation (decrement pending, increment nonce).
     */
    public function processCompletedOp(string $ownerAddress, string $network): void
    {
        $account = $this->getAccount($ownerAddress, $network);

        if ($account) {
            $account->incrementNonce();
            $account->decrementPendingOps();
        }
    }

    /**
     * Get supported networks.
     *
     * @return array<string>
     */
    public function getSupportedNetworks(): array
    {
        return $this->factory->getSupportedNetworks();
    }

    /**
     * Validate that a network is supported.
     *
     * @throws SmartAccountException If network is invalid
     */
    private function validateNetwork(string $network): void
    {
        if (! $this->factory->supportsNetwork($network)) {
            throw SmartAccountException::invalidNetwork($network);
        }
    }
}
