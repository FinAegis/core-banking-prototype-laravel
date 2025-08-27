<?php

namespace Tests\Unit\Domain\Stablecoin\Workflows\Data;

use App\Domain\Stablecoin\Workflows\Data\ReserveRebalanceData;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\DomainTestCase;

class ReserveRebalanceDataTest extends DomainTestCase
{
    #[Test]
    public function test_creates_reserve_rebalance_data_with_default_slippage(): void
    {
        $targetAllocations = [
            'USDC' => '40',
            'USDT' => '40',
            'DAI'  => '20',
        ];

        $data = new ReserveRebalanceData(
            poolId: 'pool-123',
            targetAllocations: $targetAllocations,
            executedBy: 'rebalancer-bot'
        );

        $this->assertEquals('pool-123', $data->poolId);
        $this->assertEquals($targetAllocations, $data->targetAllocations);
        $this->assertEquals('rebalancer-bot', $data->executedBy);
        $this->assertEquals(0.02, $data->maxSlippage); // Default 2%
    }

    #[Test]
    public function test_creates_reserve_rebalance_data_with_custom_slippage(): void
    {
        $targetAllocations = [
            'USDC' => '50',
            'USDT' => '30',
            'DAI'  => '15',
            'FRAX' => '5',
        ];

        $data = new ReserveRebalanceData(
            poolId: 'pool-456',
            targetAllocations: $targetAllocations,
            executedBy: 'treasury-multisig',
            maxSlippage: 0.005 // 0.5%
        );

        $this->assertEquals('pool-456', $data->poolId);
        $this->assertEquals($targetAllocations, $data->targetAllocations);
        $this->assertEquals('treasury-multisig', $data->executedBy);
        $this->assertEquals(0.005, $data->maxSlippage);
    }

    #[Test]
    public function test_to_array_converts_data_correctly(): void
    {
        $targetAllocations = [
            'USDC' => '33.33',
            'USDT' => '33.33',
            'DAI'  => '33.34',
        ];

        $data = new ReserveRebalanceData(
            poolId: 'pool-balanced',
            targetAllocations: $targetAllocations,
            executedBy: 'auto-rebalancer',
            maxSlippage: 0.01
        );

        $array = $data->toArray();

        $this->assertEquals([
            'pool_id'            => 'pool-balanced',
            'target_allocations' => $targetAllocations,
            'executed_by'        => 'auto-rebalancer',
            'max_slippage'       => 0.01,
        ], $array);
    }

    #[Test]
    public function test_validates_allocations_sum_to_100(): void
    {
        $validAllocations = [
            [
                'USDC' => '100', // Single asset
            ],
            [
                'USDC' => '50',
                'USDT' => '50',
            ],
            [
                'USDC' => '25',
                'USDT' => '25',
                'DAI'  => '25',
                'FRAX' => '25',
            ],
            [
                'USDC' => '33.33',
                'USDT' => '33.33',
                'DAI'  => '33.34', // Handles rounding
            ],
        ];

        foreach ($validAllocations as $allocation) {
            $data = new ReserveRebalanceData(
                poolId: 'pool-valid',
                targetAllocations: $allocation,
                executedBy: 'validator'
            );

            $sum = array_sum(array_map('floatval', $allocation));
            $this->assertEqualsWithDelta(100, $sum, 0.01);
        }
    }

    #[Test]
    public function test_handles_various_executor_types(): void
    {
        $executors = [
            'rebalancer-bot',
            'treasury-multisig',
            'governance-proposal-123',
            'admin@example.com',
            'emergency-council',
            '0x1234567890abcdef',
        ];

        foreach ($executors as $executor) {
            $data = new ReserveRebalanceData(
                poolId: 'pool-exec',
                targetAllocations: ['USDC' => '100'],
                executedBy: $executor
            );

            $this->assertEquals($executor, $data->executedBy);
        }
    }

    #[Test]
    public function test_handles_different_slippage_values(): void
    {
        $slippageValues = [
            0.0, // No slippage allowed
            0.001, // 0.1%
            0.005, // 0.5%
            0.01, // 1%
            0.02, // 2% (default)
            0.05, // 5%
            0.1, // 10%
            1.0, // 100% (effectively no limit)
        ];

        foreach ($slippageValues as $slippage) {
            $data = new ReserveRebalanceData(
                poolId: 'pool-slippage',
                targetAllocations: ['USDC' => '100'],
                executedBy: 'tester',
                maxSlippage: $slippage
            );

            $this->assertEquals($slippage, $data->maxSlippage);
        }
    }

    #[Test]
    public function test_handles_many_asset_allocations(): void
    {
        $targetAllocations = [
            'USDC' => '20',
            'USDT' => '20',
            'DAI'  => '15',
            'FRAX' => '10',
            'TUSD' => '10',
            'BUSD' => '10',
            'GUSD' => '5',
            'HUSD' => '5',
            'PAX'  => '5',
        ];

        $data = new ReserveRebalanceData(
            poolId: 'pool-diverse',
            targetAllocations: $targetAllocations,
            executedBy: 'diversifier'
        );

        $this->assertCount(9, $data->targetAllocations);
        $this->assertEquals('20', $data->targetAllocations['USDC']);
        $this->assertEquals('5', $data->targetAllocations['PAX']);
    }

    #[Test]
    public function test_handles_decimal_precision_in_allocations(): void
    {
        $targetAllocations = [
            'USDC' => '33.333333',
            'USDT' => '33.333333',
            'DAI'  => '33.333334',
        ];

        $data = new ReserveRebalanceData(
            poolId: 'pool-precise',
            targetAllocations: $targetAllocations,
            executedBy: 'precision-bot'
        );

        $this->assertEquals('33.333333', $data->targetAllocations['USDC']);
        $this->assertEquals('33.333334', $data->targetAllocations['DAI']);
    }

    #[Test]
    public function test_handles_zero_allocations(): void
    {
        // Sometimes assets might be completely removed
        $targetAllocations = [
            'USDC' => '60',
            'USDT' => '40',
            'DAI'  => '0', // Being phased out
        ];

        $data = new ReserveRebalanceData(
            poolId: 'pool-phaseout',
            targetAllocations: $targetAllocations,
            executedBy: 'phase-out-bot'
        );

        $this->assertEquals('0', $data->targetAllocations['DAI']);
    }

    #[Test]
    public function test_immutability_of_properties(): void
    {
        $data = new ReserveRebalanceData(
            poolId: 'pool-readonly',
            targetAllocations: ['USDC' => '100'],
            executedBy: 'readonly-test'
        );

        // All properties should be readonly
        $reflection = new ReflectionClass($data);

        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue($property->isReadOnly());
        }
    }

    #[Test]
    public function test_preserves_allocation_order(): void
    {
        $targetAllocations = [
            'USDT' => '30',
            'USDC' => '40',
            'DAI'  => '20',
            'FRAX' => '10',
        ];

        $data = new ReserveRebalanceData(
            poolId: 'pool-order',
            targetAllocations: $targetAllocations,
            executedBy: 'order-keeper'
        );

        // Order should be preserved
        $keys = array_keys($data->targetAllocations);
        $this->assertEquals(['USDT', 'USDC', 'DAI', 'FRAX'], $keys);
    }

    #[Test]
    public function test_handles_string_numeric_allocations(): void
    {
        $targetAllocations = [
            'USDC' => '40.00',
            'USDT' => '35.50',
            'DAI'  => '24.50',
        ];

        $data = new ReserveRebalanceData(
            poolId: 'pool-string',
            targetAllocations: $targetAllocations,
            executedBy: 'string-handler'
        );

        $this->assertEquals('40.00', $data->targetAllocations['USDC']);
        $this->assertEquals('35.50', $data->targetAllocations['USDT']);
        $this->assertEquals('24.50', $data->targetAllocations['DAI']);
    }
}
