<?php

declare(strict_types=1);

namespace App\Domain\X402\Services;

use App\Domain\X402\Contracts\X402SignerInterface;

/**
 * Factory for creating the appropriate x402 signer based on network type.
 *
 * Returns an EVM (EIP-712) signer for EVM networks and a Solana (Ed25519)
 * signer for Solana networks.
 */
class X402SignerFactory
{
    public function __construct(
        private readonly X402EIP712SignerService $evmSigner,
        private readonly X402SolanaSignerService $solanaSigner,
    ) {
    }

    /**
     * Get the appropriate signer for the given CAIP-2 network.
     */
    public function forNetwork(string $network): X402SignerInterface
    {
        if (self::isSolanaNetwork($network)) {
            return $this->solanaSigner;
        }

        return $this->evmSigner;
    }

    /**
     * Get the default signer based on configured default network.
     */
    public function default(): X402SignerInterface
    {
        $defaultNetwork = (string) config('x402.server.default_network', 'eip155:8453');

        return $this->forNetwork($defaultNetwork);
    }

    /**
     * Check whether a CAIP-2 network identifier is a Solana network.
     */
    public static function isSolanaNetwork(string $network): bool
    {
        return str_starts_with($network, 'solana:');
    }

    /**
     * Check whether a CAIP-2 network identifier is an EVM network.
     */
    public static function isEvmNetwork(string $network): bool
    {
        return str_starts_with($network, 'eip155:');
    }
}
