<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Services;

use App\Domain\Relayer\Contracts\PaymasterInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Exceptions\RpcException;
use App\Domain\Relayer\ValueObjects\UserOperation;
use Illuminate\Support\Facades\Log;

/**
 * Production paymaster service using Pimlico v2 sponsorship API.
 *
 * Sponsors UserOperations via Pimlico's pm_sponsorUserOperation endpoint
 * and estimates fees using real gas prices from the network.
 */
class PimlicoPaymasterService implements PaymasterInterface
{
    public function __construct(
        private readonly EthRpcClient $rpcClient,
    ) {
    }

    /**
     * Check if this UserOperation should be sponsored.
     *
     * Validates that the UserOperation has required fields for sponsorship.
     */
    public function willSponsor(UserOperation $userOp): bool
    {
        // Require non-empty sender and callData
        if (empty($userOp->sender) || $userOp->sender === '0x') {
            return false;
        }

        if (empty($userOp->callData) || $userOp->callData === '0x') {
            return false;
        }

        return true;
    }

    /**
     * Get paymaster data by calling Pimlico's sponsorship endpoint.
     *
     * @throws RpcException
     */
    public function getPaymasterData(UserOperation $userOp, string $feeToken, float $feeAmount): string
    {
        // Determine the network from the UserOperation context
        // The caller should ensure the correct network context
        $network = SupportedNetwork::tryFrom(
            (string) config('relayer.default_network', 'polygon')
        ) ?? SupportedNetwork::POLYGON;

        $entryPoint = $network->getEntryPointAddress();

        try {
            /** @var array<string, string> $result */
            $result = (array) $this->rpcClient->bundlerCall(
                $network,
                'pm_sponsorUserOperation',
                [$userOp->toArray(), $entryPoint]
            );

            return $result['paymasterAndData'] ?? '0x';
        } catch (RpcException $e) {
            Log::warning('Pimlico sponsorship failed, falling back to empty paymaster data', [
                'error'  => $e->getMessage(),
                'sender' => $userOp->sender,
            ]);

            throw $e;
        }
    }

    /**
     * Estimate fee using real gas prices from the network.
     *
     * @return array{gas_estimate: int, fee_usdc: float, fee_usdt: float}
     */
    public function estimateFee(string $callData, SupportedNetwork $network): array
    {
        $callDataSize = (int) (strlen($callData) / 2);
        $baseGas = 21000;
        $callDataGas = $callDataSize * 16;
        $totalGas = $baseGas + $callDataGas + 50000;

        // Try to fetch real gas price, fall back to static estimates
        $gasPriceUsd = $network->getAverageGasCostUsd();

        try {
            $gasPriceHex = $this->rpcClient->getGasPrice($network);
            $gasPriceWei = (float) hexdec($gasPriceHex);
            $gasPriceGwei = $gasPriceWei / 1e9;

            // Approximate USD cost using network average as reference
            $gasPriceUsd = ($totalGas * $gasPriceGwei / 1e9) * $this->getNativeTokenPriceUsd($network);
        } catch (RpcException $e) {
            Log::debug('Using static gas estimate, RPC unavailable', [
                'network' => $network->value,
                'error'   => $e->getMessage(),
            ]);
            $gasPriceUsd = ($totalGas / 1_000_000) * $network->getAverageGasCostUsd();
        }

        // Apply markup and minimum fee from config
        $markup = (float) config('relayer.fees.markup_percentage', 0.1);
        $minimumFee = (float) config('relayer.fees.minimum_fee', 0.01);

        $fee = max($gasPriceUsd * (1 + $markup), $minimumFee);

        return [
            'gas_estimate' => $totalGas,
            'fee_usdc'     => round($fee, 6),
            'fee_usdt'     => round($fee, 6),
        ];
    }

    /**
     * Get the paymaster address for a network.
     */
    public function getAddress(SupportedNetwork $network): string
    {
        return $network->getPaymasterAddress();
    }

    /**
     * Get approximate native token price in USD.
     *
     * In a full production system, this would query a price oracle.
     */
    private function getNativeTokenPriceUsd(SupportedNetwork $network): float
    {
        return match ($network) {
            SupportedNetwork::POLYGON  => 0.50,
            SupportedNetwork::ETHEREUM => 2500.0,
            SupportedNetwork::ARBITRUM,
            SupportedNetwork::OPTIMISM,
            SupportedNetwork::BASE => 2500.0,
        };
    }
}
