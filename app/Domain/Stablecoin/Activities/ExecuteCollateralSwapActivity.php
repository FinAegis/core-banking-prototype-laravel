<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Activities;

use App\Domain\Exchange\Services\OrderService;
use App\Domain\Stablecoin\Services\PriceOracleService;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Workflow\Activity;

class ExecuteCollateralSwapActivity extends Activity
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly PriceOracleService $priceOracle
    ) {
    }

    /**
     * Execute a collateral swap.
     */
    public function execute(string $positionId, array $swap): array
    {
        Log::info('Executing collateral swap', [
            'position_id' => $positionId,
            'swap'        => $swap,
        ]);

        DB::beginTransaction();

        try {
            $fromAsset = $swap['from_asset'];
            $toAsset = $swap['to_asset'];
            $amount = BigDecimal::of($swap['amount']);
            $isCompensation = $swap['is_compensation'] ?? false;

            // Get current prices
            $fromPrice = $this->priceOracle->getPrice($fromAsset);
            $toPrice = $this->priceOracle->getPrice($toAsset);

            // Calculate exchange rate
            $exchangeRate = $fromPrice->dividedBy($toPrice, 8);

            // Calculate amount to receive (with slippage)
            $slippage = BigDecimal::of($swap['estimated_slippage'] ?? 0.005);
            $amountToReceive = $amount
                ->multipliedBy($exchangeRate)
                ->multipliedBy(BigDecimal::one()->minus($slippage));

            // Execute the swap through the order service
            // In production, this would interact with a DEX or internal exchange
            $orderData = $this->orderService->createOrder([
                'position_id' => $positionId,
                'side'        => 'sell',
                'base_asset'  => $fromAsset,
                'quote_asset' => $toAsset,
                'amount'      => $amount->toFloat(),
                'type'        => 'market',
                'metadata'    => [
                    'purpose' => $isCompensation ? 'collateral_rebalancing_compensation' : 'collateral_rebalancing',
                    'swap_id' => uniqid('swap_'),
                ],
            ]);

            $orderId = $orderData['id'] ?? uniqid('order_');

            // Wait for order execution (simplified - in production would be async)
            $executionResult = $this->orderService->getOrder($orderId) ?? [
                'status'          => 'filled',
                'execution_price' => $exchangeRate->toFloat(),
                'gas_used'        => 0,
            ];

            if ($executionResult['status'] !== 'filled') {
                throw new \RuntimeException('Swap order failed to execute');
            }

            DB::commit();

            $result = [
                'success'          => true,
                'position_id'      => $positionId,
                'order_id'         => $orderId,
                'from_asset'       => $fromAsset,
                'to_asset'         => $toAsset,
                'amount_sent'      => $amount->toFloat(),
                'amount_received'  => $amountToReceive->toFloat(),
                'exchange_rate'    => $exchangeRate->toFloat(),
                'slippage_applied' => $slippage->toFloat(),
                'execution_price'  => $executionResult['execution_price'] ?? $exchangeRate->toFloat(),
                'gas_used'         => $executionResult['gas_used'] ?? 0,
            ];

            Log::info('Collateral swap executed successfully', $result);

            return $result;
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to execute collateral swap', [
                'position_id' => $positionId,
                'swap'        => $swap,
                'error'       => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
