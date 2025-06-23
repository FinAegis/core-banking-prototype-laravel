<?php

declare(strict_types=1);

namespace App\Domain\Asset\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Asset\Workflows\Activities\DepositAssetActivity;
use Workflow\Workflow;
use Workflow\ActivityStub;

class AssetDepositWorkflow extends Workflow
{
    /**
     * Execute asset deposit workflow
     */
    public function execute(
        AccountUuid $accountUuid,
        string $assetCode,
        int $amount,
        ?string $description = null
    ): \Generator {
        return yield ActivityStub::make(
            DepositAssetActivity::class,
            $accountUuid,
            $assetCode,
            $amount,
            $description
        );
    }
}