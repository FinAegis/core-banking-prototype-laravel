<?php

declare(strict_types=1);

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Workflows\DepositAccountWorkflow;
use App\Domain\Account\Workflows\WithdrawAccountWorkflow;
use App\Domain\Asset\Workflows\AssetDepositWorkflow;
use App\Domain\Asset\Workflows\AssetWithdrawWorkflow;
use App\Domain\Asset\Workflows\CurrencyConversionWorkflow;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowFailedStatus;
use Workflow\WorkflowStub;

/*
 * The conversion used to start a withdrawal and a deposit as two independent
 * workflows, so the deposit could complete even when the withdrawal was
 * refused. The conversion workflow credits the target asset only after the
 * debit succeeded, and refunds the source when the credit fails.
 */

/**
 * Starts a conversion and swallows the failure a refused child rethrows, so the
 * test can inspect what was dispatched.
 */
function startConversion(string $from, int $fromAmount, string $to, int $toAmount): WorkflowStub
{
    $workflow = WorkflowStub::make(CurrencyConversionWorkflow::class);

    try {
        $workflow->start(new AccountUuid('acct-1'), $from, $fromAmount, $to, $toAmount);
    } catch (Throwable) {
        // The workflow records the failure in its status.
    }

    return WorkflowStub::load($workflow->id());
}

it('debits the source asset and then credits the target asset', function (): void {
    WorkflowStub::fake();
    WorkflowStub::mock(AssetWithdrawWorkflow::class, 'debit-id');
    WorkflowStub::mock(AssetDepositWorkflow::class, 'credit-id');

    $workflow = startConversion('EUR', 1000, 'GBP', 860);

    WorkflowStub::assertDispatchedTimes(AssetWithdrawWorkflow::class, 1);
    WorkflowStub::assertDispatched(AssetWithdrawWorkflow::class, fn (AccountUuid $uuid, string $assetCode, int $amount): bool => $assetCode === 'EUR' && $amount === 1000);
    WorkflowStub::assertDispatchedTimes(AssetDepositWorkflow::class, 1);
    WorkflowStub::assertDispatched(AssetDepositWorkflow::class, fn (AccountUuid $uuid, string $assetCode, int $amount): bool => $assetCode === 'GBP' && $amount === 860);
    expect($workflow->status())->toBe(WorkflowCompletedStatus::class);
});

it('debits a USD source through the legacy account workflow', function (): void {
    WorkflowStub::fake();
    WorkflowStub::mock(WithdrawAccountWorkflow::class, true);
    WorkflowStub::mock(AssetDepositWorkflow::class, 'credit-id');

    startConversion('USD', 1000, 'EUR', 920);

    WorkflowStub::assertDispatched(WithdrawAccountWorkflow::class, fn (AccountUuid $uuid, mixed $money): bool => $money instanceof Money && $money->getAmount() === 1000);
    WorkflowStub::assertNotDispatched(AssetWithdrawWorkflow::class);
    WorkflowStub::assertDispatched(AssetDepositWorkflow::class, fn (AccountUuid $uuid, string $assetCode, int $amount): bool => $assetCode === 'EUR' && $amount === 920);
});

it('credits nothing when the debit is refused', function (): void {
    WorkflowStub::fake();
    $debitAttempted = false;
    WorkflowStub::mock(AssetWithdrawWorkflow::class, function () use (&$debitAttempted): never {
        $debitAttempted = true;

        throw new RuntimeException('Insufficient balance for EUR.');
    });
    WorkflowStub::mock(AssetDepositWorkflow::class, 'credit-id');

    $workflow = startConversion('EUR', 1000, 'GBP', 860);

    expect($debitAttempted)->toBeTrue();
    WorkflowStub::assertNotDispatched(AssetDepositWorkflow::class);
    expect($workflow->status())->toBe(WorkflowFailedStatus::class);
});

it('refunds the source asset when the credit fails after the debit', function (): void {
    WorkflowStub::fake();
    WorkflowStub::mock(AssetWithdrawWorkflow::class, 'debit-id');
    WorkflowStub::mock(AssetDepositWorkflow::class, function ($context, AccountUuid $uuid, string $assetCode, int $amount): string {
        if ($assetCode === 'GBP') {
            throw new RuntimeException('Target asset unavailable.');
        }

        return 'refund-id';
    });

    $workflow = startConversion('EUR', 1000, 'GBP', 860);

    WorkflowStub::assertDispatched(AssetDepositWorkflow::class, fn (AccountUuid $uuid, string $assetCode, int $amount): bool => $assetCode === 'EUR' && $amount === 1000);
    expect($workflow->status())->toBe(WorkflowFailedStatus::class);
});

it('refunds a USD source through the legacy account workflow', function (): void {
    WorkflowStub::fake();
    WorkflowStub::mock(WithdrawAccountWorkflow::class, true);
    WorkflowStub::mock(AssetDepositWorkflow::class, function (): never {
        throw new RuntimeException('Target asset unavailable.');
    });
    WorkflowStub::mock(DepositAccountWorkflow::class, true);

    startConversion('USD', 1000, 'EUR', 920);

    WorkflowStub::assertDispatched(DepositAccountWorkflow::class, fn (AccountUuid $uuid, mixed $money): bool => $money instanceof Money && $money->getAmount() === 1000);
});
