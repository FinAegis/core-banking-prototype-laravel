<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Services;

use App\Domain\Relayer\Contracts\BundlerInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Exceptions\RpcException;
use App\Domain\Relayer\ValueObjects\UserOperation;
use Illuminate\Support\Facades\Log;

/**
 * Production ERC-4337 bundler service using Pimlico v2 API.
 *
 * Submits UserOperations, estimates gas, and queries operation status
 * via Pimlico's JSON-RPC bundler endpoint.
 */
class PimlicoBundlerService implements BundlerInterface
{
    public function __construct(
        private readonly EthRpcClient $rpcClient,
    ) {
    }

    /**
     * Submit a UserOperation to the Pimlico bundler.
     *
     * @throws RpcException
     */
    public function submitUserOperation(UserOperation $userOp, SupportedNetwork $network): string
    {
        $entryPoint = $this->getEntryPointAddress($network);

        Log::info('Submitting UserOperation to Pimlico bundler', [
            'sender'      => $userOp->sender,
            'network'     => $network->value,
            'entry_point' => $entryPoint,
        ]);

        /** @var string $userOpHash */
        $userOpHash = $this->rpcClient->bundlerCall(
            $network,
            'eth_sendUserOperation',
            [$userOp->toArray(), $entryPoint]
        );

        Log::info('UserOperation submitted successfully', [
            'user_op_hash' => $userOpHash,
            'network'      => $network->value,
        ]);

        return $userOpHash;
    }

    /**
     * Get UserOperation receipt from the bundler.
     *
     * @return array{status: string, tx_hash: ?string, receipt: ?array<string, mixed>}
     *
     * @throws RpcException
     */
    public function getUserOperationStatus(string $userOpHash): array
    {
        // Try all networks to find the receipt (hash is unique across chains)
        foreach (SupportedNetwork::cases() as $network) {
            try {
                $receipt = $this->rpcClient->bundlerCall(
                    $network,
                    'eth_getUserOperationReceipt',
                    [$userOpHash]
                );

                if ($receipt !== null) {
                    /** @var array<string, mixed> $receiptArray */
                    $receiptArray = (array) $receipt;

                    $success = ($receiptArray['success'] ?? false) === true
                        || ($receiptArray['success'] ?? '') === 'true';

                    return [
                        'status'  => $success ? 'confirmed' : 'failed',
                        'tx_hash' => $receiptArray['receipt']['transactionHash'] ?? null,
                        'receipt' => [
                            'success' => $success,
                            'gasUsed' => isset($receiptArray['actualGasUsed'])
                                ? (int) hexdec((string) $receiptArray['actualGasUsed'])
                                : 0,
                            'blockNumber' => isset($receiptArray['receipt']['blockNumber'])
                                ? (int) hexdec((string) $receiptArray['receipt']['blockNumber'])
                                : null,
                        ],
                    ];
                }
            } catch (RpcException) {
                // Try next network
                continue;
            }
        }

        return [
            'status'  => 'pending',
            'tx_hash' => null,
            'receipt' => null,
        ];
    }

    /**
     * Estimate gas for a UserOperation via the bundler.
     *
     * @return array{preVerificationGas: int, verificationGasLimit: int, callGasLimit: int}
     *
     * @throws RpcException
     */
    public function estimateUserOperationGas(UserOperation $userOp, SupportedNetwork $network): array
    {
        $entryPoint = $this->getEntryPointAddress($network);

        /** @var array<string, string> $estimate */
        $estimate = (array) $this->rpcClient->bundlerCall(
            $network,
            'eth_estimateUserOperationGas',
            [$userOp->toArray(), $entryPoint]
        );

        return [
            'preVerificationGas' => isset($estimate['preVerificationGas'])
                ? (int) hexdec($estimate['preVerificationGas'])
                : 50000,
            'verificationGasLimit' => isset($estimate['verificationGasLimit'])
                ? (int) hexdec($estimate['verificationGasLimit'])
                : 100000,
            'callGasLimit' => isset($estimate['callGasLimit'])
                ? (int) hexdec($estimate['callGasLimit'])
                : 100000,
        ];
    }

    /**
     * Get EntryPoint address for the network.
     */
    public function getEntryPointAddress(SupportedNetwork $network): string
    {
        return $network->getEntryPointAddress();
    }
}
