<?php

declare(strict_types=1);

namespace App\Domain\Asset\Workflows\Activities;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Aggregates\AssetTransactionAggregate;
use Exception;
use Workflow\Activity;

class WithdrawAssetActivity extends Activity
{
    /**
     * Execute asset withdrawal activity.
     */
    public function execute(
        AccountUuid $accountUuid,
        string $assetCode,
        Money $money,
        ?string $description = null
    ): string {
        // Check if account has sufficient balance
        $accountBalance = AccountBalance::where('account_uuid', $accountUuid->toString())
            ->where('asset_code', $assetCode)
            ->first();

        if (! $accountBalance || ! $accountBalance->hasSufficientBalance($money->getAmount())) {
            $currentBalance = $accountBalance ? $accountBalance->balance : 0;
            throw new Exception(
                "Insufficient balance for {$assetCode}. Required: {$money->getAmount()}, Available: {$currentBalance}"
            );
        }

        // Generate unique transaction ID
        $transactionId = (string) \Illuminate\Support\Str::uuid();

        // Create and execute the asset transaction aggregate
        AssetTransactionAggregate::retrieve($transactionId)
            ->debit(
                accountUuid: $accountUuid,
                assetCode: $assetCode,
                money: $money,
                description: $description ?: "Asset withdrawal: {$assetCode}",
                metadata: [
                    'workflow'  => 'AssetWithdrawWorkflow',
                    'activity'  => 'WithdrawAssetActivity',
                    'timestamp' => now()->toISOString(),
                ]
            )
            ->persist();

        return $transactionId;
    }
}
