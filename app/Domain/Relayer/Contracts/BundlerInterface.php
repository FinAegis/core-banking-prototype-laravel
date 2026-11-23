<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Contracts;

use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\ValueObjects\UserOperation;

/**
 * Interface for ERC-4337 bundler implementations.
 *
 * The bundler aggregates UserOperations and submits them to the EntryPoint contract.
 */
interface BundlerInterface
{
    /**
     * Submit a UserOperation to the bundler.
     *
     * @return string The userOpHash (unique identifier for the operation)
     */
    public function submitUserOperation(
        UserOperation $userOp,
        SupportedNetwork $network
    ): string;

    /**
     * Get the status of a submitted UserOperation.
     *
     * @return array{status: string, tx_hash: ?string, receipt: ?array<string, mixed>}
     */
    public function getUserOperationStatus(string $userOpHash): array;

    /**
     * Estimate gas for a UserOperation.
     *
     * @return array{preVerificationGas: int, verificationGasLimit: int, callGasLimit: int}
     */
    public function estimateUserOperationGas(
        UserOperation $userOp,
        SupportedNetwork $network
    ): array;

    /**
     * Get supported EntryPoint address for a network.
     */
    public function getEntryPointAddress(SupportedNetwork $network): string;
}
