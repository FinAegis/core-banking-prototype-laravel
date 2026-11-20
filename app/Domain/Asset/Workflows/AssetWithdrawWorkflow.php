<?php

declare(strict_types=1);

namespace App\Domain\Asset\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Asset\Workflows\Activities\WithdrawAssetActivity;
use Generator;
use Workflow\ActivityStub;
use Workflow\Workflow;

class AssetWithdrawWorkflow extends Workflow
{
    /**
     * Execute asset withdrawal workflow.
     *
     * The amount arrives in the asset's minor units; the activity takes it as
     * Money, like the legacy account workflows.
     */
    public function execute(
        AccountUuid $accountUuid,
        string $assetCode,
        int $amount,
        ?string $description = null
    ): Generator {
        return yield ActivityStub::make(
            WithdrawAssetActivity::class,
            $accountUuid,
            $assetCode,
            new Money($amount),
            $description
        );
    }
}
