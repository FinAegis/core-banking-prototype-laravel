<?php

declare(strict_types=1);

namespace App\Domain\Asset\Workflows\Activities;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Asset\Aggregates\AssetTransferAggregate;
use App\Domain\Performance\Services\TransferOptimizationService;
use Workflow\Activity;
use Illuminate\Support\Str;

/**
 * Optimized version of InitiateAssetTransferActivity
 * Uses caching and single-query validation for sub-second performance
 */
class OptimizedInitiateAssetTransferActivity extends Activity
{
    private TransferOptimizationService $optimizationService;
    
    public function __construct()
    {
        $this->optimizationService = app(TransferOptimizationService::class);
    }
    
    /**
     * Execute optimized initiate asset transfer activity
     */
    public function execute(
        AccountUuid $fromAccountUuid,
        AccountUuid $toAccountUuid,
        string $fromAssetCode,
        string $toAssetCode,
        Money $fromAmount,
        ?string $description = null
    ): string {
        // Use single query validation for better performance
        $this->optimizationService->preValidateTransfer(
            $fromAccountUuid,
            $toAccountUuid,
            $fromAssetCode,
            $fromAmount->getAmount()
        );
        
        // Generate unique transfer ID
        $transferId = (string) Str::uuid();
        
        // For same-asset transfers, use the same amount
        $toAmount = $fromAmount;
        
        // Create and execute the asset transfer aggregate
        AssetTransferAggregate::retrieve($transferId)
            ->initiate(
                fromAccountUuid: $fromAccountUuid,
                toAccountUuid: $toAccountUuid,
                fromAssetCode: $fromAssetCode,
                toAssetCode: $toAssetCode,
                fromAmount: $fromAmount,
                toAmount: $toAmount,
                exchangeRate: $fromAssetCode === $toAssetCode ? 1.0 : null,
                description: $description ?: "Asset transfer: {$fromAssetCode} to {$toAssetCode}",
                metadata: [
                    'workflow' => 'AssetTransferWorkflow',
                    'activity' => 'OptimizedInitiateAssetTransferActivity',
                    'timestamp' => now()->toISOString(),
                    'optimized' => true,
                ]
            )
            ->persist();
        
        // Clear caches after successful initiation
        $this->optimizationService->clearTransferCaches(
            (string) $fromAccountUuid,
            $fromAssetCode
        );
        
        return $transferId;
    }
}