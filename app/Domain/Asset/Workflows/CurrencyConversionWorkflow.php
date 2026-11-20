<?php

declare(strict_types=1);

namespace App\Domain\Asset\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Workflows\DepositAccountWorkflow;
use App\Domain\Account\Workflows\WithdrawAccountWorkflow;
use Generator;
use React\Promise\PromiseInterface;
use Throwable;
use Workflow\ChildWorkflowStub;
use Workflow\Workflow;

/**
 * Converts one asset into another on the same account.
 *
 * The debit of the source asset runs first, and the credit of the target asset
 * starts only after the debit has succeeded. A refused debit (insufficient
 * balance, for example after a concurrent conversion) therefore never leaves
 * an unbacked credit behind. If the credit fails after the debit, the source
 * amount is credited back and the workflow fails.
 *
 * USD is still held by the legacy account aggregate, so a USD source is debited
 * and refunded through the legacy account workflows. Every other side runs
 * through the asset workflows. Both run as child workflows, so the direct
 * funding guard treats them as a settlement step, not as direct funding.
 */
class CurrencyConversionWorkflow extends Workflow
{
    /**
     * @param int $fromAmount source amount in the source asset's minor units
     * @param int $toAmount   target amount in the target asset's minor units
     */
    public function execute(
        AccountUuid $accountUuid,
        string $fromAssetCode,
        int $fromAmount,
        string $toAssetCode,
        int $toAmount,
    ): Generator {
        $description = "Currency conversion {$fromAssetCode} to {$toAssetCode}";

        yield $this->debit($accountUuid, $fromAssetCode, $fromAmount, $description);

        try {
            yield ChildWorkflowStub::make(AssetDepositWorkflow::class, $accountUuid, $toAssetCode, $toAmount, $description);
        } catch (Throwable $th) {
            yield $this->refund($accountUuid, $fromAssetCode, $fromAmount, "Refund: {$description}");

            throw $th;
        }

        return true;
    }

    /**
     * @return PromiseInterface<mixed>
     */
    private function debit(AccountUuid $accountUuid, string $assetCode, int $amount, string $description): PromiseInterface
    {
        if ($assetCode === 'USD') {
            return ChildWorkflowStub::make(WithdrawAccountWorkflow::class, $accountUuid, new Money($amount));
        }

        return ChildWorkflowStub::make(AssetWithdrawWorkflow::class, $accountUuid, $assetCode, $amount, $description);
    }

    /**
     * @return PromiseInterface<mixed>
     */
    private function refund(AccountUuid $accountUuid, string $assetCode, int $amount, string $description): PromiseInterface
    {
        if ($assetCode === 'USD') {
            return ChildWorkflowStub::make(DepositAccountWorkflow::class, $accountUuid, new Money($amount));
        }

        return ChildWorkflowStub::make(AssetDepositWorkflow::class, $accountUuid, $assetCode, $amount, $description);
    }
}
