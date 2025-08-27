<?php

namespace App\Domain\Wallet\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Wallet\Activities\DepositAssetActivity;
use Generator;
use Workflow\ActivityStub;
use Workflow\Workflow;

class WalletDepositWorkflow extends Workflow
{
    /**
     * Execute wallet deposit for a specific asset.
     */
    public function execute(AccountUuid $accountUuid, string $assetCode, int $amount): Generator
    {
        return yield ActivityStub::make(
            DepositAssetActivity::class,
            $accountUuid,
            $assetCode,
            $amount
        );
    }
}
