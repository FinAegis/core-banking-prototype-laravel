<?php

namespace Tests\Traits;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Stablecoin\Models\StablecoinCollateralPosition;
use Illuminate\Support\Str;
use Mockery;

trait MocksWorkflows
{
    protected function mockWorkflows(): void
    {
        // Mock WorkflowStub::make to return a mock that can handle start() and await()
        Mockery::mock('alias:Workflow\WorkflowStub')
            ->shouldReceive('make')
            ->andReturnUsing(function ($workflowClass) {
                $mock = Mockery::mock('WorkflowStub');

                // Handle specific workflows
                if ($workflowClass === \App\Domain\Stablecoin\Workflows\MintStablecoinWorkflow::class) {
                    $mock->shouldReceive('start')->andReturnUsing(function ($accountUuid, $stablecoinCode, $collateralAssetCode, $collateralAmount, $mintAmount, $existingPositionUuid = null) {
                        // Create the position
                        $positionUuid = Str::uuid()->toString();

                        StablecoinCollateralPosition::create([
                            'uuid'                  => $positionUuid,
                            'account_uuid'          => (string) $accountUuid,
                            'stablecoin_code'       => $stablecoinCode,
                            'collateral_asset_code' => $collateralAssetCode,
                            'collateral_amount'     => $collateralAmount,
                            'debt_amount'           => $mintAmount,
                            'collateral_ratio'      => $collateralAmount / $mintAmount,
                            'status'                => 'active',
                        ]);

                        // Update account balances
                        $account = Account::where('uuid', (string) $accountUuid)->first();
                        if ($account) {
                            // Deduct collateral
                            $collateralBalance = AccountBalance::where('account_uuid', $account->uuid)
                                ->where('asset_code', $collateralAssetCode)
                                ->first();
                            if ($collateralBalance) {
                                $collateralBalance->decrement('balance', $collateralAmount);
                            }

                            // Add minted stablecoins
                            $stablecoinBalance = AccountBalance::firstOrCreate(
                                [
                                    'account_uuid' => $account->uuid,
                                    'asset_code'   => $stablecoinCode,
                                ],
                                [
                                    'balance' => 0,
                                ]
                            );
                            $stablecoinBalance->increment('balance', $mintAmount);
                        }

                        $resultMock = Mockery::mock('WorkflowResult');
                        $resultMock->shouldReceive('await')->andReturn($positionUuid);

                        return $resultMock;
                    });
                } else {
                    // Default behavior for other workflows
                    $mock->shouldReceive('start')->andReturnSelf();
                    $mock->shouldReceive('await')->andReturn(Str::uuid()->toString());
                }

                return $mock;
            });
    }

    protected function mockSubProductService(array $enabledProducts = ['stablecoins']): void
    {
        $this->app->bind(\App\Domain\Product\Services\SubProductService::class, function () use ($enabledProducts) {
            $mock = Mockery::mock(\App\Domain\Product\Services\SubProductService::class);

            foreach ($enabledProducts as $product) {
                $mock->shouldReceive('isEnabled')
                    ->with($product)
                    ->andReturn(true);
            }

            $mock->shouldReceive('isEnabled')
                ->andReturn(false); // Default for other products

            $mock->shouldReceive('isFeatureEnabled')
                ->andReturn(true);

            return $mock;
        });
    }
}
