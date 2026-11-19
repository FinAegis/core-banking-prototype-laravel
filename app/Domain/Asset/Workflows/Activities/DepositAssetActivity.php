<?php

declare(strict_types=1);

namespace App\Domain\Asset\Workflows\Activities;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Workflows\DirectFundingGuard;
use App\Domain\Asset\Aggregates\AssetTransactionAggregate;
use App\Domain\Asset\Workflows\AssetDepositWorkflow;
use Workflow\Activity;

class DepositAssetActivity extends Activity
{
    /**
     * Execute asset deposit activity.
     */
    public function execute(
        AccountUuid $accountUuid,
        string $assetCode,
        Money $money,
        ?string $description = null
    ): string {
        DirectFundingGuard::assertAllowed($this->storedWorkflow, AssetDepositWorkflow::class);

        // Generate unique transaction ID
        $transactionId = (string) \Illuminate\Support\Str::uuid();

        // Create and execute the asset transaction aggregate
        AssetTransactionAggregate::retrieve($transactionId)
            ->credit(
                accountUuid: $accountUuid,
                assetCode: $assetCode,
                money: $money,
                description: $description ?: "Asset deposit: {$assetCode}",
                metadata: [
                    'workflow'  => 'AssetDepositWorkflow',
                    'activity'  => 'DepositAssetActivity',
                    'timestamp' => now()->toISOString(),
                ]
            )
            ->persist();

        return $transactionId;
    }
}
