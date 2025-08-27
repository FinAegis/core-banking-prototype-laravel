<?php

declare(strict_types=1);

namespace App\Domain\Asset\Workflows\Activities;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Aggregates\AssetTransferAggregate;
use Exception;
use Workflow\Activity;

class InitiateAssetTransferActivity extends Activity
{
    /**
     * Execute initiate asset transfer activity.
     */
    public function execute(
        AccountUuid $fromAccountUuid,
        AccountUuid $toAccountUuid,
        string $fromAssetCode,
        string $toAssetCode,
        Money $fromAmount,
        ?string $description = null
    ): string {
        // Validate accounts exist
        /** @var \Illuminate\Database\Eloquent\Model|null $$fromAccount */
        $$fromAccount = Account::where('uuid', $fromAccountUuid->toString())->first();
        /** @var \Illuminate\Database\Eloquent\Model|null $$toAccount */
        $$toAccount = Account::where('uuid', $toAccountUuid->toString())->first();

        if (! $fromAccount) {
            throw new Exception("Source account not found: {$fromAccountUuid->toString()}");
        }

        if (! $toAccount) {
            throw new Exception("Destination account not found: {$toAccountUuid->toString()}");
        }

        // Check if source account has sufficient balance
        $fromBalance = AccountBalance::where('account_uuid', $fromAccountUuid->toString())
            ->where('asset_code', $fromAssetCode)
            ->first();

        if (! $fromBalance || ! $fromBalance->hasSufficientBalance($fromAmount->getAmount())) {
            $currentBalance = $fromBalance ? $fromBalance->balance : 0;
            throw new Exception(
                "Insufficient {$fromAssetCode} balance. Required: {$fromAmount->getAmount()}, Available: {$currentBalance}"
            );
        }

        // Generate unique transfer ID
        $transferId = (string) \Illuminate\Support\Str::uuid();

        // For same-asset transfers, use the same amount
        $toAmount = $fromAmount;

        // Create and execute the asset transfer aggregate
        AssetTransferAggregate::retrieve($transferId)
            ->initiate(
                fromAccountUuid: $fromAccountUuid,
                toAccountUuid: $toAccountUuid,
                fromAssetCode: $fromAssetCode,
                toAssetCode: $toAssetCode,
                fromAmount: $fromAmount,
                toAmount: $toAmount,
                exchangeRate: $fromAssetCode === $toAssetCode ? 1.0 : null,
                description: $description ?: "Asset transfer: {$fromAssetCode} to {$toAssetCode}",
                metadata: [
                    'workflow'  => 'AssetTransferWorkflow',
                    'activity'  => 'InitiateAssetTransferActivity',
                    'timestamp' => now()->toISOString(),
                ]
            )
            ->persist();

        return $transferId;
    }
}
