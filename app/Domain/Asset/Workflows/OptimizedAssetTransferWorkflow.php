<?php

declare(strict_types=1);

namespace App\Domain\Asset\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Asset\Workflows\Activities\CompleteAssetTransferActivity;
use App\Domain\Asset\Workflows\Activities\FailAssetTransferActivity;
use App\Domain\Asset\Workflows\Activities\OptimizedInitiateAssetTransferActivity;
use App\Domain\Performance\Services\TransferOptimizationService;
use Generator;
use Throwable;
use Workflow\ActivityStub;
use Workflow\Workflow;

/**
 * Optimized version of AssetTransferWorkflow
 * Targets sub-second performance with caching and parallel processing.
 */
class OptimizedAssetTransferWorkflow extends Workflow
{
    /**
     * Execute optimized asset transfer between accounts.
     *
     * @param  AccountUuid  $fromAccountUuid  Source account UUID
     * @param  AccountUuid  $toAccountUuid  Destination account UUID
     * @param  string  $fromAssetCode  Source asset code
     * @param  string  $toAssetCode  Destination asset code
     * @param  Money  $fromAmount  Amount to transfer
     * @param  string|null  $description  Transfer description
     * @return array Transfer result with performance metrics
     */
    public function execute(
        AccountUuid $fromAccountUuid,
        AccountUuid $toAccountUuid,
        string $fromAssetCode,
        string $toAssetCode,
        Money $fromAmount,
        ?string $description = null
    ): Generator {
        $startTime = microtime(true);

        // Pre-warm caches for better performance
        $optimizationService = app(TransferOptimizationService::class);
        $optimizationService->warmUpCaches(
            [
                (string) $fromAccountUuid,
                (string) $toAccountUuid,
            ]
        );

        $transferId = null;

        try {
            // Initiate the transfer with optimized activity
            $transferId = yield ActivityStub::make(
                OptimizedInitiateAssetTransferActivity::class,
                $fromAccountUuid,
                $toAccountUuid,
                $fromAssetCode,
                $toAssetCode,
                $fromAmount,
                $description
            );

            // Add compensation to reverse the transfer if something goes wrong
            $this->addCompensation(
                fn () => ActivityStub::make(
                    FailAssetTransferActivity::class,
                    $transferId,
                    'Transfer failed during workflow execution'
                )
            );

            // For same-asset transfers, we can complete immediately
            if ($fromAssetCode === $toAssetCode) {
                // Complete the transfer
                yield ActivityStub::make(
                    CompleteAssetTransferActivity::class,
                    $transferId
                );
            } else {
                // For cross-asset transfers, we might need currency conversion
                // This would involve exchange rate service and additional activities
                // For now, we'll just complete it
                yield ActivityStub::make(
                    CompleteAssetTransferActivity::class,
                    $transferId
                );
            }

            // Calculate execution time
            $executionTime = microtime(true) - $startTime;

            return [
                'success'           => true,
                'transfer_id'       => $transferId,
                'execution_time_ms' => round($executionTime * 1000, 2),
                'optimized'         => true,
            ];
        } catch (Throwable $exception) {
            // Compensate on failure
            yield from $this->compensate();

            // Calculate execution time even on failure
            $executionTime = microtime(true) - $startTime;

            return [
                'success'           => false,
                'error'             => $exception->getMessage(),
                'execution_time_ms' => round($executionTime * 1000, 2),
                'optimized'         => true,
            ];
        }
    }
}
