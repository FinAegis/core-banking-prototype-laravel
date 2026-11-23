<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Services;

use App\Domain\Relayer\Contracts\BundlerInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\ValueObjects\UserOperation;
use Illuminate\Support\Facades\Cache;

/**
 * Demo implementation of the ERC-4337 Bundler.
 */
class DemoBundlerService implements BundlerInterface
{
    /**
     * EntryPoint v0.6 addresses (same on all EVM chains).
     */
    private const ENTRY_POINT_ADDRESS = '0x5FF137D4b0FDCD49DcA30c7CF57E578a026d2789';

    public function submitUserOperation(
        UserOperation $userOp,
        SupportedNetwork $network
    ): string {
        // Demo: generate a mock userOpHash
        $userOpHash = '0x' . hash('sha256', json_encode($userOp->toArray()) . $network->value);

        // Store for later status lookup
        Cache::put("user_op:{$userOpHash}", [
            'status'       => 'submitted',
            'network'      => $network->value,
            'sender'       => $userOp->sender,
            'submitted_at' => now()->toIso8601String(),
            'tx_hash'      => null,
        ], now()->addHours(24));

        // Simulate confirmation after a short delay
        Cache::put("user_op:{$userOpHash}:confirmed", true, now()->addSeconds(30));

        return $userOpHash;
    }

    /**
     * @return array{status: string, tx_hash: ?string, receipt: ?array<string, mixed>}
     */
    public function getUserOperationStatus(string $userOpHash): array
    {
        $data = Cache::get("user_op:{$userOpHash}");

        if ($data === null) {
            return [
                'status'  => 'not_found',
                'tx_hash' => null,
                'receipt' => null,
            ];
        }

        // Check if confirmed
        if (Cache::has("user_op:{$userOpHash}:confirmed")) {
            return [
                'status'  => 'confirmed',
                'tx_hash' => '0x' . hash('sha256', $userOpHash . 'tx'),
                'receipt' => [
                    'success'     => true,
                    'gasUsed'     => 150000,
                    'blockNumber' => random_int(10000000, 99999999),
                ],
            ];
        }

        return [
            'status'  => $data['status'],
            'tx_hash' => $data['tx_hash'],
            'receipt' => null,
        ];
    }

    public function estimateUserOperationGas(
        UserOperation $userOp,
        SupportedNetwork $network
    ): array {
        // Demo: return reasonable gas estimates
        $callDataSize = (int) (strlen($userOp->callData) / 2);

        return [
            'preVerificationGas'   => 50000,
            'verificationGasLimit' => 100000,
            'callGasLimit'         => 21000 + ($callDataSize * 16) + 50000,
        ];
    }

    public function getEntryPointAddress(SupportedNetwork $network): string
    {
        return self::ENTRY_POINT_ADDRESS;
    }
}
