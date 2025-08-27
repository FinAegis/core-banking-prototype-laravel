<?php

namespace App\Domain\Wallet\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Wallet\Activities\WithdrawAssetActivity;
use Generator;
use Workflow\ActivityStub;
use Workflow\Workflow;

class WalletWithdrawWorkflow extends Workflow
{
    /**
     * Execute wallet withdrawal for a specific asset.
     */
    public function execute(AccountUuid $accountUuid, string $assetCode, int $amount): Generator
    {
        return yield ActivityStub::make(
            WithdrawAssetActivity::class,
            $accountUuid,
            $assetCode,
            $amount
        );
    }
}
