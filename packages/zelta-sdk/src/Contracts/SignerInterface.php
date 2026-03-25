<?php

declare(strict_types=1);

namespace Zelta\Contracts;

/**
 * Interface for signing x402 payment authorizations.
 *
 * Implementations produce the signed payload (EIP-712 for EVM or Ed25519
 * for Solana) that the facilitator can submit on-chain.
 */
interface SignerInterface
{
    /**
     * Sign a transfer authorization.
     *
     * @param string $network  CAIP-2 network identifier (e.g. "eip155:8453")
     * @param string $to       Recipient wallet address
     * @param string $amount   Amount in atomic units
     * @param string $asset    Asset contract address or mint
     * @param int    $timeout  Maximum validity window in seconds
     * @param array<string, mixed> $extra Protocol-specific extensions
     *
     * @return array<string, mixed> Signed payload for PAYMENT-SIGNATURE header
     */
    public function sign(
        string $network,
        string $to,
        string $amount,
        string $asset,
        int $timeout,
        array $extra = [],
    ): array;

    /**
     * Get the signer's wallet address.
     */
    public function getAddress(): string;
}
