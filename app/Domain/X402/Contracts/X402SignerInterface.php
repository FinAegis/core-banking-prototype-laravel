<?php

declare(strict_types=1);

namespace App\Domain\X402\Contracts;

/**
 * Interface for signing x402 transfer authorizations.
 *
 * Implementations produce the on-chain payload (e.g. EIP-3009 or Permit2
 * signatures) that a facilitator can later submit to the blockchain.
 */
interface X402SignerInterface
{
    /**
     * Sign a transfer authorization for the given parameters.
     *
     * @param string $network           CAIP-2 network identifier (e.g. "eip155:8453").
     * @param string $to                Recipient wallet address.
     * @param string $amount            Amount in atomic units (6 decimals for USDC).
     * @param string $asset             Asset contract address.
     * @param int    $maxTimeoutSeconds Maximum validity window for the authorization.
     * @param array<string, mixed> $extra  Protocol-specific extensions.
     *
     * @return array<string, mixed> Signed payload suitable for inclusion in a PAYMENT-SIGNATURE header.
     */
    public function signTransferAuthorization(
        string $network,
        string $to,
        string $amount,
        string $asset,
        int $maxTimeoutSeconds,
        array $extra = [],
    ): array;

    /**
     * Get the signer's wallet address.
     */
    public function getAddress(): string;
}
