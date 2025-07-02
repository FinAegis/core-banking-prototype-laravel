<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Projectors;

use App\Domain\Stablecoin\Events\StablecoinOperationCreated;
use App\Domain\Stablecoin\Events\StablecoinOperationCompleted;
use App\Domain\Stablecoin\Events\StablecoinOperationFailed;
use App\Models\StablecoinOperation;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class StablecoinOperationProjector extends Projector
{
    public function onStablecoinOperationCreated(StablecoinOperationCreated $event): void
    {
        StablecoinOperation::create([
            'uuid' => $event->uuid,
            'type' => $event->type,
            'stablecoin' => $event->stablecoin,
            'amount' => $event->amount,
            'collateral_asset' => $event->collateralAsset,
            'collateral_amount' => $event->collateralAmount,
            'collateral_return' => $event->collateralReturn,
            'source_account' => $event->sourceAccount,
            'recipient_account' => $event->recipientAccount,
            'operator_uuid' => $event->operatorUuid,
            'position_uuid' => $event->positionUuid,
            'reason' => $event->reason,
            'status' => $event->status,
            'metadata' => $event->metadata,
        ]);
    }

    public function onStablecoinOperationCompleted(StablecoinOperationCompleted $event): void
    {
        $operation = StablecoinOperation::where('uuid', $event->uuid)->first();
        
        if ($operation) {
            $operation->update([
                'status' => 'completed',
                'executed_at' => now(),
            ]);
        }
    }

    public function onStablecoinOperationFailed(StablecoinOperationFailed $event): void
    {
        $operation = StablecoinOperation::where('uuid', $event->uuid)->first();
        
        if ($operation) {
            $operation->update([
                'status' => 'failed',
                'metadata' => array_merge($operation->metadata ?? [], [
                    'error' => $event->reason,
                ]),
            ]);
        }
    }
}