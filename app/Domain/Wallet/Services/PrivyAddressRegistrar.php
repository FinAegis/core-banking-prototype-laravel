<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\Wallet\Constants\ReservedAddresses;
use App\Domain\Wallet\Exceptions\AddressOwnershipUnavailableException;
use App\Domain\Wallet\Exceptions\AddressRegistrationRateLimitedException;
use App\Domain\Wallet\Exceptions\AddressRegistrationRejectedException;
use App\Domain\Wallet\Helpers\Crypto\Base58;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Throwable;

/**
 * Stores Privy-derived public addresses against a user.
 *
 * Privy generates and holds the keys client-side (passkey-controlled smart
 * accounts on EVM, device-bound ed25519 on Solana). Backend only ever sees
 * public addresses, which we mirror into `blockchain_addresses` so existing
 * Helius/Alchemy webhook sync, balance lookups, and tx indexers continue
 * to work unchanged.
 *
 * EVM smart accounts are counterfactual at the same address across all four
 * EVM chains we support; we record one row per chain to keep
 * `(chain, address)` unique-index queries cheap.
 *
 * Abuse bounds (every new row is watched by Helius/Alchemy and attributes
 * inbound transfers to its owner, so registration must not be free-form):
 *  - re-posting addresses the caller already owns is an idempotent no-op and
 *    never limited;
 *  - claiming a new address is rate limited per user
 *    (`wallet.address_registration.max_new_claims_per_hour`);
 *  - reserved addresses (programs, mints, zero/burn, EntryPoints, token
 *    contracts) are refused, and a Solana address must decode to 32 bytes;
 *  - the address must be a wallet of the caller's Privy account when
 *    ownership verification is enforced ({@see PrivyWalletOwnershipVerifier});
 *  - a user holds at most `max_addresses_per_chain` distinct Privy addresses
 *    per chain;
 *  - an address already claimed by another user — in blockchain_addresses or
 *    as another user's relayer smart account — stays with that user.
 * Every refusal returns the same generic message
 * ({@see AddressRegistrationRejectedException}) so the endpoint is not an
 * "is this address a customer" oracle; the reason is logged for operators.
 */
class PrivyAddressRegistrar
{
    private const EVM_CHAINS = ['polygon', 'base', 'arbitrum', 'ethereum'];

    private const ADDRESS_REGEX_EVM = '/^0x[a-fA-F0-9]{40}$/';

    private const ADDRESS_REGEX_SOLANA_BASE58 = '/^[1-9A-HJ-NP-Za-km-z]{32,44}$/';

    private const RATE_LIMIT_DECAY_SECONDS = 3600;

    public function __construct(
        private readonly PrivyWalletOwnershipVerifier $ownershipVerifier,
    ) {
    }

    /**
     * @param  array{address: string, owner_passkey_credential_id?: string|null} $evm
     * @param  array{address: string} $solana
     * @return array<int, BlockchainAddress>
     *
     * @throws InvalidArgumentException                 malformed address, or {@see AddressRegistrationRejectedException}
     * @throws AddressRegistrationRateLimitedException  too many new-address claims
     * @throws AddressOwnershipUnavailableException     ownership enforced but Privy could not be asked
     */
    public function register(User $user, array $evm, array $solana): array
    {
        $evmAddress = $this->normalizeEvmAddress($evm['address'] ?? '');
        $solanaAddress = $solana['address'] ?? '';
        $this->assertSolanaAddress($solanaAddress);

        $passkeyCredentialId = $evm['owner_passkey_credential_id'] ?? null;
        if ($passkeyCredentialId !== null && ! is_string($passkeyCredentialId)) {
            throw new InvalidArgumentException('owner_passkey_credential_id must be a string when provided.');
        }

        if (ReservedAddresses::isReservedEvm($evmAddress) || ReservedAddresses::isReservedSolana($solanaAddress)) {
            $this->reject($user, 'reserved_address', ['evm' => $evmAddress, 'solana' => $solanaAddress]);
        }

        $ownsEvm = $this->ownsAddress($user, self::EVM_CHAINS, $evmAddress);
        $ownsSolana = $this->ownsAddress($user, ['solana'], $solanaAddress);
        $privyVerified = null;

        if (! $ownsEvm || ! $ownsSolana) {
            // New claim: rate limit first (bounds ownership look-ups and
            // conflict probes), then verify ownership outside the DB
            // transaction (it may call Privy).
            $this->hitRateLimit($user);

            $privyVerified = $this->ownershipVerifier->verify(
                $user,
                $ownsEvm ? null : $evmAddress,
                $ownsSolana ? null : $solanaAddress,
            );
        }

        $records = [];

        DB::transaction(function () use ($user, $evmAddress, $solanaAddress, $passkeyCredentialId, $ownsEvm, $ownsSolana, $privyVerified, &$records): void {
            // Serialise concurrent registrations for this user so the per-user
            // cap cannot be raced past.
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            if (! $ownsEvm) {
                $this->assertWithinCap($user, 'polygon', $evmAddress);
                $this->assertNotOtherUsersSmartAccount($user, $evmAddress);
            }

            if (! $ownsSolana) {
                $this->assertWithinCap($user, 'solana', $solanaAddress);
            }

            foreach (self::EVM_CHAINS as $chain) {
                $records[] = $this->upsertAddress(
                    user: $user,
                    chain: $chain,
                    address: $evmAddress,
                    privyVerified: $privyVerified,
                    metadata: [
                        'provider'                    => 'privy',
                        'wallet_kind'                 => 'privy_smart_account',
                        'owner_passkey_credential_id' => $passkeyCredentialId,
                    ],
                );
            }

            $records[] = $this->upsertAddress(
                user: $user,
                chain: 'solana',
                address: $solanaAddress,
                privyVerified: $privyVerified,
                metadata: [
                    'provider'    => 'privy',
                    'wallet_kind' => 'privy_embedded_solana',
                ],
            );
        });

        return $records;
    }

    /**
     * @param  array<string, mixed> $metadata
     */
    private function upsertAddress(
        User $user,
        string $chain,
        string $address,
        ?bool $privyVerified,
        array $metadata,
    ): BlockchainAddress {
        $existing = BlockchainAddress::query()
            ->where('chain', $chain)
            ->where('address', $address)
            ->first();

        if ($existing instanceof BlockchainAddress) {
            if ($existing->user_uuid !== $user->uuid) {
                // First claimant keeps it. Generic message: never reveal that
                // the address belongs to another customer.
                if ($privyVerified === true) {
                    // Privy says the caller owns this wallet, so the earlier
                    // claim is a squat (or data corruption) that blocks the
                    // real owner. Operator fix: wallet:reassign-address.
                    Log::critical('wallet.addresses: Privy-verified owner blocked by an earlier claim of the same address', [
                        'chain'               => $chain,
                        'address'             => $address,
                        'claimant_uuid'       => $user->uuid,
                        'existing_owner_uuid' => $existing->user_uuid,
                    ]);
                }

                $this->reject($user, 'claimed_by_other_user', [
                    'chain'               => $chain,
                    'address'             => $address,
                    'existing_owner_uuid' => $existing->user_uuid,
                    'privy_verified'      => $privyVerified,
                ]);
            }
            $existing->fill([
                'is_active' => true,
                'metadata'  => array_merge($existing->metadata ?? [], $metadata),
            ])->save();

            return $existing;
        }

        try {
            return BlockchainAddress::create([
                'user_uuid'       => $user->uuid,
                'chain'           => $chain,
                'address'         => $address,
                'public_key'      => $address,
                'is_active'       => true,
                'label'           => $chain === 'solana' ? 'Privy Solana' : 'Privy Smart Account',
                'derivation_path' => null,
                'metadata'        => $metadata,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Another user's concurrent registration claimed the address
            // between the look-up above and this insert (the per-user lock
            // does not serialise different users). Same generic refusal.
            $this->reject($user, 'claimed_concurrently', [
                'chain'          => $chain,
                'address'        => $address,
                'privy_verified' => $privyVerified,
            ]);
        }
    }

    /**
     * Whether the user already holds this address on any of the given chains.
     *
     * @param array<int, string> $chains
     */
    private function ownsAddress(User $user, array $chains, string $address): bool
    {
        return BlockchainAddress::query()
            ->whereIn('chain', $chains)
            ->where('address', $address)
            ->where('user_uuid', $user->uuid)
            ->exists();
    }

    /**
     * @throws AddressRegistrationRateLimitedException
     */
    private function hitRateLimit(User $user): void
    {
        $max = max(1, (int) config('wallet.address_registration.max_new_claims_per_hour', 10));
        $key = 'wallet-address-register:' . $user->getKey();

        // hit() increments atomically and returns the new count, so a burst
        // of concurrent requests cannot slip past the limit.
        if (RateLimiter::hit($key, self::RATE_LIMIT_DECAY_SECONDS) > $max) {
            Log::warning('wallet.addresses: new-address registration rate limit reached', [
                'user_uuid' => $user->uuid,
                'limit'     => $max,
            ]);

            throw new AddressRegistrationRateLimitedException(max(1, RateLimiter::availableIn($key)));
        }
    }

    /**
     * Cap the number of distinct Privy addresses a user can hold on a chain
     * (EVM rows mirror one address across all EVM chains, so polygon stands
     * for the EVM family). Rows from other provisioning paths are not
     * counted.
     */
    private function assertWithinCap(User $user, string $chain, string $address): void
    {
        $max = max(1, (int) config('wallet.address_registration.max_addresses_per_chain', 3));

        // A user holds a handful of rows per chain, so filter the provider in
        // PHP rather than with a JSON-path query (portable across drivers).
        $held = BlockchainAddress::query()
            ->where('user_uuid', $user->uuid)
            ->where('chain', $chain)
            ->where('address', '!=', $address)
            ->get(['address', 'metadata'])
            ->filter(fn (BlockchainAddress $row): bool => ($row->metadata['provider'] ?? null) === 'privy')
            ->pluck('address')
            ->unique()
            ->count();

        if ($held >= $max) {
            $this->reject($user, 'per_user_cap', ['chain' => $chain, 'held' => $held, 'limit' => $max]);
        }
    }

    /**
     * An EVM address that is another user's relayer smart account belongs to
     * that user — registering it would hijack attribution of transfers to it.
     */
    private function assertNotOtherUsersSmartAccount(User $user, string $evmAddress): void
    {
        $owner = SmartAccount::query()
            ->where('account_address', $evmAddress)
            ->where('user_id', '!=', $user->getKey())
            ->value('user_id');

        if ($owner !== null) {
            $this->reject($user, 'other_users_smart_account', [
                'address'          => $evmAddress,
                'existing_user_id' => $owner,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $context
     *
     * @throws AddressRegistrationRejectedException
     */
    private function reject(User $user, string $reason, array $context): never
    {
        Log::warning('wallet.addresses: registration refused', [
            'reason'    => $reason,
            'user_uuid' => $user->uuid,
        ] + $context);

        throw new AddressRegistrationRejectedException();
    }

    private function normalizeEvmAddress(string $address): string
    {
        if (preg_match(self::ADDRESS_REGEX_EVM, $address) !== 1) {
            throw new InvalidArgumentException("Invalid EVM address: {$address}");
        }

        return strtolower($address);
    }

    private function assertSolanaAddress(string $address): void
    {
        if (preg_match(self::ADDRESS_REGEX_SOLANA_BASE58, $address) !== 1) {
            throw new InvalidArgumentException("Invalid Solana base58 address: {$address}");
        }

        // An ed25519 public key is exactly 32 bytes; the length/alphabet regex
        // alone admits strings that decode to 31-33 bytes.
        try {
            $decoded = Base58::decode($address);
        } catch (Throwable) {
            $decoded = '';
        }

        if (strlen($decoded) !== 32) {
            throw new InvalidArgumentException("Invalid Solana base58 address: {$address}");
        }
    }
}
