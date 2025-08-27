<?php

namespace App\Domain\Wallet\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Wallet\Activities\ConvertAssetActivity;
use Generator;
use Throwable;
use Workflow\ActivityStub;
use Workflow\Workflow;

class WalletConvertWorkflow extends Workflow
{
    /**
     * Execute wallet currency conversion within the same account
     * Uses AssetTransferAggregate for proper cross-asset operations.
     */
    public function execute(
        AccountUuid $accountUuid,
        string $fromAssetCode,
        string $toAssetCode,
        int $amount
    ): Generator {
        try {
            $result = yield ActivityStub::make(
                ConvertAssetActivity::class,
                $accountUuid,
                $fromAssetCode,
                $toAssetCode,
                $amount
            );

            // Add compensation to reverse the conversion
            // This requires knowing the converted amount to reverse properly
            $this->addCompensation(
                fn () => ActivityStub::make(
                    ConvertAssetActivity::class,
                    $accountUuid,
                    $toAssetCode,    // Reverse: from -> to becomes to -> from
                    $fromAssetCode,  // Reverse: to -> from becomes from -> to
                    $result['converted_amount'] // Use actual converted amount for proper reversal
                )
            );

            return $result;
        } catch (Throwable $th) {
            yield from $this->compensate();
            throw $th;
        }
    }
}
