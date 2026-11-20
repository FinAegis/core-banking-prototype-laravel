<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Services;

use App\Domain\Relayer\Contracts\SmartAccountFactoryInterface;
use App\Domain\Relayer\Exceptions\SmartAccountException;
use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\Wallet\Exceptions\AddressOwnershipUnavailableException;
use App\Domain\Wallet\Exceptions\AddressRegistrationRejectedException;
use App\Domain\Wallet\Services\PrivyWalletOwnershipVerifier;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
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
        private readonly ?PrivyWalletOwnershipVerifier $ownershipVerifier = null,
    ) {
    }

    /**
     * Create or retrieve a smart account for a user.
     *
     * If the user's account for this owner and network already exists,
     * returns it. Otherwise creates a new one, unless the owner address is
     * registered to another user's smart account or the user is at the
     * owner-address cap. A caller-supplied owner the user does not hold yet
     * is checked against the caller's Privy wallets, like a wallet address
     * registration (`wallet.address_registration.ownership_verification`:
     * `monitor` logs a mismatch, `enforce` refuses it). Every refusal is the
     * same generic 422 INVALID_ADDRESS and reveals nothing about another
     * account.
     *
     * @throws SmartAccountException If network is invalid or the owner address is refused
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
            if ((int) $existing->user_id !== (int) $user->id) {
                $this->refuseOwner($user, 'owner_of_other_user', $normalizedOwner, $network);
            }

            return $existing;
        }

        // Outside the DB transaction: the ownership check may call Privy.
        $this->verifyNewOwner($user, $normalizedOwner, $network);

        // Compute counterfactual address
        $accountAddress = $this->factory->computeAddress($ownerAddress, $network);

        try {
            $account = DB::transaction(function () use ($user, $normalizedOwner, $accountAddress, $network): SmartAccount {
                // Serialise this user's creations so the owner cap cannot be
                // raced past.
                User::query()->whereKey($user->getKey())->lockForUpdate()->first();

                $owners = self::ownerAddressesOf((int) $user->id);
                if (! in_array($normalizedOwner, $owners, true) && count($owners) >= self::maxOwnerAddressesPerUser()) {
                    $this->refuseOwner($user, 'owner_cap', $normalizedOwner, $network);
                }

                return SmartAccount::create([
                    'user_id'         => $user->id,
                    'owner_address'   => $normalizedOwner,
                    'account_address' => strtolower($accountAddress),
                    'network'         => $network,
                    'deployed'        => false,
                    'nonce'           => 0,
                    'pending_ops'     => 0,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Created meanwhile by a concurrent request.
            $existing = SmartAccount::where('owner_address', $normalizedOwner)
                ->where('network', $network)
                ->first();

            if ($existing === null || (int) $existing->user_id !== (int) $user->id) {
                $this->refuseOwner($user, 'owner_claimed_concurrently', $normalizedOwner, $network);
            }

            return $existing;
        }

        Log::info('Smart account created', [
            'user_id'         => $user->id,
            'owner_address'   => $normalizedOwner,
            'account_address' => $accountAddress,
            'network'         => $network,
        ]);

        return $account;
    }

    /**
     * The owner address the server assigns a user who creates an account
     * without naming one (the mobile onboarding flow). It derives from the
     * app key, so only this server can compute it.
     */
    public function derivedOwnerAddress(User $user): string
    {
        $seed = $user->id . ':' . config('app.key');
        $hash = hash('sha3-256', $seed);

        return '0x' . substr($hash, 24); // Last 20 bytes (40 hex chars)
    }

    /**
     * Proof of control for an owner address the user is claiming for the
     * first time: it must be one of the caller's Privy wallets. Owners the
     * user already holds and the server-derived owner are not checked. In
     * `monitor` mode a mismatch is only logged; in `enforce` mode it is
     * refused, and so is a check that cannot be made (fail closed).
     *
     * @throws SmartAccountException generic 422 INVALID_ADDRESS (enforce mode)
     */
    private function verifyNewOwner(User $user, string $normalizedOwner, string $network): void
    {
        $owners = self::ownerAddressesOf((int) $user->id);

        if (in_array($normalizedOwner, $owners, true) || $normalizedOwner === strtolower($this->derivedOwnerAddress($user))) {
            return;
        }

        // Over the cap the transaction refuses anyway; refuse before asking Privy.
        if (count($owners) >= self::maxOwnerAddressesPerUser()) {
            $this->refuseOwner($user, 'owner_cap', $normalizedOwner, $network);
        }

        $verifier = $this->ownershipVerifier ?? app(PrivyWalletOwnershipVerifier::class);

        try {
            $verified = $verifier->verify($user, $normalizedOwner, null);
        } catch (AddressRegistrationRejectedException) {
            $this->refuseOwner($user, 'owner_not_verified', $normalizedOwner, $network);
        } catch (AddressOwnershipUnavailableException) {
            $this->refuseOwner($user, 'owner_verification_unavailable', $normalizedOwner, $network);
        }

        if ($verified === false) {
            Log::warning('Smart account owner is not a wallet of the caller\'s Privy account (monitor mode, created anyway)', [
                'user_id'       => $user->id,
                'owner_address' => $normalizedOwner,
                'network'       => $network,
            ]);
        }
    }

    /**
     * Distinct owner addresses a user may hold smart accounts for. A Zelta
     * user has one owner mirrored across the EVM networks; every created
     * account is watched (Alchemy), so the count is capped.
     */
    public static function maxOwnerAddressesPerUser(): int
    {
        return max(1, (int) config('wallet.address_registration.max_smart_account_owners', 3));
    }

    /**
     * @return list<string> the user's distinct (lower-case) owner addresses
     */
    public static function ownerAddressesOf(int $userId): array
    {
        $owners = SmartAccount::query()
            ->where('user_id', $userId)
            ->pluck('owner_address')
            ->map(static fn (mixed $owner): string => strtolower((string) $owner))
            ->unique()
            ->all();

        return array_values($owners);
    }

    /**
     * @throws SmartAccountException always (generic 422; the reason is logged)
     */
    private function refuseOwner(User $user, string $reason, string $ownerAddress, string $network): never
    {
        Log::warning('Smart account creation refused', [
            'reason'        => $reason,
            'user_id'       => $user->id,
            'owner_address' => $ownerAddress,
            'network'       => $network,
            'limit'         => self::maxOwnerAddressesPerUser(),
        ]);

        throw SmartAccountException::ownerNotAllowed();
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
