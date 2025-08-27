<?php

declare(strict_types=1);

namespace App\Domain\Asset\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use Generator;
use Throwable;
use Workflow\ChildWorkflowStub;
use Workflow\Workflow;

class AssetTransferWorkflow extends Workflow
{
    /**
     * Execute asset transfer workflow with compensation logic.
     */
    public function execute(
        AccountUuid $fromAccountUuid,
        AccountUuid $toAccountUuid,
        string $assetCode,
        int $amount,
        ?string $description = null
    ): Generator {
        try {
            // For simple same-asset transfers, use our wallet workflows
            $workflow = ChildWorkflowStub::make(
                \App\Domain\Wallet\Workflows\WalletTransferWorkflow::class,
                $fromAccountUuid,
                $toAccountUuid,
                $assetCode,
                $amount,
                $description
            );

            return yield $workflow;
        } catch (Throwable $th) {
            // Execute compensations in reverse order
            yield from $this->compensate();
            throw $th;
        }
    }
}
