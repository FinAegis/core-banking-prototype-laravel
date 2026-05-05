<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Account\Models\BlockchainAddress;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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
 */
class PrivyAddressRegistrar
{
    private const EVM_CHAINS = ['polygon', 'base', 'arbitrum', 'ethereum'];

    private const ADDRESS_REGEX_EVM = '/^0x[a-fA-F0-9]{40}$/';

    private const ADDRESS_REGEX_SOLANA_BASE58 = '/^[1-9A-HJ-NP-Za-km-z]{32,44}$/';

    /**
     * @param  array{address: string, owner_passkey_credential_id?: string|null} $evm
     * @param  array{address: string} $solana
     * @return array<int, BlockchainAddress>
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

        $records = [];

        DB::transaction(function () use ($user, $evmAddress, $solanaAddress, $passkeyCredentialId, &$records): void {
            foreach (self::EVM_CHAINS as $chain) {
                $records[] = $this->upsertAddress(
                    user: $user,
                    chain: $chain,
                    address: $evmAddress,
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
        array $metadata,
    ): BlockchainAddress {
        $existing = BlockchainAddress::query()
            ->where('chain', $chain)
            ->where('address', $address)
            ->first();

        if ($existing instanceof BlockchainAddress) {
            if ($existing->user_uuid !== $user->uuid) {
                throw new InvalidArgumentException(
                    "Address {$address} on {$chain} is already registered to a different user."
                );
            }
            $existing->fill([
                'is_active' => true,
                'metadata'  => array_merge($existing->metadata ?? [], $metadata),
            ])->save();

            return $existing;
        }

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
    }
}
