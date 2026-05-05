<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Contracts;

use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\ValueObjects\UserOperation;

/**
 * Interface for ERC-4337 paymaster implementations.
 *
 * The paymaster pays gas fees on behalf of users, deducting the equivalent
 * value from their stablecoin balance.
 */
interface PaymasterInterface
{
    /**
     * Check if the paymaster will sponsor this operation.
     */
    public function willSponsor(UserOperation $userOp): bool;

    /**
     * Get the paymaster data to include in the UserOperation.
     *
     * @return string Encoded paymaster data
     */
    public function getPaymasterData(
        UserOperation $userOp,
        string $feeToken,
        float $feeAmount
    ): string;

    /**
     * Estimate the fee for sponsoring an operation.
     *
     * @return array{gas_estimate: int, fee_usdc: float, fee_usdt: float}
     */
    public function estimateFee(
        string $callData,
        SupportedNetwork $network
    ): array;

    /**
     * Sponsor a UserOperation via the paymaster's verifying-paymaster endpoint
     * (Pimlico's `pm_sponsorUserOperation`). Returns adjusted gas params + the
     * `paymasterAndData` blob that the bundler will accept on submission.
     *
     * Implementations MAY throw an RpcException-shaped exception when the
     * paymaster declines to sponsor; callers are expected to translate that
     * into a record-level failure.
     *
     * @return array{
     *   paymasterAndData: string,
     *   callGasLimit: int,
     *   verificationGasLimit: int,
     *   preVerificationGas: int,
     *   maxFeePerGas: int,
     *   maxPriorityFeePerGas: int
     * }
     */
    public function sponsor(
        UserOperation $userOp,
        SupportedNetwork $network,
        string $entryPoint,
    ): array;

    /**
     * Get the paymaster contract address for a network.
     */
    public function getAddress(SupportedNetwork $network): string;
}
