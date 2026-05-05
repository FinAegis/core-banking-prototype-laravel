<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Services;

use App\Domain\Relayer\Contracts\PaymasterInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\ValueObjects\UserOperation;

/**
 * Demo implementation of the ERC-4337 Paymaster.
 */
class DemoPaymasterService implements PaymasterInterface
{
    /**
     * Demo paymaster addresses per network.
     */
    private const PAYMASTER_ADDRESSES = [
        'polygon'  => '0x0000000000000000000000000000000000000001',
        'arbitrum' => '0x0000000000000000000000000000000000000002',
        'optimism' => '0x0000000000000000000000000000000000000003',
        'base'     => '0x0000000000000000000000000000000000000004',
        'ethereum' => '0x0000000000000000000000000000000000000005',
    ];

    public function willSponsor(UserOperation $userOp): bool
    {
        // In demo mode, always sponsor
        return true;
    }

    public function getPaymasterData(
        UserOperation $userOp,
        string $feeToken,
        float $feeAmount
    ): string {
        // Demo: return mock paymaster data
        // Format: paymaster_address + encoded fee info
        return '0x' . str_repeat('00', 20) . bin2hex(pack('a10f', $feeToken, $feeAmount));
    }

    public function estimateFee(
        string $callData,
        SupportedNetwork $network
    ): array {
        // Demo: estimate based on calldata size and network
        $callDataSize = (int) (strlen($callData) / 2); // hex bytes
        $baseGas = 21000; // Base transaction gas
        $callDataGas = $callDataSize * 16; // 16 gas per byte
        $totalGas = $baseGas + $callDataGas + 50000; // Add buffer

        $gasPrice = $network->getAverageGasCostUsd();
        $fee = ($totalGas / 1_000_000) * $gasPrice;

        return [
            'gas_estimate' => $totalGas,
            'fee_usdc'     => round($fee, 6),
            'fee_usdt'     => round($fee, 6),
        ];
    }

    public function getAddress(SupportedNetwork $network): string
    {
        return self::PAYMASTER_ADDRESSES[$network->value] ?? self::PAYMASTER_ADDRESSES['polygon'];
    }

    /**
     * Demo sponsorship: returns a fixed paymasterAndData blob and conservative
     * gas defaults. Useful for local testing without hitting Pimlico.
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
    ): array {
        return [
            'paymasterAndData'     => $this->getAddress($network) . str_repeat('0', 80), // address + 40-byte padding
            'callGasLimit'         => 100_000,
            'verificationGasLimit' => 150_000,
            'preVerificationGas'   => 50_000,
            'maxFeePerGas'         => 30_000_000_000,        // 30 gwei
            'maxPriorityFeePerGas' => 1_000_000_000,         // 1 gwei
        ];
    }
}
