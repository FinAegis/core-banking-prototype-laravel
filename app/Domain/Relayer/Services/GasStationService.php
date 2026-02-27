<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Services;

use App\Domain\Relayer\Contracts\BundlerInterface;
use App\Domain\Relayer\Contracts\PaymasterInterface;
use App\Domain\Relayer\Contracts\WalletBalanceProviderInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Events\TransactionSponsored;
use App\Domain\Relayer\Exceptions\RpcException;
use App\Domain\Relayer\ValueObjects\UserOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Gas Station Service for sponsoring meta-transactions.
 *
 * This service enables users to execute blockchain transactions without
 * holding native gas tokens (ETH/MATIC). Instead, they pay fees in stablecoins.
 *
 * Balance Checking:
 * - In production: Uses WalletBalanceProvider to query ERC-20 contracts via RPC
 * - In demo mode: Falls back to always returning true (NOT for production)
 *
 * @see WalletBalanceProviderInterface
 */
class GasStationService
{
    public function __construct(
        private readonly PaymasterInterface $paymaster,
        private readonly BundlerInterface $bundler,
        private readonly ?WalletBalanceProviderInterface $balanceProvider = null,
        private readonly ?EthRpcClient $rpcClient = null,
    ) {
    }

    /**
     * Sponsor a transaction by paying gas on behalf of the user.
     *
     * The user signs a UserOperation, and we submit it through the bundler
     * with our paymaster paying the gas fees.
     *
     * @param  string|null  $initCode  Optional initCode for first-time account deployment (CREATE2)
     * @return array{tx_hash: string, user_op_hash: string, gas_used: int, fee_charged: string, fee_currency: string, is_deployment: bool}
     */
    public function sponsorTransaction(
        string $userAddress,
        string $callData,
        string $signature,
        SupportedNetwork $network = SupportedNetwork::POLYGON,
        string $feeToken = 'USDC',
        ?string $initCode = null
    ): array {
        $isDeployment = $initCode !== null && $initCode !== '' && $initCode !== '0x';

        Log::info('Sponsoring transaction', [
            'user_address'  => $userAddress,
            'network'       => $network->value,
            'fee_token'     => $feeToken,
            'is_deployment' => $isDeployment,
        ]);

        // Validate initCode format if provided
        if ($isDeployment && ! preg_match('/^0x[a-fA-F0-9]+$/', $initCode)) {
            throw new RuntimeException('Invalid initCode format. Expected hex string with 0x prefix.');
        }

        // 1. Get nonce for user (0 for new accounts)
        $nonce = $isDeployment ? 0 : $this->getNonce($userAddress, $network);

        // 2. Create unsigned UserOperation with optional initCode
        $userOp = UserOperation::createUnsigned(
            sender: $userAddress,
            nonce: $nonce,
            callData: $callData,
            initCode: $isDeployment ? $initCode : null,
        );

        // 3. Estimate gas
        $gasEstimate = $this->bundler->estimateUserOperationGas($userOp, $network);

        // 4. Calculate fee
        $feeEstimate = $this->paymaster->estimateFee($callData, $network);
        $feeAmount = $feeToken === 'USDC' ? $feeEstimate['fee_usdc'] : $feeEstimate['fee_usdt'];

        // 5. Check if user has sufficient balance
        if (! $this->hasSufficientBalance($userAddress, $feeToken, $feeAmount, $network)) {
            throw new RuntimeException("Insufficient {$feeToken} balance for gas fee");
        }

        // 6. Get paymaster data
        $paymasterData = $this->paymaster->getPaymasterData($userOp, $feeToken, $feeAmount);

        // 7. Build final UserOperation with gas parameters
        $finalUserOp = $userOp->withGasAndSignature(
            callGasLimit: $gasEstimate['callGasLimit'],
            verificationGasLimit: $gasEstimate['verificationGasLimit'],
            preVerificationGas: $gasEstimate['preVerificationGas'],
            maxFeePerGas: $this->getMaxFeePerGas($network),
            maxPriorityFeePerGas: $this->getMaxPriorityFeePerGas($network),
            paymasterAndData: $paymasterData,
            signature: $signature,
        );

        // 8. Submit to bundler and deduct fee atomically
        return DB::transaction(function () use ($finalUserOp, $network, $userAddress, $feeToken, $feeAmount, $gasEstimate, $isDeployment): array {
            $userOpHash = $this->bundler->submitUserOperation($finalUserOp, $network);

            // 9. Deduct fee from user's stablecoin balance
            $this->deductFee($userAddress, $feeToken, $feeAmount, $network, $userOpHash);

            Log::info('Transaction sponsored successfully', [
                'user_op_hash' => $userOpHash,
                'fee_charged'  => $feeAmount,
                'fee_token'    => $feeToken,
            ]);

            Event::dispatch(new TransactionSponsored(
                userAddress: $userAddress,
                userOpHash: $userOpHash,
                network: $network,
                feeAmount: $feeAmount,
                feeToken: $feeToken,
            ));

            return [
                'tx_hash'       => '', // Will be available after bundler processes
                'user_op_hash'  => $userOpHash,
                'gas_used'      => $gasEstimate['callGasLimit'] + $gasEstimate['verificationGasLimit'],
                'fee_charged'   => number_format($feeAmount, 6),
                'fee_currency'  => $feeToken,
                'is_deployment' => $isDeployment,
            ];
        });
    }

    /**
     * Estimate gas fee for a transaction.
     *
     * @return array{estimated_gas: int, fee_usdc: string, fee_usdt: string, network: string}
     */
    public function estimateFee(
        string $callData,
        SupportedNetwork $network = SupportedNetwork::POLYGON
    ): array {
        $estimate = $this->paymaster->estimateFee($callData, $network);

        return [
            'estimated_gas' => $estimate['gas_estimate'],
            'fee_usdc'      => number_format($estimate['fee_usdc'], 6),
            'fee_usdt'      => number_format($estimate['fee_usdt'], 6),
            'network'       => $network->value,
        ];
    }

    /**
     * Get all supported networks with their detailed configuration.
     *
     * @return array<array{chain_id: int, name: string, entrypoint_address: string, factory_address: string, paymaster_address: string, current_gas_price: string, average_fee_usdc: string, congestion_level: string, fee_token: string}>
     */
    public function getSupportedNetworks(): array
    {
        return array_map(
            fn (SupportedNetwork $network) => [
                'chain_id'           => $network->getChainId(),
                'name'               => $network->value,
                'entrypoint_address' => $network->getEntryPointAddress(),
                'factory_address'    => $network->getFactoryAddress(),
                'paymaster_address'  => $network->getPaymasterAddress(),
                'current_gas_price'  => $network->getCurrentGasPrice(),
                'average_fee_usdc'   => number_format($network->getAverageGasCostUsd(), 4),
                'congestion_level'   => $network->getCongestionLevel(),
                'fee_token'          => 'USDC',
            ],
            SupportedNetwork::cases()
        );
    }

    /**
     * Get nonce for a user's smart wallet from the EntryPoint contract.
     *
     * Queries EntryPoint.getNonce(address,uint192) via eth_call.
     * Falls back to 0 on RPC failure (safe for first transactions).
     */
    private function getNonce(string $userAddress, SupportedNetwork $network): int
    {
        if ($this->rpcClient === null) {
            return 0;
        }

        try {
            // getNonce(address,uint192) selector = 0x35567e1a
            $paddedAddress = str_pad(substr(strtolower($userAddress), 2), 64, '0', STR_PAD_LEFT);
            $paddedKey = str_pad('0', 64, '0', STR_PAD_LEFT); // key = 0

            $result = $this->rpcClient->ethCall($network, [
                'to'   => $network->getEntryPointAddress(),
                'data' => '0x35567e1a' . $paddedAddress . $paddedKey,
            ]);

            // Result is a uint256 in hex
            $hex = ltrim(str_replace('0x', '', $result), '0');

            return $hex === '' ? 0 : (int) hexdec($hex);
        } catch (RpcException $e) {
            Log::warning('Failed to fetch nonce from EntryPoint, defaulting to 0', [
                'user'    => $userAddress,
                'network' => $network->value,
                'error'   => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Check if user has sufficient stablecoin balance.
     *
     * Uses WalletBalanceProvider in production mode to query actual ERC-20 balances.
     * Falls back to demo mode with a warning if provider not available.
     *
     * @throws RuntimeException if balance provider is missing in production
     */
    private function hasSufficientBalance(
        string $userAddress,
        string $token,
        float $amount,
        SupportedNetwork $network = SupportedNetwork::POLYGON
    ): bool {
        // Production mode: Use balance provider
        if ($this->balanceProvider !== null) {
            return $this->balanceProvider->hasBalance($userAddress, $token, $amount, $network);
        }

        // Reject in production environment without a balance provider
        if (app()->environment('production')) {
            Log::critical('Balance provider not configured in production environment', [
                'user'   => $userAddress,
                'token'  => $token,
                'amount' => $amount,
            ]);

            throw new RuntimeException('Balance verification unavailable. Contact support.');
        }

        // Demo mode: Allow in non-production with warning
        Log::warning('Using demo balance check (always true) - NOT suitable for production', [
            'user'        => $userAddress,
            'token'       => $token,
            'amount'      => $amount,
            'environment' => app()->environment(),
        ]);

        return true;
    }

    /**
     * Get the balance for a user's wallet.
     *
     * @return string Balance in token decimals (e.g., "100.000000")
     */
    public function getBalance(
        string $userAddress,
        string $token,
        SupportedNetwork $network = SupportedNetwork::POLYGON
    ): string {
        if ($this->balanceProvider !== null) {
            return $this->balanceProvider->getBalance($userAddress, $token, $network);
        }

        // Demo mode: Return default balance
        return '1000.000000';
    }

    /**
     * Invalidate cached balance for a user.
     * Call this after fee deduction to ensure fresh balance on next check.
     */
    public function invalidateBalanceCache(
        string $userAddress,
        string $token,
        SupportedNetwork $network
    ): void {
        if ($this->balanceProvider !== null) {
            $this->balanceProvider->invalidateCache($userAddress, $token, $network);
        }
    }

    /**
     * Deduct fee from user's stablecoin balance.
     *
     * Records fee deduction in the relayer_fee_ledger for audit trail,
     * then invalidates cached balance. The actual on-chain fee deduction
     * is handled by the paymaster contract during UserOperation execution.
     */
    private function deductFee(
        string $userAddress,
        string $token,
        float $amount,
        SupportedNetwork $network = SupportedNetwork::POLYGON,
        ?string $userOpHash = null,
    ): void {
        // Record fee in ledger for audit trail
        try {
            DB::table('relayer_fee_ledger')->insert([
                'id'           => Str::uuid()->toString(),
                'user_address' => strtolower($userAddress),
                'token'        => $token,
                'amount'       => $amount,
                'network'      => $network->value,
                'type'         => 'gas_sponsorship',
                'user_op_hash' => $userOpHash,
                'created_at'   => now(),
            ]);
        } catch (Throwable $e) {
            // Log but don't fail the transaction — the on-chain fee is the source of truth
            Log::error('Failed to record fee in ledger', [
                'user'   => $userAddress,
                'token'  => $token,
                'amount' => $amount,
                'error'  => $e->getMessage(),
            ]);
        }

        Log::debug('Fee deducted', [
            'user'    => $userAddress,
            'token'   => $token,
            'amount'  => $amount,
            'network' => $network->value,
        ]);

        // Invalidate cached balance after deduction
        $this->invalidateBalanceCache($userAddress, $token, $network);
    }

    /**
     * Get max fee per gas for a network.
     *
     * Fetches real gas price via eth_gasPrice when RPC client is available,
     * applies a 25% buffer for inclusion reliability. Falls back to static values.
     */
    private function getMaxFeePerGas(SupportedNetwork $network): int
    {
        if ($this->rpcClient !== null) {
            try {
                $gasPriceHex = $this->rpcClient->getGasPrice($network);
                $gasPrice = (int) hexdec(str_replace('0x', '', $gasPriceHex));

                // Apply 25% buffer for reliable inclusion
                return (int) ($gasPrice * 1.25);
            } catch (RpcException $e) {
                Log::debug('Using static maxFeePerGas, RPC unavailable', [
                    'network' => $network->value,
                ]);
            }
        }

        return match ($network) {
            SupportedNetwork::POLYGON  => 100_000_000_000,
            SupportedNetwork::ARBITRUM => 1_000_000_000,
            SupportedNetwork::OPTIMISM => 1_000_000_000,
            SupportedNetwork::BASE     => 1_000_000_000,
            SupportedNetwork::ETHEREUM => 50_000_000_000,
        };
    }

    /**
     * Get max priority fee per gas for a network.
     *
     * Fetches real priority fee via eth_maxPriorityFeePerGas when RPC client
     * is available, applies a 25% buffer. Falls back to static values.
     */
    private function getMaxPriorityFeePerGas(SupportedNetwork $network): int
    {
        if ($this->rpcClient !== null) {
            try {
                $priorityFeeHex = $this->rpcClient->getMaxPriorityFeePerGas($network);
                $priorityFee = (int) hexdec(str_replace('0x', '', $priorityFeeHex));

                return (int) ($priorityFee * 1.25);
            } catch (RpcException $e) {
                Log::debug('Using static maxPriorityFeePerGas, RPC unavailable', [
                    'network' => $network->value,
                ]);
            }
        }

        return match ($network) {
            SupportedNetwork::POLYGON  => 30_000_000_000,
            SupportedNetwork::ARBITRUM => 100_000_000,
            SupportedNetwork::OPTIMISM => 100_000_000,
            SupportedNetwork::BASE     => 100_000_000,
            SupportedNetwork::ETHEREUM => 2_000_000_000,
        };
    }
}
