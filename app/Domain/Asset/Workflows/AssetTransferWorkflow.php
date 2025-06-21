<?php

declare(strict_types=1);

namespace App\Domain\Asset\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Asset\Workflows\Activities\InitiateAssetTransferActivity;
use App\Domain\Asset\Workflows\Activities\ValidateExchangeRateActivity;
use App\Domain\Asset\Workflows\Activities\CompleteAssetTransferActivity;
use App\Domain\Asset\Workflows\Activities\FailAssetTransferActivity;
use Workflow\Workflow;
use Workflow\ActivityStub;
use Workflow\ChildWorkflowStub;
use App\Domain\Asset\Workflows\Activities\ReverseAssetTransferActivity;

class AssetTransferWorkflow extends Workflow
{
    /**
     * Execute asset transfer workflow with compensation logic
     */
    public function execute(
        AccountUuid $fromAccountUuid,
        AccountUuid $toAccountUuid,
        string $fromAssetCode,
        string $toAssetCode,
        Money $fromAmount,
        ?string $description = null
    ): \Generator {
        try {
            // Step 1: Initiate the transfer
            $transferId = yield ActivityStub::make(
                InitiateAssetTransferActivity::class,
                $fromAccountUuid,
                $toAccountUuid,
                $fromAssetCode,
                $toAssetCode,
                $fromAmount,
                $description
            );
            
            // Add compensation to reverse the initiated transfer if something fails
            $this->addCompensation(fn() => ActivityStub::make(
                ReverseAssetTransferActivity::class,
                $transferId,
                $fromAccountUuid,
                $toAccountUuid,
                $fromAssetCode,
                $toAssetCode,
                $fromAmount
            ));
            
            // Step 2: If cross-asset transfer, validate exchange rate and calculate target amount
            $toAmount = $fromAmount; // Default for same-asset transfers
            $exchangeRate = null;
            
            if ($fromAssetCode !== $toAssetCode) {
                $rateData = yield ActivityStub::make(
                    ValidateExchangeRateActivity::class,
                    $fromAssetCode,
                    $toAssetCode,
                    $fromAmount
                );
                
                $toAmount = $rateData['to_amount'];
                $exchangeRate = $rateData['exchange_rate'];
            }
            
            // Step 3: Complete the transfer
            yield ActivityStub::make(
                CompleteAssetTransferActivity::class,
                $transferId,
                $fromAccountUuid,
                $toAccountUuid,
                $fromAssetCode,
                $toAssetCode,
                $fromAmount,
                $toAmount,
                $exchangeRate
            );
            
            return [
                'transfer_id' => $transferId,
                'from_account' => $fromAccountUuid->toString(),
                'to_account' => $toAccountUuid->toString(),
                'from_asset' => $fromAssetCode,
                'to_asset' => $toAssetCode,
                'from_amount' => $fromAmount->getAmount(),
                'to_amount' => $toAmount->getAmount(),
                'exchange_rate' => $exchangeRate,
                'status' => 'completed',
            ];
            
        } catch (\Throwable $th) {
            // Execute compensations in reverse order
            yield from $this->compensate();
            
            // Step 4: Handle failure
            yield ActivityStub::make(
                FailAssetTransferActivity::class,
                $transferId ?? null,
                $fromAccountUuid,
                $toAccountUuid,
                $fromAssetCode,
                $toAssetCode,
                $fromAmount,
                $th->getMessage()
            );
            
            throw $th;
        }
    }
}