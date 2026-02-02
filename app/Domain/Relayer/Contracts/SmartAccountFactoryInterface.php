<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Contracts;

/**
 * Interface for smart account factory operations.
 *
 * Handles deterministic address computation and account deployment.
 */
interface SmartAccountFactoryInterface
{
    /**
     * Compute the counterfactual address for a smart account.
     *
     * This returns the address the account WILL have once deployed,
     * without actually deploying it.
     *
     * @param string $ownerAddress The EOA owner address
     * @param string $network The network name (polygon, base, arbitrum)
     * @param int $salt Optional salt for different accounts per owner
     * @return string The computed smart account address
     */
    public function computeAddress(string $ownerAddress, string $network, int $salt = 0): string;

    /**
     * Generate the init code for deploying a smart account.
     *
     * The init code is used in the first UserOperation to deploy
     * the account on-chain.
     *
     * @param string $ownerAddress The EOA owner address
     * @param string $network The network name
     * @param int $salt Optional salt
     * @return string The init code as hex string
     */
    public function getInitCode(string $ownerAddress, string $network, int $salt = 0): string;

    /**
     * Check if a smart account is deployed on-chain.
     *
     * @param string $accountAddress The smart account address
     * @param string $network The network name
     * @return bool True if deployed
     */
    public function isDeployed(string $accountAddress, string $network): bool;

    /**
     * Get the factory contract address for a network.
     *
     * @param string $network The network name
     * @return string|null The factory address or null if not configured
     */
    public function getFactoryAddress(string $network): ?string;

    /**
     * Check if a network is supported.
     */
    public function supportsNetwork(string $network): bool;

    /**
     * Get all supported networks.
     *
     * @return array<string>
     */
    public function getSupportedNetworks(): array;
}
