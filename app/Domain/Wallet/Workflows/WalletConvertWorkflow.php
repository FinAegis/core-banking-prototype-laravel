<?php

namespace App\Domain\Wallet\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Wallet\Activities\ConvertAssetActivity;
use Workflow\ActivityStub;
use Workflow\Workflow;

class WalletConvertWorkflow extends Workflow
{
    /**
     * Execute wallet currency conversion within the same account
     * Uses AssetTransferAggregate for proper cross-asset operations
     *
     * @param AccountUuid $accountUuid
     * @param string $fromAssetCode
     * @param string $toAssetCode  
     * @param int $amount
     * @return \Generator
     */
    public function execute(
        AccountUuid $accountUuid,
        string $fromAssetCode,
        string $toAssetCode,
        int $amount
    ): \Generator {
        return yield ActivityStub::make(
            ConvertAssetActivity::class,
            $accountUuid,
            $fromAssetCode,
            $toAssetCode,
            $amount
        );
    }
}